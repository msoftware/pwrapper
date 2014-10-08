<?php
//TODO: create logged in only methods

class PW_Ajax extends PW_Module {

	protected static $actions = array();
	protected static $nonce_salt = 'pwrapper';

	public static function init_hooks()
	{
		//Ajax request security
		add_action('admin_init', array('PW_Ajax', 'security_callback'));
		//Print javascript actions
		add_action('admin_footer', array('PW_Ajax', 'print_callback'));
		add_action('wp_footer', array('PW_Ajax', 'print_callback'));
	}

	// Adds callback to the ajax calls
	public static function ajax_init()
	{
		$ajax_methods = PWrapper::classes_methods(['_ajax', '_ajax_public']);
		foreach ($ajax_methods as $cls => $methods)
		{
			//$cls = strtolower($cls);

			foreach ($methods as $method)
			{
				$action = preg_replace('/_ajax($|_public$)/', '', $method);
				$ajax_action = "$cls.$action";

				// public site method
				$public = false;
				if (preg_match('/ajax_public/', $method)) {
					add_action("wp_ajax_nopriv_$ajax_action", array('PW_Ajax', 'wrapper_callback'));
					$public = true;
				}

				// admin method
				add_action("wp_ajax_$ajax_action", array('PW_Ajax', 'wrapper_callback'));

				self::$actions[$ajax_action] = [$action, $cls, $method, $public];
			}
		}
	}

	// Receive the response of an ajax post and deliver to the class method
	public static function wrapper_callback() {
		// determine the class and method based on the wp action name
		preg_match('/wp_ajax_(nopriv_)?(.*)/', current_filter(), $mt);
		$ajax_action = $mt[2];
		//extract( self::action_match($ajax_action) );
		$class    = self::$actions[$ajax_action][1];
		$method = self::$actions[$ajax_action][2];

		// get method params and fetch the post data
		$params = array();
		$cls_method = new ReflectionMethod($class, $method);
		foreach ($cls_method->getParameters() as $p)
		$params[] = $_POST[$p->getName()];

		// call the method
		ob_start();
		$return = call_user_func_array(array($class, $method), $params);

		$output = ob_get_contents();
		ob_end_clean();

		if (isset($return)) {
			$output = $return;
		}

		// header information
		Http_Helper::header_guess($output);

		// print output and exit
		if ($output === false) {
			die("0");
		} elseif (isset($output)) {
			die((string) $output);
		} else {

			die("1");
		}
	}

	// Checks if the running action has a valid nonce
	public static function security_callback() {
		if (defined('DOING_AJAX') && DOING_AJAX) {
			// current action
			$ajax_action = $_REQUEST['action'];

			// is a pw ajax method?
			if (array_key_exists($ajax_action, self::$actions)) {
				// check action's nonce
				check_ajax_referer("$ajax_action-".self::$nonce_salt, 'security', true);
			}
		}
	}

	// prints actions js functions with nonce and parameters
	public static function print_callback()
	{
		// todo: generate the print to a file and cache it
		// 		 regenerate only on changes
		
		// print on the front-end only public methods
		$scope = current_filter();
		if ($scope == 'admin_footer') {
			$public = false;
		} else {
			// front-end print only public methods
			$public = true;
		}

		$actions = array();
		foreach (self::$actions as $action => $params)
		if (!$public || true == $params[3]) {
			$actions[$action] = $params;
		}

		if (count($actions) > 0) {
			$js_actions = array();

			// creates actions object
			foreach ($actions as $ajax_action => $params) {
				$action   = $params[0];
				$class    = $params[1];
				$method = $params[2];

				$js_method = array();

				$js_method['name'] = $action;
				$js_method['params'] = array();
				$js_method['data'] = "action: '$ajax_action', ";

				// class params to the js method
				$cls_method = new ReflectionMethod($class, $method);
				foreach ($cls_method->getParameters() as $p) {
					$js_method['params'][] = $p->getName();
					$js_method['data'] .= "'".$p->getName()."': ".$p->getName().", ";
				}

				$js_method['params'][] = 'callback';
				$js_method['params'][] = 'params';
				$js_method['params'] = implode(', ', $js_method['params']);

				$js_method['nonce'] = wp_create_nonce("$ajax_action-".self::$nonce_salt);
				$js_method['data'] .= "security: '$js_method[nonce]'";

				$js_actions[$class][] = $js_method;
			}

			// check cache for the existence of this version
			$key = md5(json_encode($js_actions));
			if (!$js = wp_cache_get($key, 'pw_ajax')) {

				// generate the js methods
				$js = array();

				$js[] = "var ajaxurl = '".admin_url('admin-ajax.php')."';\n\n";

				foreach ($js_actions as $class => $methods) {
					$js[] = "var $class = new function() {\n";

					foreach ($methods as $js_method) {
						$js[] = "\tthis.$js_method[name] = function($js_method[params])
                        {
                            var data = { $js_method[data] };

                            params = jQuery.extend(true, {
                                url: ajaxurl,
                                type: 'POST',
                                data: data,
                                success: callback
                            }, params);

                            return jQuery.ajax(params);
                        }\n\n";
					}

					$js[] = "}\n\n";//end of class methods wrapper
				}

				require_once dirname(__FILE__).'/../lib/JSMin.php';

				$js = JSMin::minify(join('', $js));

				wp_cache_add($key, $js, 'pw_ajax');
			}

			// print the methods
			echo "<script type=\"text/javascript\">\n";
			echo $js;
			echo "</script>\n";
		}
	}

	// helper method to output data in a ajax method
	static function output($data, $type = 'json')
	{
		$output = $data;

		switch ($type) {
			case 'json':
				$json = json_encode($data);
				if (json_last_error() == JSON_ERROR_NONE) {
					$output = $json;
				}

			case 'json_str':
				Http_Helper::$next_header = 'json';
				break;
			case 'xml':
				Http_Helper::$next_header = 'xml';
				break;
		}

		return $output;
	}
}
