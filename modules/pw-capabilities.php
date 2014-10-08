<?php

class PW_Capabilities extends PW_Module
{
    public static $capabilities = array();

    static function init_hooks()
    {
        //add_filter( 'user_has_cap', ['PW_Capabilities', 'superadmin_caps'], 10, 3 );
        add_action( 'admin_init', ['PW_Capabilities', 'init_capabilities'] );
    }

    /**
     * format:
     *     array(
     *         'rolename' => [
     *             'capabality_1',
     *             ['capabality_2', $allow_bool],
     *         ];
     *     )
     * @return [type] [description]
     */
    public static function init_capabilities()
    {
        if (Cache_Helper::cache())
            return true;

        foreach (PWrapper::classes_loaded('PW_Module') as $class)
        {
            // call class method to seek custom capabilities
            if (method_exists($class, 'capabilities'))
            {
                $capabilities = call_user_func(array($class, 'capabilities'));

                self::add($capabilities);

				self::$capabilities[$class] = $capabilities;
            }


            /*
            todo:
                - manage actions to require capability check (wrap)
                - allow user capability add?
            */
        }
        
        // cache the instance to run only once in a while
        Cache_Helper::cache(self::$capabilities);
    }

    public static function add(&$capabilities, $allow=True)
    {
        foreach ($capabilities as $role_name => &$caps)
        {
            if (!$role_target = get_role( $role_name ))
                $role_target = add_role($role_name, String_Helper::humanize($role_name));

            if (!is_array($caps))
                $caps = [$caps];

            foreach ($caps as $capability)
            {
                if (is_array($capability))
                { // allow array capability to disallow [capability, allow]
                    $cap = [$role_name => $capability[0]];
                    self::add( $cap, $capability[1] );
                }
                else
                { //add the capability to the role instance
                    $role_target->add_cap($capability, $allow);
                    get_role( 'administrator' )->add_cap($capability);
                }
            }

        }
    }


    // superadmin has all caps (not working for non multisite)
    static function superadmin_caps($allcaps, $caps, $args)
    {
        if (is_super_admin( $args[1] ))
            foreach ($caps as $c)
                if (in_array( $c, wp_list_pluck(self::$capabilities, 0) ))
                    $allcaps[$c] = True;

        return $allcaps;
    }


}