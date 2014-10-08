<?php
/**
 * @package Fieldmanager
 */

/**
 * Textarea field
 * @package Fieldmanager
 */
class PW_Fieldmanager_Paragraph extends Fieldmanager_Field {

	/**
	 * @var string
	 * Override field_class
	 */
	public $field_class = '';

	/**
	 * @var string
	 * The paragraph content
	 */
	public $content = '';

	/**
	 * Construct default attributes; 50x10 textarea
	 * @param string $label
	 * @param array $options
	 */
	public function __construct( $label = '', $options = array() ) {
		if ( is_array( $label ) ) $options = $label;
		else $options['label'] = $label;

		if (isset($options['label']))
			unset($options['label']);

		parent::__construct( $options );
	}

	/**
	 * Form element
	 * @param mixed $value
	 * @return string HTML
	 */
	public function form_element( $value = '' ) {
		return sprintf(
			'<p class="fm-element" id="%s" %s />%s</p>',
			$this->get_element_id(),
			$this->get_element_attributes(),
			html_entity_decode( $this->content )
		);
	}

}