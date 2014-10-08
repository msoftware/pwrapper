<?php
require_once dirname(__FILE__) .'/../lib/h2o-php/h2o.php';

class PW_Template extends PW_Module
{
    /*TEMPLATE SYSTEM*/
    static $context_default = array();
    static $options_default = array();

    static function init()
    {
        // default context
        $context = array(
            'stylesheet_directory_uri' => get_stylesheet_directory_uri(),
            'ajaxurl' => admin_url('admin-ajax.php'),
        );
        self::$context_default = array_merge($context, self::$context_default);

        // default options
        $options = array(
            'searchpath'    =>  array(),
            'autoescape'    =>  true,
            );
        self::$options_default = array_merge($options, self::$options_default);

        // addon filters and tags
        H2o::addTag(['call', 'request', 'do_shortcode']);
        H2o::addFilter(['PW_H2o_Filters']);
    }

    static function init_theme()
    {
        foreach (PWrapper::$components as $component => $options)
            self::$options_default['searchpath'][] = $options['pwrapper'] .'/templates/';
        // todo: check hierarchy...
    }

    /**
     * Shortcode to render a template
     * Usage:
     *     [template_render file="app/banner.html" section="home"]
     *     
     * @param  [type] $atts    [description]
     * @param  string $content [description]
     * @return [type]          [description]
     */
    static function template_render_shortcode($atts, $content='')
    {
        // file parameter render
        $file = $atts['file'];
        unset($atts['file']);

        // data_ parameters passed to context
        $data = array();
        foreach ($atts as $key => $value)
            if (stripos($key, "data_") === 0) {
                $field = str_replace('data_', '', $key);
                $data[$field] = $value;
                unset($atts[$key]);
            }

        // content is on context also
        if (!empty($content))
            $data['content'] = $content;

        return self::render($file, $data, $atts);

        //todo: allow pod loading, query
    }

    static function render($file, $context=array(), $options=array())
    {
        $file = self::file_extension_check($file);

        $options = array_merge(self::$options_default, $options);
        $context = array_merge(self::$context_default, $context);

        if (substr($file, -4) == '.php') {
            extract($context);
            ob_start();
        }
        else {
            $template = new H2o($file, $options);
            return $template->render($context);
        }
    }

    static function file_extension_check($file)
    {
        $extension = @pathinfo($file, PATHINFO_EXTENSION)['extension'];
        
        if (empty($extension))
            $file .= '.html';

        return $file;
    }

    static function render_ajax($file, $context=array())
    {
        if (!is_array($context))
            $context = [];

        return self::render($file, $context);
    }
}

// Call a php method (method, [class, params...)
class Call_Tag extends H2o_Node
{
    private $args = array();

    function __construct($argstring, $parser, $pos=0)
    {
        $this->args = h2o_parser::parseArguments($argstring);
    }

    function render($context, $stream)
    {

        $method = $context->resolve($this->args[0]);
        $class  = $context->resolve( ($this->args[1]) ? $this->args[1] : false );

        $params = array();
        foreach (array_slice($this->args, 2) as $param)
            $params[] = $context->resolve($param);

        // first param is not a class, but a method param
        if (!is_callable([$class, $method])) {
            array_unshift($params, $class);
            $class = false;
        }

        $return = self::call_method($method, $class, $params);
        $stream->write($return);
    }

    static function call_method($method, $class=false, $params=array())
    {
        if ($class)
            $method = [$class, $method];

        if (is_callable($method))
        {
            ob_start();

            $return = call_user_func_array($method, $params);

            $output = ob_get_contents();
            ob_end_clean();

            if ($return && $return !== true)
                $output = $return;

            return $output;
        }
    }
}

// Echo a request param (get, post)
class Request_Tag extends H2o_Node
{
    private $args = array();

    function __construct($argstring, $parser, $pos=0)
    {
        $this->args = h2o_parser::parseArguments($argstring);
    }

    function render($context, $stream)
    {
        $varname = $context->resolve($this->args[0]);
        $method  = $context->resolve( ($this->args[1]) ? $this->args[1] : false );

        switch (strtolower($method)) {
            case 'get':
                $value = $_GET[$varname];
            case 'post':
                $value = $_POST[$varname];
            default:
                $value = $_REQUEST[$varname];
        }

        $stream->write($value);
    }
}

// Do a Shortcode
class Do_Shortcode_Tag extends H2o_Node
{
    private $args = array();

    function __construct($argstring, $parser, $pos=0)
    {
        $this->args = h2o_parser::parseArguments($argstring);
    }

    function render($context, $stream)
    {
        $content = $context->resolve($this->args[0]);

        $result = do_shortcode( $content );

        $stream->write($result);
    }
}

class PW_H2o_Filters extends FilterCollection
{
    static function get_uri($path, $args=null)
    {
        $uri = WordPress_Helper::get_link($path, $args);
        return $uri;
    }

    static function date_timestamp($date)
    {
        return (new DateTime($date))->getTimestamp();
    }
}