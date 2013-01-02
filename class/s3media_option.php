<?php

if( !class_exists('s3media_option')){
	class s3media_option extends s3media {
		
		public $options;

		function __construct() {

			// init options if available
			$this->options = get_option( parent::DOMAIN );

			// add conference options
			add_action('admin_menu', array( $this, 'admin_menu') );
			add_action('admin_init', array( $this, 'admin_init') );
		}

		function get_option( $key = null ) {
			if( is_null($key) || !empty($this->options[$key])) {
				return false;
			} else {
				return $this->options[$key];
			}
		}

		function get_options(){
			return $this->options;
		}

		function admin_menu() {
			add_options_page( parent::PLUGIN_NAME, parent::PLUGIN_NAME, 'manage_options', parent::DOMAIN, array( $this, 'manage_options'));
		}

		function manage_options() {

			// header
			printf( '<div class="wrap"><div id="icon-options-general" class="icon32"><br></div><h2>%s</h2><form method="post" action="%s">',
				parent::PLUGIN_NAME,
				'options.php'
				);

			// options
			settings_fields( parent::DOMAIN );
			do_settings_sections( parent::DOMAIN );

			// footer
			printf('<p class="submit"><input type="submit" class="button-primary" value="%s" /></p></form></div>',
				__('Save Settings')
				);

		}

		// add the admin settings and such
		function admin_init(){

			// load saved settings
			$this->options = get_option( parent::DOMAIN );

			// setup settings
			register_setting( parent::DOMAIN, parent::DOMAIN, array( $this, 'sanitize_options' ) );

			// general settings
			add_settings_section( 'general', __('General Options', 's3media'), function(){}, parent::DOMAIN );

			// api settings
			add_settings_section( 'api', __('AWS S3 API Options', 's3media'), function(){}, parent::DOMAIN );
			add_settings_field( 'access_key', __('Access Key', 's3media'), array( $this, 'option_builder'), parent::DOMAIN, 'api', array(
				'label_for' => 'access_key',
				'description' => __('Enter AWS Access Key. You can find it by logging in "Your Account -> Security Credentials" and scroll to "Access Credentials" link.','s3media')
				));
			add_settings_field( 'secret_key', __('Secret Key', 's3media'), array( $this, 'option_builder'), parent::DOMAIN, 'api', array(
				'label_for' => 'secret_key',
				'description' => __('Enter AWS Secret Key. You can find it by logging in "Your Account -> Security Credentials" and scroll to "Access Credentials" and click "show" on the appropriate key.','s3media')
				));
			add_settings_field( 'bucket', __('AWS S3 Bucket Name', 's3media'), array( $this, 'option_builder'), parent::DOMAIN, 'api', array(
				'label_for' => 'bucket',
				'description' => __('Allowed characters for file and folder names are: a-z, A-Z, 0-9,-,...','s3media')
				));
		}

		public function option_builder( $args ){
			$defaults = array(
				'label_for' => 'default',
				'type' => '',
				'description' => ''
				);
			$html = '';
			$args = wp_parse_args($args, $defaults);
			switch( $args['type'] ) {
				case 'select':
					$check_value = !isset($this->options[ $args['label_for'] ]) || empty($this->options[ $args['label_for'] ]) ? '' : $this->options[ $args['label_for'] ];
					$multiple = isset($args['multiple']) && $args['multiple'] ? 'multiple' : '';
					$html .= '<select name="' . $this->field_name( $args['label_for'] ) . '" ' . $multiple . '>';
					foreach( $args['options'] as $value => $title ) {
						$html .= '<option value="' . $value . '" ' . selected( $value, $check_value, false ) . '>' . $title . '</option>';
					}
					$html .= '</select>';
					break;
				case 'radio':
					$check_value = !isset($this->options[ $args['label_for'] ]) || empty($this->options[ $args['label_for'] ]) ? key( $args['options'] ) : $this->options[ $args['label_for'] ];
					foreach( $args['options'] as $value => $title ) {
						$html .= '<input type="radio" name="' . $this->field_name( $args['label_for'] ) . '" value="' . $value . '" ' . checked( $value, $check_value, false ) . ' /><span class="name">' . $title . '</span>';
					}
					break;
				case 'textarea':
					$value = !isset($this->options[ $args['label_for'] ]) || empty($this->options[ $args['label_for'] ]) ? '' : $this->options[ $args['label_for'] ];
					$html .= '<textarea name="' . $this->field_name( $args['label_for'] ) . '" rows="7" cols="75" type="textarea">' . $value . '</textarea>';
					break;
				default :
					$value = !isset($this->options[ $args['label_for'] ]) || empty($this->options[ $args['label_for'] ]) ? '' : $this->options[ $args['label_for'] ];
					$html .= '<input type="text" name="' . $this->field_name( $args['label_for'] ) . '" value="' . $value . '" />';
					break;
			}
			$html .= !empty($args['description']) ? '<p class="description">' . $args['description'] . '</p>' : '';

			// override the html for the field with add_filter('wp-pillow-author_{label}')
			echo apply_filters( parent::DOMAIN . '_' . $args['label_for'], $html);
		}

		function options_setting_string() {
			echo '<input name="' . $this->field_name('text_string') . '" type="text" value="' . $this->options['text_string'] . '" />';
		} 

		// validate our options
		function sanitize_options( $input ) {
			// $options = get_option('uva_conf_options');
			// $options['text_string'] = trim($input['text_string']);
			// if(!preg_match('/^[a-z0-9]{32}$/i', $options['text_string'])) {
			// 	$options['text_string'] = '';
			// }
			return $input;
		}

		public function field_name( $name, $echo = false ) {
			$name = parent::DOMAIN . "[{$name}]";
			if( $echo ) {
				echo $name;
			} else {
				return $name;
			}
		}
	}
}