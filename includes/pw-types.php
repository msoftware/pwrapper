<?php

/*
* Post Types Manager
*/
class PW_Types extends PW_Module {

    /**
     * List of registred types with its class
     * @var array
     */
    public static $types = array();
    
    /**
     * Array of hooks and actions to look in PW_Module classes
     * Structure: action_name => [ hook_tag, num_params, [priority, [callback ]
     * @var array
     */
    public static $hooks = array(
        'save_validate' => array('pods_api_pre_save_pod_item', 2, 9, ['PW_Types','validate_callback']),
        'save_before' => array('pods_api_pre_save_pod_item', 2),
        //'pre_create' => array('pods_api_pre_create_pod_item', 2),
        //'pre_edit' => array('pods_api_pre_edit_pod_item', 2),
        'save_after' => array('pods_api_post_save_pod_item', 3),
        //'post_create' => array('pods_api_post_create_pod_item', 3),
        //'post_edit' => array('pods_api_post_edit_pod_item', 3),
        'delete_before' => array('pods_api_pre_delete_pod_item', 2),
        'delete_after' => array('pods_api_post_delete_pod_item', 2),
        );

    public static function init_hooks() {
        /*# Type After-Save
        add_action( 'save_post', array( 'PW_Types', 'after_save' ) );
        # Type Before Save
        add_filter( 'wp_insert_post_data', array('PW_Types', 'before_save'), '99', 2 );
        # Query Posts Filter
        add_filter( 'pre_get_posts', array( 'PW_Types', 'before_query' ) , 20, 1 );*/
        # Runs with sql clauses formed. Allow rebuild them.
        //add_action( 'posts_clauses', array( 'PW_Types', 'before_query_sql' ) );

    }

    /**
     * Initialize Pods data hooks based on child classes methods name
     * @return void
     */
    public static function types_init()
    {
        // class methods managing actions in types
        $actions = array_keys(self::$hooks);
        $action_methods = PWrapper::classes_methods($actions);
        $actions_regex = implode('|', $actions);

        foreach ($action_methods as $class => $methods)
        {
            foreach ($methods as $method)
            {
                // match the type method is managing
                preg_match("/^(.*_?)($actions_regex)$/", $method, $mt);
                $action = $mt[2];
                if (!empty($mt[1]))
                    $type = substr($mt[1], 0, -1);
                elseif (isset($class::$type))
                    $type = $class::$type;
                else
                    pods_error("PW_Type: No type defined for method $class::$method");

                // pods hook
                $hook = self::$hooks[$action];
                $tag = "$hook[0]_$type";

                $priority = (count($hook) > 1) ? $hook[2] : 10;
                $accepted_args = (count($hook) > 0) ? $hook[1] : 0;

                $callback = (count($hook) > 2) ? $hook[3] : array('PW_Types', 'action_callback');
                $params = [$type, $class, $method, $tag];

                // register the action callback
                Callback_Helper::add_filter($tag, $callback, $params, $priority, $accepted_args);

                // stores the types/pods each class manage
                self::$types[$class] = $type;
            }
        }
    }

    // default callback for pods api hooks
    static function action_callback()
    {
        $args = func_get_args();
        $params = array_pop($args);
        $type   = $params[0];
        $class  = $params[1];
        $method = $params[2];

        return call_user_func_array(array($class, $method), $args);
    }

    static function validate_callback()
    {
        $args = func_get_args();
        $params = array_pop($args);
        $type   = $params[0];
        $class  = $params[1];
        $method = $params[2];
        $pieces = $args[0];

        $current = pods(
            $pieces['params']->pod,
            $pieces['params']->id
        );

        if (method_exists($class, $method))
        {
            $data = self::pieces_data($pieces, $current);

            // call user validate method
            $data = call_user_func(array($class, $method), $data, $current);

            $pieces = self::pieces_data($data, $pieces);
        }
        
        return $pieces;
    }

    static function pieces_data($object, $object2=false)
    {
        if (isset($object['pod'])) //converting pieces ($object) into $data
        {
            // prepare data array
            $data = array();

            $fields = $object['pod']['fields'];

            foreach ( $fields as $name => $field )
                if (in_array($name, $object['fields_active']))
                    $data[$name] = $object['fields'][$name]['value'];
                elseif ($object2)
                    $data[$name] = $object2->field($name);
                else
                    $data[$name] = null;

            // foreach ( $current->pod_data['object_fields'] as $name => $field )
            //     if (in_array($name, $pieces['fields_active']))
            //         $data[$name] = $pieces['object_fields'][$name]['value'];
            //     else
            //         $data[$name] = $current->field($name);

            // todo: add also object_fields and custom_fields. create the inverse for them

            return $data;
        }
        else //updating pieces ($object2) from data ($object)
        {
            // repopulate $pieces params
            foreach ($object as $name => $value)
            {
                if ( !isset($object2['fields'][$name]['value'])
                   || $value != $object2['fields'][$name]['value'])
                {
                    $object2['fields'][$name]['value'] = $value;

                    // if value changed, check if exists in fields_active
                    if ( !in_array($name, $object2['fields_active'])
                       && array_key_exists($name, $object2['pod']['fields']))
                        $object2['fields_active'][] = $name;
                }
            }
            // foreach ( $current->pod_data['object_fields'] as $name => $field )
            //     $pieces['object_fields'][$name]['value'] = $data[$name];
            // dados que sobrarem vao para custom_fields/custom_data

            return $object2;
        }
    }

    /*
    Pods already have the capabilities for types. Wp too.
    TODO: make init_types call a single wrapper and let it check for capabilities, etc..

    public static function capabilities()
    {
        $actions = ['edit', 'add', 'view', 'delete', 'manage'];
        $capabilities = array();

        foreach (self::$types as $class => $type)
        {
            $role = $class::$role;

            // add capabilities for default actions
            foreach ($actions as $action)
                $capabilities[$role][] = "pw_${action}_$type";
        }

        return $capabilities;
    }*/

    /*
    * Returns the class name of the given post_type
    * The class which manages (registred) the post_type
     */
    public static function post_type_class($type) {
        return array_search($type, self::$types);
    }
}