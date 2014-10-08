<?php

class PW_Notice extends PW_Module
{
    static $notices = array();

    static function init_hooks()
    {
        add_filter( 'init', ['PW_Notice', 'init_session'], 10 );
    }

    // todo: add persistent notices (db or cache)
    
    static function init_session()
    {
        $pw_notices = Session_Helper::get('_pw_notices');

        if (!empty($pw_notices))
        {
            $pw_notices = json_decode($pw_notices, true);

            // add notices
            foreach ($pw_notices as $notice)
                call_user_func_array(['PW_Notice', 'add'], $notice);
        }
    }

	/*
	classes: message, error, info
	*/
    static function add($content, $class='message', $priority=11, $valid_callback=null, $hook=null, $permanent=false)
    {
        if ($permanent)
        {
            // check if already registred
            $pw_notices = Session_Helper::get('_pw_notices');
            if (!empty($pw_notices))
            {
                $pw_notices = json_decode($pw_notices, true);
                if ( in_array($content, wp_list_pluck($pw_notices, 0)) )
                    return true; // existent
            }

            $class .= " permanent";
        }

        $hooks = [];
        if ($hook)
            $hooks[] = $hook;

		if (is_admin())
			$hooks[] = 'admin_notices';
        else
        {
            // woocommerce
            if (class_exists( 'WooCommerce' ))
                $hooks[] = 'woocommerce_before_main_content';
            // woothemes
            if (function_exists('woo_version'))
                $hooks[] = 'woo_main_before';
            
            // wp default
            $hooks[] = 'loop_start';
        }

        $key = Callback_Helper::add_filter($hooks, ['PW_Notice', 'display_callback'], null, $priority);

        // save the included notice
        self::$notices[$key] = [$content, $class, $priority, $valid_callback, $hook, $permanent];

        Session_Helper::set('_pw_notices', json_encode(self::$notices));
    }

    static function display_callback()
    {
        $args = func_get_args();
        array_pop($args);
        $key = array_pop($args);

        if (isset(self::$notices[$key]))
        {
        	$notice  = self::$notices[$key];
            $content = $notice[0];
            $class   = $notice[1];
            $valid   = $notice[3];
            $permanent = $notice[5];

            // allow check for display time. eg: is_home()?
            if ($valid && is_callable($valid) && !call_user_func_array($valid, $notice))
            	return false;

            self::display($content, $class);


            // mark as shown if not permanent notice
            if (!$permanent)
            {
                unset(self::$notices[$key]);
                Session_Helper::set('_pw_notices', json_encode(self::$notices));
            }
        }
    }

    static function display($content, $class='message')
    {
        $classes = [$class, 'pw-notice'];
        if (is_admin())
            switch ($class) {
                case 'message':
                    $classes[] = 'updated'; break;
                case 'info':
                    $classes[] = 'update-nag'; break;
            }
        if (class_exists( 'WooCommerce' ))
            $classes[] = "woocommerce-$class";

        $class = implode(' ', $classes);

        echo PW_Template::render(
            'pw/notice.html',
            ['class'=>$class, 'content'=>$content],
            ['autoescape'=>false]
        );
    }
}