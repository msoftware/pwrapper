<?php
class PW_Widgets extends PW_Module {
	
	static $widgets = array();
	static $defaults = array();
	
	static function init_hooks()
	{
	}

	/**
     * Inits the PW_Module`s _widget methods. Register their actions callbacks.
     * Every class method ending with `_widget` is registred.
	 * @return [type] [description]
	 */
	static function init_theme()
	{
        foreach (PWrapper::classes_methods('_widget') as $class => $methods)
        {
            foreach ($methods as $method)
            {
                $id = str_replace('_widget', '', $method);
                $id = str_replace('_', '-', sanitize_title( $id ));

            	self::register($id, [$class,$method]);
            }
        }
	}

	static function set_default($id, $options)
	{
		self::$defaults[$id] = $options;
	}
	
	static function register($id, $method)
	{
		$options = array(
			'title' 	  => String_Helper::humanize($id),
			'description' => 'Widget for '. String_Helper::humanize($id),
			'class_name'  => "", // css class to include
			'method'	  => $method, // callback for the widget
		);

		if (isset( self::$defaults[$id] ))
			$options = array_merge($options, self::$defaults[$id]);

		// widget instance
		$widget = new PW_Widget($id, $options['title'], $options);

		// register widget
		global $wp_widget_factory;
		$wp_widget_factory->widgets[$id] = $widget;
		//$widget->_register();
		
		if ( isset(self::$widgets[$id]) )
			self::$widgets[$id] = array_merge($widget, self::$widgets[$id]);
		else
			self::$widgets[$id] = $widget;
	}
	
	/**
	 * Wrapper callback for the widget display. Is called when the widget is rendered in the site.
	 * Calls the class method `_shortcode` which renders the output for the widget.
	 * @param class $widget   	PW_Widget instance
	 * @param array $args     	Widget arguments.
	 * @param array $instance 	Saved values from database.
	 * @return [type]           [description]
	 */
	static function display($widget, $args, $instance)
	{
		$options = $widget->pw_options;
		$class 	 = $options['method'][0];
		$method  = $options['method'][1];
		
		if (method_exists($class, $method))
		{
			echo call_user_func(array($class, $method), $args, $instance);
		}
	}
	
	/**
	 * Wrapper callback for the widget form. Is called on the admin widget management.
	 * Calls the class method `_shortcode_form` which renders the form for the widget.
	 * @param class $widget   	PW_Widget instance
	 * @param array $instance 	Previously saved values from database.
	 * @return [type]           [description]
	 */
	static function form ($widget, $instance)
	{
		$options = $widget->pw_options;
		$class 	 = $options['method'][0];
		$method  = str_replace('_widget', '_widget_form', $options['method'][1]);
		
		if (method_exists($class, $method))
		{
			call_user_func(array($class, $method), $instance);
		}
	}
	
	/**
	 * Wrapper callback for the widget form update. Is called on the admin widget management when user save changes on the widget.
	 * Calls the class method `_widget_update`.
	 * @param class $widget   	PW_Widget instance
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array 			Updated safe values to be saved.
	 */
	static function update ($widget, $new_instance, $old_instance)
	{
		$options = $widget->pw_options;
		$class 	 = $options['method'][0];
		$method  = str_replace('_widget', '_widget_update', $options['method'][1]);
		
		if (method_exists($class, $method))
		{
			return call_user_func(array($class, $method), $widget, $new_instance, $old_instance);
		}
	}
}

/**
 * Wrapper/Factory class for widgets creations.
 */
class PW_Widget extends WP_Widget {
	
	public $pw_options = array();

	/**
	 * Register widget with WordPress.
	 */
	function __construct($id, $title, $args)
	{
		$this->pw_options = $args;
		parent::__construct($id, $title, $args);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance )
	{
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args['before_widget'];
		
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];
			
		PW_Widgets::display($this, $args, $instance);
		
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance )
	{
		PW_Widgets::form($this, $instance);
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance )
	{
		$instance = PW_Widgets::update($this, $new_instance, $old_instance);
		
		return $instance;
	}

} // class Foo_Widget
