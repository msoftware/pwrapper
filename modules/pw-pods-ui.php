<?php

class PW_Pods_Ui extends PW_Module {

    private static $default_actions = array('edit', 'add', 'duplicate', 'view', 'manage');

    public static function init_hooks()
    {
        # Pods Page Actions Hooks (ui menus)
        foreach (self::$default_actions as $action)
            add_filter("pods_ui_action_$action", array('PW_Pods_Ui', 'action_callback'), 10, 5);
    }

    // Wrapper which allow pod classes to change rendering of PodsUI actions
    // also fires custom action method in pod class
    // Actions: edit, add, duplicate, manage, view and actions_custom array
    public static function action_callback()
    {
        // get define params
        $params = func_get_args();
        // fix for pods issue #2116
        //$params = array_slice($params, 1);

        $action = str_replace('pods_ui_action_', '', current_filter());
        //$podsui = array_slice($params, -1, 1)[0];
        $podsui = $params[count($params)-1];
        $type = (is_object($podsui->pod)) ? $podsui->pod->pod : $podsui->pod;

        // look for a class that manages this pod and call its action method, if existent
        if ($class = PW_Types::post_type_class($type))
        {
            if (method_exists($class, "{$action}_action"))
                $method = [$class, "{$action}_action"];
            elseif (method_exists($class, "{$type}_{$action}_action"))
                $method = [$class, "{$type}_{$action}_action"];
            else
                return false;


            remove_filter("pods_ui_action_$action", array('PW_Pods_Ui', 'action_callback'), 10, 5);

            // Call the class method for the action
            $result = call_user_func_array($method, $params);
            
            add_filter("pods_ui_action_$action", array('PW_Pods_Ui', 'action_callback'), 10, 5);

            return $result;
        }

        return false;
    }

}