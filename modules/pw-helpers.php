<?php

class String_Helper
{
    static function simple_uuid($len=8)
    {
        /*$id = 100;
        base_convert($id, 10, 36);*/

        $uid = sha1(microtime(true).mt_rand(10000,90000));
        return substr($uid, 0, $len);
    }

    static function humanize($str, $separator='_-')
    {
        $str = trim(function_exists('mb_strtolower') ? mb_strtolower($str) : strtolower($str));
        $str = preg_replace('/['.$separator.']+/', ' ', $str);
        return ucwords($str);
    }
    
    static function hyphenize ($string) {
        $rules = array('/[^\w\s-]+/'=>'','/\s+/'=>'-', '/-{2,}/'=>'-');
        $string = preg_replace(array_keys($rules), $rules, trim($string));
        return $string = trim(strtolower($string));
    }

    static function methodize($string) {
        $string = self::hyphenize($string);
        $string = str_replace('-', '_', $string);
        return $string;
    }

    // return the numbers of a string
    static function numbers($string) {
        $string = filter_var($string, FILTER_SANITIZE_NUMBER_INT);
        return str_replace(array('+','-'), '', $string);
        //return preg_replace('/[^0-9]/', '', $string);
    }

    // convert a string to float
    static function floatize($string)
    {
        $locale = localeconv();
        
        $string = str_replace($locale['thousands_sep'], '', $string);
        $string = str_replace($locale['decimal_point'], '.', $string);

        return $string;
    }

    static function random ($len, $chars = 'abcdefghijklmnopqrstuvwxyz1234567890')
    {
        for (
            $s='', $cl=strlen($chars)-1, $i=0;
            $i < $len;
            $s .= $chars[mt_rand(0, $cl)], ++$i
        );
        return $s;
    }
}

class File_Helper {

    static function upload($name, $data)
    {
        if ( !( ( $uploads = wp_upload_dir( current_time( 'mysql' ) ) ) && false === $uploads[ 'error' ] ) )
            return PW_Error::die_error( __( 'There was an issue saving the file in your uploads folder.', 'pwrapper' ), true );

        // Generate unique file name
        $filename = wp_unique_filename( $uploads[ 'path' ], $name );

        $extension = preg_match('/.*(\..*)/', $name)[1];

        // move the file to the uploads dir
        $new_file = $uploads[ 'path' ] . '/' . $filename;


        file_put_contents( $new_file, $data );


        // Set correct file permissions
        $stat = stat( dirname( $new_file ) );
        $perms = $stat[ 'mode' ] & 0000666;
        @chmod( $new_file, $perms );

        // Get the file type
        $wp_filetype = wp_check_filetype( $filename );

        // construct the attachment array
        $attachment = array(
            'post_mime_type' => ( !$wp_filetype[ 'type' ] ? 'text/' . $extension : $wp_filetype[ 'type' ] ),
            'guid' => $uploads[ 'url' ] . '/' . $filename,
            'post_parent' => null,
            'post_title' => 'Pods Wrapper File (' . $name . ')',
            'post_content' => '',
            'post_status' => 'private'
        );

        // insert attachment
        $attachment_id = wp_insert_attachment( $attachment, $new_file );

        // error!
        if ( is_wp_error( $attachment_id ) )
            return PW_Error::die_error( __( 'There was an issue saving the file in your uploads folder.', 'pwrapper' ), true );

        return $attachment[ 'guid' ];
    }
    static function file_get_contents_utf_ansi($filename, $defAnsiEnc = 'Windows-1251')
    {
        $buf = file_get_contents($filename);
     
        if      (substr($buf, 0, 3) == "\xEF\xBB\xBF")          return substr($buf,3);
        else if (substr($buf, 0, 2) == "\xFE\xFF")              return mb_convert_encoding(substr($buf, 2), 'UTF-8', 'UTF-16BE');
        else if (substr($buf, 0, 2) == "\xFF\xFE")              return mb_convert_encoding(substr($buf, 2), 'UTF-8', 'UTF-16LE');
        else if (substr($buf, 0, 4) == "\x00\x00\xFE\xFF")      return mb_convert_encoding(substr($buf, 4), 'UTF-8', 'UTF-32BE');
        else if (substr($buf, 0, 4) == "\xFF\xFE\x00\x00")      return mb_convert_encoding(substr($buf, 4), 'UTF-8', 'UTF-32LE');
        else if (mb_detect_encoding(trim($buf), $defAnsiEnc)
            || utf8_encode(utf8_decode($buf)) != $buf)          return mb_convert_encoding($buf, 'UTF-8', $defAnsiEnc);
        else                                                    return $buf;
    }

    public static function directory_list($start = '.', $path = false, $extension = false) 
    {
        $files = array();

        if (is_dir($start)) 
        {
          $file_handle = opendir($start);

          while (($file = readdir($file_handle)) !== false) 
          {
            if ($file != '.' && $file != '..' && strlen($file) > 2) 
            {
              if (strcmp($file, '.') == 0 || strcmp($file, '..') == 0) 
              {
                continue;
              }

              // private (hidden) files
              if ($file[0] == '.' || $file[0] == '_')
                continue;

              $file_parts = explode('.', $file);
              $_file = $extension ? $file : $file_parts[0];
              $file_path = $path ? $start . '/' . $_file : $_file;

              if (is_dir($file_path)) 
                $files = array_merge($files, self::get_directory_list($file_path));
              else 
                array_push($files, $file);
            }
          }

          closedir($file_handle);
        } 
        else 
        {
          $files = array();
        }

        return $files;
    }
}

class Http_Helper
{
    static $next_header = false;

    static function current_url()
    {
        global $wp;
        $current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
        return $current_url;
    }

    static function header_guess(&$content)
    {
        $header = false;

        if (!self::$next_header)
        {
            if (is_string($content))
            {
                // JSON
                json_decode($content);
                if (json_last_error() == JSON_ERROR_NONE)
                    $header = 'json';

                // XML
                if (!$header) {
                    try {
                        new DOMElement($content);
                        $header = 'xml';
                    } catch(DOMException $e) {
                    }
                }
            }
            elseif (is_object($content) || is_array($content))
            {
                // Object to Json
                $json = json_encode($content);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $header = 'json';
                    $content = $json;
                }
            }
        } else {
            $header = self::$next_header;
            self::$next_header = false;
        }


        switch ($header) {
            case 'json':
                header('Content-Type: application/json');
                break;
            case 'xml':
                header("Content-type: text/xml");
                break;
        }
    }
    
    // determine current request protocol (http/https)
    static function protocol()
    {
        $isSecure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $isSecure = true;
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $isSecure = true;
        }

        return $isSecure ? 'https' : 'http';
    }

    // retorna dados do acesso corrente
    static function information()
    {
        return array(
            'ip'        => $_SERVER['REMOTE_ADDR'],
            'referer'   => @$_SERVER['HTTP_REFERER'],
            'uri'       => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'scheme'    => self::protocol(),
            'agent'     => $_SERVER['HTTP_USER_AGENT'],
            'time'      => $_SERVER['REQUEST_TIME'],
            'server'    => $_SERVER['SERVER_NAME'],
        );
    }
}


class WordPress_Helper
{
    static $prevent_filters = [];

    // Previne que um action/filter seja acionado no futuro (mesmo que registrado depois)
    static function prevent_action($tag, $function_to_remove='all', $priority = 10)
    {
        $idx = _wp_filter_build_unique_id($tag, $function_to_remove, $priority);
        
        self::$prevent_filters[$tag][$priority][$idx] = array('function' => $function_to_remove);

        add_filter($tag, ['WordPress_Helper','prevent_action_callback'], 0);

        return true;
    }

    static function prevent_action_callback()
    {
        $tag = current_filter();
        $prevent = self::$prevent_filters[$tag];
        global $wp_filter, $merged_filters;

        $remove = array();

        foreach ($prevent as $priority => $stack)
        {
            foreach ($stack as $idx => $action)
                // all from tag
                if ($action['function'] == 'all' && $priority == 0)
                {
                    foreach ($wp_filter[$tag] as $fpriority => $fstack)
                        foreach ($fstack as $fidx => $faction)
                            $remove[] = [$tag, $fpriority, $fidx];
                    break;
                }
                // all from tag priority
                elseif ($action['function'] == 'all')
                {
                    foreach ($wp_filter[$tag][$priority] as $fidx => $faction)
                        $remove[] = [$tag, $priority, $fidx];
                    continue;
                }
                // remove single function
                else
                    $remove[] = [$tag, $priority, $idx];
        }

        foreach ($remove as $signature)
            $wp_filter[$signature[0]][$signature[1]][$signature[2]]['function'] = null;

        unset($merged_filters[$tag]);
    }

    // allow given pages and restrict all other
    // receives allowed pages array and callback for matching passing condition
    static function restrict_access_allowing(array $allowed, $redirect='/', $callback='is_user_logged_in')
    {
        $params = [$allowed, $redirect, $callback];
        Callback_Helper::add_filter('template_redirect', ['WordPress_Helper', 'restrict_access_callback'], $params);
    }

    static function restrict_access_callback()
    {
        $params = array_pop(func_get_args());
        $pages = $params[0];
        $redirect = $params[1];
        $callback = $params[2];

        // restrict based on callback
        if (call_user_func($callback))
            return false;

        // restrict based on condition
        if (self::is_condition($pages))
            return false;

        if ($redirect == '/')
            $redirect = home_url();
        elseif (substr($redirect, 0, 1) == '/')
            $redirect = post_permalink( get_page_by_path($redirect) );

        wp_redirect( $redirect );
        exit;
    }


    /*
    Can only be used after posts_selection action hook
    https://codex.wordpress.org/Conditional_Tags
    TODO: sub-category, sub-category post
    */
    function is_condition($conditions) {
        // test boolean
        $t = false;
        
        foreach ($conditions as $key => $value) {
            // key only param (home)
            if (is_int($key)) {
                $key = $value;
                $value = null;
            }

            // multiple param (recursive) [todo: refactor]
            if (is_array($value))
                // prevent tax param (array) interpretation as multiple
                if ($key != 'tax' || is_array($value[0]))
                    // loop items and call the method
                    foreach ($value as $param) {
                        $t = self::is_condition( array($key=>$param) );
                        if ($t==true)
                            return $t;
                    }

            switch ($key) {
                case 'page':
                    $t = is_page( $value );

                    // Find if is child page
                    if (!$t) {
                        global $post;
                        $page = get_posts( array( 'name' => $value, 'post_type' => 'page' ) );

                        $t = ($page && is_page() && $post->post_parent == $page[0]->ID);
                    }
                    break;
                case 'home':
                    $t = is_home();
                    break;
                case 'type':
                    // single post type item
                    $t = (get_post_type() == $value);

                    // post type archive
                    if (!$t)
                        $t = is_post_type_archive( $value );
                    // todo: type taxonomy
                    break;
                case 'tax':
                    if (is_array($value)) {
                        $taxonomy = $value[0];
                        $term = $value[1];
                    } else {
                        $taxonomy = $value;
                        $term = null;
                    }

                    global $wp_query;

                    // taxonomy archives
                    if ($wp_query->is_tax && isset( $wp_query->query_vars[$taxonomy] ))
                    {
                        $tax_terms = self::term_hierarchy($taxonomy, $wp_query->query_vars[$taxonomy], null, 'slug');
                        if ($term)
                            $t = in_array($term, $tax_terms);
                        else
                            $t = (count($tax_terms) > 0);
                    }

                    // post terms
                    if (!$t && $wp_query->post) {
                        $post_terms = self::term_hierarchy($taxonomy, null, $wp_query->post->ID, 'slug');
                        if ($term)
                            $t = in_array($term, $post_terms);
                        else
                            $t = (count($post_terms) > 0);
                    }
                    break;
            }

            if ($t == true)
                return $t;
        }
        return $t;
    }

    /**
     * Returns a term (category) hierarchy (parent categories)
     * @param  string $taxonomy     The taxonomy to look for
     * @param  int|string $term     A term to look for parents
     * @param  int $post_id         To bring full taxonomy hierarchy of a post
     * @param  string $field        Return only this field of Taxonomy Object [term_id, name, slug...]
     * @return array                the term and its parents
     */
    static function term_hierarchy($taxonomy, $term=null, $post_id=null, $field = null)
    {
        if ($categories = Cache_Helper::transient() )
            return $categories;

        $categories = [];

        // Term taxonomy object
        if ($term)
        {
            if (is_numeric($term))
                $categories[] = get_term_by( 'id', $term, $taxonomy );
            if (is_string($term))
                $categories[] = get_term_by( 'slug', $term, $taxonomy );
        }

        // Grab categories of the post
        if ($post_id)
        {
            $post_cats = wp_get_object_terms($post_id, $taxonomy);

            foreach ($post_cats as $cat_obj)
                $categories[] = $cat_obj;
        }

        // Parent categories
        foreach ($categories as $cat_obj)
        {
            // loop tax parents till end
            while (! ($cat_obj = get_term($cat_obj->parent, $taxonomy)) instanceof WP_Error)
                $categories[] = $cat_obj;
        }

        // Filter return to selected field
        if ($field)
        {
            foreach ($categories as $i => $cat_obj)
                $categories[$i] = $cat_obj->$field;
        }

        Cache_Helper::transient( $categories );

        return $categories;
    }

    /**
    * gets the current post type in the WordPress Admin
    */
    function current_post_type() {
        global $post, $typenow, $current_screen;

        //we have a post so we can just get the post type from that
        if ( $post && $post->post_type )
            return $post->post_type;

        //check the global $typenow - set in admin.php
        elseif( $typenow )
            return $typenow;

        //check the global $current_screen object - set in sceen.php
        elseif( $current_screen && $current_screen->post_type )
            return $current_screen->post_type;

        //lastly check the post_type querystring
        elseif( isset( $_REQUEST['post_type'] ) )
            return sanitize_key( $_REQUEST['post_type'] );

        //we do not know the post type!
        return null;
    }

    /**
     * Gets the permalink and includes query arguments
     * @param  int|str  $path   post_id, page path, query-post
     * @param  arr      $args   named argumets to include in the link
     * @return str              the post link with arguments included
     */
    static function get_link($path, $args=null)
    {
        // By post_id
        if (is_numeric($path))
        {
            $link = get_permalink( $path );
        }
        // Page by Path
        else
        {
            if ($path == '/')
                $link = home_url( );
            else
                if ($page = get_page_by_path($path))
                    $link = post_permalink( $page );
        }

        if (!$link)
        {
            parse_str($path, $path_arr);

            // Taxonomy Term
            if (in_array( 'term', array_keys($path_arr) ))
                $link = get_term_link( $path_arr['term'], $path_arr['taxonomy'] );

            // Query Post
            else
                $link = get_permalink( get_posts($path)[0] );
        }

        if (is_array($args)) {
            $link = add_query_arg($args, $link);
        }

        return $link;
    }

} 

class Callback_Helper
{
    static $callbacks = array();

    /**
     * General porpose filter callback wrapper with param facilities.
	 *
     */
    static function add_filter($tags, $callback, $params=null, $priority=10, $accepted_args=null)
    {
        for (
            $key = String_Helper::random(8);
            array_key_exists($key, self::$callbacks);
        );
            
        if (!is_array($tags))
            $tags = [$tags];

        self::$callbacks[$key] = [$tags, $callback, $params];

        foreach ($tags as $tag)
            add_filter( $tag, ['Callback_Helper', "filter_callback_$key"], $priority, $accepted_args );

        return $key;
    }

    static function __callStatic($name, $arguments)
    {
		// grab key and call the callback
        preg_match('/filter_callback_(.+)/', $name, $mt);

        if (isset( $mt[1] ))
        {
            $key        = $mt[1];
            $callback   = self::$callbacks[ $key ][1];
            $params     = self::$callbacks[ $key ][2];

            array_push($arguments, $key, $params);

            return call_user_func_array($callback, $arguments);
        }
    }
}

class Session_Helper
{
    static $initialized = false;

    static function init()
    {
        if (!self::$initialized)
        {
            if( !session_id() )
                session_start();

            add_action('wp_logout', ['Session_Helper', 'end']);
            add_action('wp_login', ['Session_Helper', 'end']);

            self::$initialized = true;
        }
    }
    static function end()
    {
        session_destroy();
    }

    static function set($key, $value)
    {
        self::init();
        $_SESSION[$key] = $value;
    }
    static function get($key)
    {
        self::init();
        return $_SESSION[$key];
    }
}

/**
 * Generic Class for get/set WP cache data.
 * It is designed to cache whole method calls.
 * Make use of the method signature (class, name, args) to generate unique keys.
 * Object instances is not figured yet.
 */
class Cache_Helper
{
    static function cache($data=null, $expiration=2100, $group=null)
    {
        $cache_id = self::cache_id();

        if (is_null($group))
            $group = self::cache_group();

        if ($data)
            return wp_cache_set( $cache_id, $data, $group, $expiration );
        elseif ($data === false)
            return wp_cache_delete( $cache_id, $group );
        else
            return wp_cache_get( $cache_id, $group );
    }

    static function transient($data=null, $expiration=900)
    {
        $cache_id = self::cache_id(3, 45);

        if ($data)
            return set_transient( $cache_id, $data, $expiration );
        elseif ($data === false)
            return delete_transient( $cache_id );
        else
            return get_transient( $cache_id );
    }

    static function site_transient($data=null, $expiration=900)
    {
        $cache_id = self::cache_id(3, 45);

        if ($data)
            return set_site_transient( $cache_id, $data, $expiration );
        elseif ($data === false)
            return delete_site_transient( $cache_id );
        else
            return get_site_transient( $cache_id );
    }

    /**
     * generate a cache key based on trace information
     * @param  integer $deep   How far from cache_id() is the method cached. Usually 3 (cache_id, transient, mymethod)
     * @param  boolean $maxlen Max length of the key
     * @return string          A key based on trace
     */
    static function cache_id($deep=3, $maxlen=false)
    {
        // grab the method called
        $method = debug_backtrace(0, $deep)[$deep-1];
        $method = array_intersect_key( $method, array_flip(['function', 'class', 'type', 'args']) );

        // encode the array
        $method_hash = md5(json_encode($method));

        if ($maxlen)
            return substr($method_hash, 0, $maxlen);
        else
            return $method_hash;
    }

    static function cache_group($deep=3)
    {
        $trace = debug_backtrace(0, $deep)[$deep-1];
        
        if ($trace['class'])
            return String_Helper::hyphenize($trace['class']);
        else
            return String_Helper::hyphenize($trace['file']);

    }
}


class Error_Helper
{
    // default params for `print`, in order to receive unexpected messages (pods_bypass)
    static $type = '';
    static $action = '';

    //todo: bind/hook into pods_error to make their messages beter
    static function die_error($message, $type=null, $action=null)
    {
        if ($type === null)
            $type = self::$type;
        if ($action === null)
            $action = self::$action;

        $error = "<h1>Ops, ocorreu um erro</h1>
                    <p>$message</p>";

        switch ($type) {
            case 'back':
                if (!$action)
                    $action = 'javascript:history.back();';
                $error = $error ."<a href=\"$action\">Voltar</a>";
                break;
            default:
                //$error = $error;
                break;
        }

        if ( !defined( 'DOING_AJAX' ) )
        {
            $title = wp_title('', false);
            $title .= ' | '. get_bloginfo('name');

            wp_die( $error, $title, ['response'=>200] );
        }
        else
            die( "<e>$message</e>" );
    }

    static function pods_bypass($null, $message)
    {
        if (!is_admin())
        {
            self::die_error($message);
            return true;
        }
    }
}



trait PW_Singleton
{
    protected static $instance;

    final public static function getInstance()
    {
        return isset(static::$instance)
            ? static::$instance
            : static::$instance = new static;
    }

    final private function __wakeup() {}
    final private function __clone() {}    
}

/**
 * Singleton for static classes which depends on static methods but have inheritance
 */
trait PW_Static_Singleton
{
    protected static $class_name;
    
    final public static function getClass()
    {
        return isset(static::$class_name)
            ? static::$class_name
            : static::$class_name = get_called_class();
    }

    final public static function call($method)
    {
        $args = array_slice(func_get_args(), 1);

        return call_user_func_array([self::getClass(), $method], $arguments);
    }
}