<?php

// todo: test and debug
//      status: alpha

class PW_Admin_Post_Filter extends PW_Module
{
    static $filters = [];

    public static function init_hooks()
    {
        // post filter query registrations
        add_action( 'init', ['PW_Admin_Post_Filter', 'query_var_callback'] );
        add_filter( 'parse_query', ['PW_Admin_Post_Filter', 'query_parse_callback'] );
    }

    public static function post_filter_init()
    {
        if (!is_admin())
            return false;
        
        $cls_filters = PWrapper::component_call( 'post_filter', false );
        foreach ($cls_filters as $class => $filters)
        {
            foreach ($filters as $name => $options)
            {
                $filter = array_merge(array(
                            'post_type' => (isset($class::$type)) ? $class::$type : null,
                            'itens'     => [],
                            'name'      => String_Helper::hyphenize($name),
                            'label'     => String_Helper::humanize($name),
                            'query'     => [],
                    ), $options
                );
                self::add($filter);
            }
        }
    }

    /**
    * Itens format: ['value'=>unique, 'label'=>name]
    **/
    public static function add($filter)
    {
        // register the print hook for the given post filter
        Callback_Helper::add_filter(
            'restrict_manage_posts',
            ['PW_Admin_Post_Filter', 'print_callback'],
            $filter
        );

        self::$filters[$filter['name']] = $filter;
    }

    public static function print_callback()
    {
        $filter = array_pop(func_get_args());

        if (is_admin() && WordPress_Helper::current_post_type() == $filter['post_type'])
        {
            echo "<select name='$filter[name]' id='$filter[name]' class='postform'>";
            echo "<option value=''>Show All $filter[label]</option>";
            foreach ($filter['filter_itens'] as $item)
            {
                $selected = ($_GET[$filter['name']] == $item['value']) ? ' selected="selected"' : '';
                echo "<option value=\"$item[value]\"$selected>$item[label]</option>";
            }
            echo "</select>";
        }
    }

    function query_var_callback()
    {
        if (!is_admin())
            return false;

        global $wp;

        foreach (self::$filters as $filter)
            if ( $filter['post_type'] == WordPress_Helper::current_post_type() )
                $wp->add_query_var( $filter['name'] );
    }

    function query_parse_callback($query)
    {
        if (!is_admin())
            return false;

        foreach (self::$filters as $filter)
        {
            if ( $filter['post_type'] != WordPress_Helper::current_post_type() )
                continue;

            if ( $meta_value = $query->get($filter['name']) )
            {
                if (!is_array(current($filter['query'])))
                    $filter['query'] = [$filter['query']];

                foreach ($filter['query'] as $filter_query)
                {
                    // match input filter value
                    $value = ($filter_query[1] == '#input') ? $meta_value : $filter_query[1];
                    
                    $query->set( $query[0], $value );
                }
            }
        }

        return $query;
    }
    
}