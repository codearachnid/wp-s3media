<?php

/**
 * for more about uploading and using wp filesystem
 * @link http://codex.wordpress.org/Function_Reference/wp_handle_upload
 * @link http://ottopress.com/2011/tutorial-using-the-wp_filesystem/
 * @link http://wordpress.stackexchange.com/questions/53753/saving-media-which-hook-is-fired
 */


// Trying to cheat 'eh?
if ( !defined( 'ABSPATH' ) ) die( '-1' );

// s3media
if( ! class_exists('s3media')) {
	class s3media {
		protected static $instance;
    protected static $s3media_options; 
    
		public $path;
		public $dir;
		public $url;
		public $version = '1.0';
    
    public $s3;
    
		const PLUGIN_NAME = 'Amazon S3 Media';
		const DOMAIN = 's3media';
		const MIN_WP_VERSION = '3.5';
		const MIN_PHP_VERSION = '5.3';

		function __construct() {

			// register lazy autoloading
			spl_autoload_register( 'self::lazy_loader' );

			// set core vars
			$this->path = self::get_plugin_path();
			$this->dir = trailingslashit( basename( $this->path ) );
			$this->url = plugins_url() . '/' . $this->dir;
			$this->base_slug = apply_filters( self::DOMAIN . '_base_slug', 'wishlist');

			static::$s3media_options = new s3media_option;
      
      // add_action('wp_handle_upload', array($this, 'process_uploads') );
      add_action('add_attachment', array($this, 'process_uploads') );
      add_action('edit_attachment', array($this, 'process_uploads') );
		}
    
		public static function lazy_loader( $class_name ) {

			$file = self::get_plugin_path() . 'class/' . $class_name . '.php';

			if ( file_exists( $file ) )
				require_once $file;
		}

		public static function get_plugin_path() {
			return trailingslashit( dirname( __FILE__ ) );
		}
    
    public function process_uploads( $attachment_id ){
           
      $metadata = wp_get_attachment_metadata( $attachment_id );
      $uploads = wp_upload_dir();
      
      // s3media::s3_upload_file($file_url, $uri);
      
    }
    
    public function s3_get(){
      try{
        $options = static::$s3media_options->get_options();
        $s3 = new S3($options['access_key'], $options['secret_key']);
        return $s3;
      }catch(Exception $e){
        return WP_Error('broke', __('Unrecognized access|secret pair.'));
      }
    }
        
    public function s3_list_buckets($details = false){
      $s3 = $this->s3_get();
      try{
        $buckets = $s3->listBuckets($details);
        print_r($buckets); // temporary
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to get S3 buckets.'));
      }
    }
    
    public function s3_get_bucket($bucket_name){
      $s3 = $this->s3_get();
      try{
        if (($contents = $s3->getBucket($bucket_name)) !== false) {
          foreach ($contents as $object) {
            print_r($object);
          }
        }
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to get S3 bucket contents.'));
      }
    }
    
    public function s3_get_bucket_location($bucket_name){
      $s3 = $this->s3_get();
      try{
        if (($location = S3::getBucketLocation($bucket)) !== false) {
          return $location;
        }
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to get S3 bucket location.'));
      }
    }
    
    // uri can contain path like year/month/day/filename to make sub folders
    public function s3_upload_file($file, $uri){
      $s3 = $this->s3_get();
      try{
        $bucket = static::$s3media_options->get_option('bucket');
        $input = $s3->inputResource(fopen($file, "rb"), filesize($file));
        
        if (S3::putObject($input, $bucket, $uri, S3::ACL_PUBLIC_READ)) {
            return true;
        } else {
            return false;
        }
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to upload file.'));
      }
    }
    
		/**
		 * Check the minimum PHP & WP versions
		 *
		 * @static
		 * @return bool Whether the test passed
		 */
		public static function prerequisites() {;
			$pass = TRUE;
			$pass = $pass && version_compare( phpversion(), self::MIN_PHP_VERSION, '>=' );
			$pass = $pass && version_compare( get_bloginfo( 'version' ), self::MIN_WP_VERSION, '>=' );
			return $pass;
		}

		public static function min_version_fail_notice() {
			echo '<div class="error"><p>';
			_e( sprintf( '%s requires the minimum versions of PHP v%s, WordPress v%s, and WooCommerce v%s in order to run properly.',
				self::PLUGIN_NAME,
				self::MIN_PHP_VERSION,
				self::MIN_WP_VERSION,
				self::MIN_WC_VERSION
			), 'wcsvl' );
			echo '</p></div>';
		}

		/* Static Singleton Factory Method */
		public static function instance() {
			if ( !isset( self::$instance ) ) {
				$class_name = __CLASS__;
				self::$instance = new $class_name;
			}
			return self::$instance;
		}
	}	
}
