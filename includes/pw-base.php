<?php
class PW_Module {

    /**
     * Called on plugin inclusion, should be used to initiate the class in the scope.
     */
    public static function _construct() {
        return true;
    }

    /**
     * Executed on wordpress initiation (after _construct)
     */
    public static function init() {
        return false;
    }

    /**
     * Executed on plugin initiation (after init) the method objective is
     * to prepare wordpress hooks for this type instance
     */
    public static function init_hooks() {
        return false;
    }

    /**
     * Action used for registering module script/style dependencies
     * Run at  *admin_enqueue_scripts* and *wp_enqueue_scripts*
     */
    public static function register_scripts() {
        return false;
    }

    /*
    Methods which creates ajax handlers
    
    public static function myaction_ajax()
    public static function myaction_ajax_public()
    */

    /*
    Hooks for rendering ui page actions

    public static function action_add($_null, $podsui)
    public static function action_edit($_null, $duplicate, $podsui)
    public static function action_duplicate($_null, $podsui)
    public static function action_manage($_null, $reorder, $podsui)
    public static function action_view($_null, $podsui)

    public static function action_custom($_null, $id, $row, $podsui)
    */
   
    /*
    Capabilities creation

    static function capabilities(){
        return array(
            'role_name' => 'capabilities_to_add',
        );
    }

    */
}

class PW_Type extends PW_Module {

    // type/pod names which this class manages
    public static $type = 'post';

    // default role which can manage this type
    public static $role = 'administrator';


    /*
    Methods signatures that can be implemented to manage types
    
    public static function post_save($pieces, $is_new_item, $id)
    public static function pre_save($pieces, $is_new_item)
    public static function pre_delete($params, $pod)
    public static function post_delete($params, $pod)

    // this method receives is called before pre_save, but receives the data in a smoother format ($data[field] = value)
    public static function validate($data, $is_new_item)
    */

    
}