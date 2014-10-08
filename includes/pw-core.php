<?php
include_once PW_PATH .'includes/pw-base.php';
include_once PW_PATH .'includes/pw-helpers.php';

class Pods_Wrapper
{
	public static $components = array(),
				  $classes = array();
	private static $php_classes = array();

	public static function init()
	{
		self::$php_classes = get_declared_classes();

		self::init_hooks();

		# Init/Register the plugin
		self::component_setup('pods-wrapper', dirname( dirname( __FILE__ ) ));

		self::init_plugins();
	}

	/**
	 * Look for plugins based on PW and load/initialize them
	 * @hook plugins_loaded
	 */
	public static function init_plugins()
	{
		if ( ! function_exists( 'get_plugins' ) )
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = get_plugins();
		foreach ($plugins as $file => $options)
		{
		 	if (is_plugin_active($file))
		 	{
		 		// check if this is a pw plugin
		 		$plugin = get_file_data( WP_PLUGIN_DIR . "/$file", ['Depends'=>'Depends'] );
		 		if ( strpos($plugin['Depends'], 'pods-wrapper') === false )
		 		 	continue;

		 		$name = preg_split('/[\/\.]/', $file)[0];
		 		$directory = WP_PLUGIN_DIR .'/'. plugin_dir_path($file);
		 		
		 		if ( self::component_setup($name, $directory) )
 					self::$components[$name]['root'] = dirname($directory);
		 	}
		}
	}

	/**
	 * Setup core hooks of PW
	 * @hook plugins_loaded
	 */
    public static function init_hooks()
    {
		//add_action( 'admin_init', array( 'Pods_Wrapper', 'check_dependencies' ) );

    	// init theme components
	    add_action( 'setup_theme', array('Pods_Wrapper', 'init_themes'), 20 );

	    // init components startup method
        add_filter( 'init', ['Pods_Wrapper', 'init_components'], 22 );

		// Plugin activation
		register_activation_hook( 'pods-wrapper/pods-wrapper.php', array( 'Pods_Wrapper', 'install' ) );

		// Admin and Front scripts enqueue
		add_action( 'admin_enqueue_scripts', array( 'Pods_Wrapper', 'register_scripts' ) );
		add_action( 'login_enqueue_scripts', array( 'Pods_Wrapper', 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( 'Pods_Wrapper', 'register_scripts' ) );
    }

    /**
     * Look for theme pwrapper folder and initialize its components
     * @hook setup_theme
     */
	public static function init_themes()
	{
		// Adds theme folder to paths
		$theme = get_stylesheet_directory() .'/pwrapper';
   	   	if ( self::component_setup('theme', $theme) )
   	   		self::$components['theme']['root'] = get_stylesheet_directory();


   	   	// parent theme
	    $parent = get_template_directory() .'/pwrapper';
	    if ($parent != $theme)
		   	if ( self::component_setup('theme_parent', $parent) )
	   	   		self::$components['theme_parent']['root'] = get_template_directory();


		self::component_call( 'init_theme', false );
	}

	/**
	 * Calls component_init for all loaded PW Classes
	 * @hook init
	 */
	public static function init_components()
	{
		$inits = self::classes_methods('_init');
		foreach ($inits as $class => $methods)
		{
			foreach ($methods as $method)
				call_user_func(array($class, $method));
		}
	}

	public static function component_setup($name, $directory, $init=true)
	{
		if ( is_dir($directory) )
		{
			self::$components[$name]['pwrapper'] = $directory;

	   	   	// Include files from path
	   	   	$classes = self::component_load( $name );
	        self::$classes = array_merge(self::$classes, $classes);

	   	   	// Initialize classes
	   	   	if ($init)
	   	   	{
		   	   	self::component_call( 'init', $name );
		   	   	self::component_call( 'init_hooks', $name );
		   	   	return $name;
	   	   	}
	   	   	return false;
		}
	}

	public static function register_scripts() {
		// Call classes script method
		self::component_call( 'register_scripts', false );
	}


	public static function check_dependencies() {
		# Check piklist is active
		include_once 'piklist-checker.php';
		if ( ! piklist_checker::check( __FILE__ ) ) {
			return;
		}
	}

	/*
	* To be run on plugin activation
	*/
	public static function install()
	{
		do_action( 'pw_install' );
	}

	public static function slug( $string )
	{
		return str_replace( '.php', '', str_replace( array( '-', ' ' ), '_', strtolower( $string ) ) );
	}


	// TODO: usar get_declared_classes para buscar também fora do escopo de arquivos (nomes de arquivo)

	public static function classes_loaded($base='pw_module')
	{
		$found = array();

        foreach (self::$classes as $cls) {
        	if (is_subclass_of($cls, $base))
        		$found[] = $cls;
        }

        return $found;
	}

	// todo: add ends_with param
	// Return the loaded classes methods
	public static function classes_methods($ends_with=false, $exceptions=array(), $base='pw_module')
	{
		if (!is_array($exceptions))
			$exceptions = [$exceptions];

		$found = array();

		// prepare the search pattern
		$patterns = [];

		/*if (!is_array($starts_with))
			$starts_with = [$starts_with];
		foreach ($starts_with as $portion)
			if (is_string($portion))
				$patterns[] = "^$portion.+";*/

		if (!is_array($ends_with))
			$ends_with = [$ends_with];
		foreach ($ends_with as $portion)
			if (is_string($portion))
				$patterns[] = ".*$portion\$";

		$patterns = implode('|', $patterns);
		$regex = "/($patterns)/";

		// look in the classes methods for the patterns
        foreach (self::$classes as $cls)
        {
        	if (in_array($cls, $exceptions))
        		continue;
        	if (!is_subclass_of($cls, $base))
        		continue;

        	$methods = get_class_methods($cls);

	        foreach ($methods as $k => $m)
	        	if ( !preg_match($regex, $m) )
	        		unset($methods[$k]);

        	if (count($methods) > 0)
        		$found[$cls] = $methods;
        }

        return $found;
	}

	/**
	 * Include every php file of mentioned component
	 * And match its classes
	 * @param  string $name  Component name
	 * @param  string $class Class name to match
	 * @return array         Found classes
	 */
	public static function component_load($name='pods-wrapper', $class='pw_module')
	{
		$units = $name
			? [ $name => self::$components[$name]]
			: self::$components;

		// components files folder
		$folder = 'includes';

		$found_classes = [];

		foreach ($units as $component => $options)
		{
			// already included
			if (is_array( $options['included'] ))
			{
				$found_classes = array_merge($found_classes, $options['classes']);
				continue;
			}
			else
			{
				// initialize component arrays
				self::$components[$component]['included'] = array();
				self::$components[$component]['classes'] = array();
			}

			# get the files of the given folder
			$files = self::component_files($folder, $component);
			foreach ( $files as $include )
			{
				# Do not include this
				$exclude_files = array('pw-base.php', 'pw-core.php', 'pods-checker.php');
				if ( strpos(__FILE__, $include) != FALSE || in_array($include, $exclude_files) )
					continue;

				// insert the file
				include_once $options['pwrapper'] . "/$folder/" . $include;
				self::$components[$component]['included'][] = $include;

				// classes on the file
				$new_classes = array_diff(get_declared_classes(), self::$php_classes);
				self::$php_classes = array_merge(self::$php_classes, $new_classes);

				// match classes found
				foreach ($new_classes as $class_name)
				{
					if ( is_subclass_of( $class_name, $class ) )
					{
						$found_classes[] = $class_name;
						self::$components[$component]['classes'][] = $class_name;

						// call static _construct
						if ( method_exists( $class_name, '_construct' ) )
							call_user_func( array( $class_name, '_construct' ) );
					}	
				}

			}
		}

		return $found_classes;
	}

	/**
	* Execute a static method on every file class of a component
	*/
	public static function component_call($method, $component='pods-wrapper', $class='pw_module')
	{
		$classes = self::component_load($component, $class);
		$result = [];

		foreach ($classes as $class_name)
		{
			if ( method_exists( $class_name, $method ) )
				$result[$class_name] = call_user_func( array( $class_name, $method ) );
		}

		return $result;
	}

	/*
	* List the include files of the folder in wpaf or piklist plugin/theme
	* If $path is specified look on at a given component (theme, plugin) - from piklist
	* Also allow user to execute the found includes _construct method
	*/
	public static function component_files($folder, $name=false) {
		$files = array();

        $units = $name
            ? [$name => self::$components[$name]]
            : self::$components;

		
		foreach ($units as $component => $options)
		{
            $includes = File_Helper::directory_list( $options['pwrapper'] .'/'. $folder );
	        $files = array_merge($files, $includes);
		}

		return $files;
	}


}
