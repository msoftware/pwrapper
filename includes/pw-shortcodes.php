<?php

class PW_Shortcodes extends PW_Module {

    static $shortcodes = array();
    static $shortcodes_post = array();

    public static function init_hooks()
    {
        add_action( 'template_redirect', ['PW_Shortcodes', 'post_callback'], 1);
    }

    /**
     * Inits the PW_Module`s _shortcode methods. Register their actions callbacks.
     * Every class method ending with `_shortcode` is registred.
     * @return void
     */
    public static function shortcodes_init()
    {
        foreach (Pods_Wrapper::classes_methods(['_shortcode', '_shortcode_post']) as $cls => $methods)
        {
            foreach ($methods as $method)
            {
                if ( substr($method, -strlen('_post')) == '_post' )
                {
                    self::$shortcodes_post[] = str_replace('_post', '', $method);
                    continue;
                }

                $action = str_replace('_shortcode', '', $method);

                if ( array_key_exists($action, self::$shortcodes) )
                    pods_error("Shortcode action name already registred ($cls->$method).");

                add_shortcode( $action, array( 'PW_Shortcodes', 'callback' ) );

                self::$shortcodes[$action] = [$cls, $method];
            }
        }
    }

    /**
     * Wrapper Callback for the _shortcode registred methods.
     * @param  [type]   $atts    [description]
     * @param  string   $content [description]
     * @param  [type]   $action  [description]
     * @return [string]          The output/return of the registred shortcode method
     */
    public static function callback($atts, $content="", $action)
    {
        // determine the class and method based
        $method = self::$shortcodes[$action];

        ob_start();

        // call class the method
        $return = call_user_func($method, $atts, $content);

        $output = ob_get_contents();
        ob_end_clean();

        if ($return)
            return $return;
        else
            return $output;
    }

    /**
     * Wrapper callback for the shortcode_post methods
     * @return void
     */
    function post_callback()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            global $post;
            if (!empty($post->post_content))
            {
                //todo: fit this regex to have only our shortcodes
                $regex = get_shortcode_regex();

                preg_match_all('/'.$regex.'/',$post->post_content, $matches);

                // check for the shortcode in the post content
                foreach (self::$shortcodes as $action => $method)
                    if ( !empty($matches[2]) && in_array($action, $matches[2]) )
                        if (method_exists($method[0], "$method[1]_post"))
                            // call the post method of the shortcode
                            call_user_func([$method[0], "$method[1]_post"], $atts, $content);
            }

        }
    }

}
