<?php

// QueryPath include
//require_once dirname(__FILE__) .'/../lib/querypath/src/qp.php';
// todo: refactor into modules

class PW_Admin extends PW_Module {

    public static $metaboxes = array();

    public static function init_hooks()
    {
        # Meta Boxes Hook (wp)
        //add_action('add_meta_boxes', array('PW_Admin', 'meta_boxes'));

    }

    // unfinished
    public static function init_meta_boxes()
    {
        $boxes = self::call_path( 'includes', 'meta_box', 'pw_module', false );

        foreach ($boxes as $cls => $cls_boxes) {
            // todo: self::meta_box_add()
            $type = $cls::$type;
            $pod = pods($type);

            foreach ($cls_boxes as $name => $options)
            {
                $default = array(
                    '_object_types' => $type,
                    'callback' => 'false',
                    );
                self::$metaboxes[$name] = redrokk_metabox_class::getInstance($name, $options);
            }
        }
    }

    // unfinished
    public static function meta_box_add($class) {
        if (method_exists($class, "meta_boxes"))
        {
            $type = $class::$type;
            $pod = pods($type);
            $boxes = $class::meta_boxes();

            //switch ($pod->pod_data['storage']) {

            $default = array(
                '_object_types' => $type,
                'callback' => 'false',
                );
            //self::$metaboxes[$name] = redrokk_metabox_class::getInstance($name, $options);
        }
    }


}

class Pods_Admin
{
    // unfinished work which aim to bring metabox addition to pods/wp based on the class meta_boxes method
    public static function add_meta_boxes($id, $title, $callback, $post_type, $context,
         $priority, $callback_args)
    {

        // create the meta box container
        $box = qp('<div>')->attr('id', $id)->addClass('postbox');

        // append toggle to the box
        qp('<div class="handlediv" title="Click to toggle"><br></div>')->appendTo($box);

        // create the title tag
        qp('<h3 class="hndle"><span>'. $title .'</span></h3>')->appendTo($box);

        // box content
        $content = '';
        qp('<div class="inside">'. $content .'</div>')->appendTo($box);

        switch ($context) {
            case 'side':
                $box->appendTo('#side-sortables');
                break;
            
            default:
                # code...
                break;
        }
    }
}
