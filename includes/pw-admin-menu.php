<?php

class PW_Admin_Menu extends PW_Module {

    public static $menus = array();

    public static function init_hooks()
    {
        # Admin Menu initialization
        add_action( 'admin_menu', array('PW_Admin_Menu', 'init_admin_menu') );
        add_action( 'admin_init', ['PW_Admin_Menu', 'form_post_callback'], 1 );
    }

    /**
     * Inits the Admin Menu Module.
     * Calls every `admin_menu` method of PW_Module classes.
     * The class method should return an array of menu configurations.
     * They are quite the same params of `admin_menu_add`, but with candies included.
     * 
     * Menu array example format:
     *     return array(
     *     		'my-menu' => [ // the slug of the top menu entry
     * 			      'menu' 		=> 'Menu Title',
     * 			      'title' 		=> 'My Action',
     * 			      'capability' 	=> 'manage_options',
     * 			      'callback' 	=> ['Class', 'menu_callback'],
     *                'ui'          => [], // pods_ui definition
     *                'form'        => [],
     * 			      'icon' 		=> '',
     * 			      'position' 	=> null,
     * 			      'parent' 		=> null,
     * 			      'remove' 		=> false,
     * 			      'submenu' 		=> [] // submenus defined as above
     *     		]
     *     )
     *   
     * Most of this options are optional. Usually we'll use just `callback` or `ui`
	 *
	 * An example `ui` definition would be as follows:
	 * 
	 * 		array(
	 *			'pod' => 'convite_lote',
	 *			'actions_custom' => array(
	 *				'exportar' => []
	 *			),
	 *		);
	 *		
     */
    public static function init_admin_menu()
    {
        // Call the admin menu of pw_type classes
        $cls_menus = PWrapper::component_call( 'admin_menu', false );

        // loop returned classes menus and add them to wp
        foreach ($cls_menus as $class => $menus)
        {
            foreach ($menus as $top_slug => $top_options)
            {
                // add the menu to wp and store the options
                self::add($top_slug, $top_options, 'top');
                self::$menus[$top_slug]['class'] = $class;

                foreach ($top_options['submenu'] as $sub_slug => $sub_options)
                {
                    // add the menu to wp and store the options
                    self::add($sub_slug, $sub_options, 'submenu', $top_slug);
                    self::$menus[$sub_slug]['class'] = $class;
                }
            }
        }

    }

    public static function add($slug, $options, $level='top', $parent='edit.php')
    {
        // menu options defaults apply
        $menu = array_merge(array(
            'slug'  => $slug,
            'title' => String_Helper::humanize($slug),
            'menu'  => String_Helper::humanize($slug),
            'capability' => (isset(self::$menus[$parent])) ? self::$menus[$parent]['capability'] : 'manage_options',
            //'callback' => ['PW_Admin_Menu', 'callback'],
            'callback'  => null,
            'ui'        => null,
            'form'      => null,
            'icon'      => '',
            'position'  => null,
            'parent'    => $parent,
            'remove'    => false,
        ), $options);

        if ($menu['ui'])
            // init the menu ui params
            self::init_admin_menu_ui($menu);
        if ($menu['form'])
            // init the menu form params
            self::init_admin_menu_form($menu);

        //todo: review this array to avoid name conflicts
        self::$menus[$slug] = $menu;

        if (!$menu['remove'])
        {
            // check if already existent (adding submenu to a existent top level)
            if (self::exists($slug, $level, $parent) && $level=='top')
                return false;

            if ($level=='top')
                return add_menu_page( $menu['title'], $menu['menu'], $menu['capability'],
                                    $slug, $menu['callback'], $menu['icon'], $menu['position'] );
            else
                return add_submenu_page( $menu['parent'], $menu['title'], $menu['menu'],
                                    $menu['capability'], $slug, $menu['callback']); 
        }
        // remove menus
        else
        {
            if ($level=='top')
                return remove_menu_page( $slug );
            else
                return remove_submenu_page( $menu['parent'], $slug );
        }


    }

    static function callback()
    {
    	//WIP: not done maybe not necessary
        global $plugin_page;

        if (array_key_exists($plugin_page, self::$menus))
        {
            $menu = self::$menus[$plugin_page];
        }
    }

    static function exists($slug, $level='top', $parent=null)
    {
        if ($level == 'top')
        {
            global $menu;
            return in_array( $slug, wp_list_pluck($menu, 2) );
        }
        else // submenu
        {
            global $submenu;
            return in_array($slug, wp_list_pluck($submenu[$parent], 2)
            );
        }
    }

    // init admin menu PodsUI default params
    static function init_admin_menu_ui( &$admin_menu )
    {
        if (array_key_exists('ui', $admin_menu))
        {
            // menu with _ui_ has the admin menu callback
            $admin_menu['callback'] = ['PW_Admin_Menu', 'ui_callback'];

            // ui defaults for the admin menu 
            $admin_menu['ui'] = array_merge(array(
                'pod' => '',
                'actions_custom' => array(),
            ), $admin_menu['ui']);

            // custom actions defaults for the menu item
            if ( !empty($admin_menu['ui']['actions_custom']) )
            {
                foreach ($admin_menu['ui']['actions_custom'] as $action => &$options)
                {
                    $defaults = array(
                        'more_args' => true,
                        'callback' => ['PW_Admin_Menu', 'ui_custom_action_callback'],
                        'link' => null,
                        'show_action' => 'manage',
                    );
                    $options = array_merge($defaults, $options);

                    add_filter("pods_ui_action_$action", array('PW_Pods_Ui', 'action_callback'), 10, 5);
                }
            }
        }
    }
    // Callback to admin menus which will render PodsUI
    static function ui_callback()
    {
        global $plugin_page;

        if (array_key_exists($plugin_page, self::$menus))
        {
            $ui = self::$menus[$plugin_page]['ui'];
            self::ui_display($ui);
        }
    }


    // Load the PodsUI
    static function ui_display($params)
    {
        $pod = pods( $params['pod'] );
        unset($params['pod']);

        $pod->ui( $params, true );
    }

    // default render callback for custom actions which use show_action option if present
    static function ui_custom_action_callback($podsui, $row, $id)
    {
        global $plugin_page;

        if (array_key_exists($plugin_page, self::$menus))
        {
            $custom_action = $podsui->actions_custom[ $podsui->action ];
            $ui = self::$menus[$plugin_page]['ui'];

            if ( !empty($custom_action['show_action']) )
            {
                $ui['action'] = $custom_action['show_action'];
                self::ui_display($ui);
            }
        }
    }

    static function init_admin_menu_form( &$admin_menu )
    {
        if (array_key_exists('form', $admin_menu) && defined( 'FM_VERSION' ))
        {
            // PW fields inclusion
            include_once dirname(__FILE__) . '/fieldmanager/pw-fieldmanager-paragraph.php';

            $admin_menu['callback'] = ['PW_Admin_Menu', 'form_callback'];

            // submenu defaults
            $admin_menu['form'] = array_merge(array(
                'name'  => $admin_menu['slug'],
                'label' => String_Helper::humanize($admin_menu['slug']),
                'children' => array(),
                // PW fieldmanager custom options
                'submit'     => 'Save Options',
                'capability' => $admin_menu['capability'],
            ), $admin_menu['form']);

            // children elements defaults
            foreach ($admin_menu['form']['children'] as $name => $opts)
            {
                if (is_string($opts))
                    $opts = ['type'=>$opts];

                // default options for children elements
                $opts = array_merge([
                    'name'  => $name,
                    'label' => String_Helper::humanize( $name ),
                    'type'  => 'textfield',
                ], $opts);


                $admin_menu['form']['children'][$name] = $opts;
            }

        }
    }

    static function form_callback()
    {
        global $plugin_page;

        if (array_key_exists($plugin_page, self::$menus))
        {
            $form = self::$menus[$plugin_page]['form'];

            self::form_display($form);
        }
    }

    static function form_display($form)
    {
        $menu = self::form_menu_object($form);
        $menu->render_submenu_page();
    }

    static function form_menu_object($form)
    {
        foreach ($form['children'] as $key => $opts)
        {
            $type = ucwords($opts['type']);
            unset($opts['type']);

            try {
                $class = new ReflectionClass("Fieldmanager_{$type}");
            } catch (Exception $e) {
                $class = new ReflectionClass("PW_Fieldmanager_{$type}");
            }

            $field = $class->newInstanceArgs( [$opts] );
            $form['children'][$key] = $field;
        }

        $submit = $form['submit'];
        $capability = $form['capability'];
        unset($form['submit'], $form['capability']);

        $fm = new Fieldmanager_Group( $form );

        $menu = new Fieldmanager_Context_Submenu( '', $form['label'], null, $capability, $form['name'], $fm, True );
        $menu->submit_button_label = $submit;

        WordPress_Helper::prevent_action( 'admin_init', array( $menu, 'handle_submenu_save' ) );

        return $menu;
    }

    static function form_post_callback()
    {
        global $plugin_page;

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && array_key_exists($plugin_page, self::$menus))
        {
            $menu = self::$menus[$plugin_page];
            $action = String_Helper::methodize($menu['form']['name']);

            // call class method slug_post
            if (method_exists($menu['class'], "{$action}_form_post"))
            {
                if( !wp_verify_nonce( $_POST['fieldmanager-' . $menu['form']['name'] . '-nonce'], 'fieldmanager-save-' . $menu['form']['name'] ) ) {
                    pods_error( 'Nonce validation failed' );
                }

                // post data for this form
                $data = $_POST[ $menu['form']['name'] ];

                // preprocess (validate) the data
                $form = self::form_menu_object($menu['form']);
                $data = $form->fm->presave_all($data, null);

                // callback for this action post
                call_user_func([$menu['class'], "{$action}_form_post"], $data);
            }
            // if not found, save to wp_options by default
            else
            {
                $menu = self::form_menu_object($menu['form']);
                $menu->handle_submenu_save();
            }

        }
    }

}