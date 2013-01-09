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
    
    public $s3_base_url;
    
		function __construct() {

			// register lazy autoloading
			spl_autoload_register( 'self::lazy_loader' );

			// set core vars
			$this->path = self::get_plugin_path();
			$this->dir = trailingslashit( basename( $this->path ) );
			$this->url = plugins_url() . '/' . $this->dir;
			$this->base_slug = apply_filters( self::DOMAIN . '_base_slug', 'wishlist');

			static::$s3media_options = new s3media_option;
      $this->s3_base_url = 'http://' . static::$s3media_options->get_option('bucket') . '.s3.amazonaws.com';
      // add_action('wp_handle_upload', array($this, 'process_uploads') );
      
      // attachment actions
      add_action('add_attachment', array($this, 'process_uploads') , 30 );
      add_action('edit_attachment', array($this, 'process_uploads') , 30 );
      add_action('delete_attachment', array($this, 'process_delete_attachment') , 30 );
      
      // attachment filters
      add_filter('wp_get_attachment_url', array($this, 'process_get_attachmentment_url') , 30);
      add_filter('wp_get_attachment_thumb_url', array($this, 'process_get_attachment_thumb_url'), 30 );
      add_filter('wp_update_attachment_metadata', array($this, 'process_update_attachment_metadata'), 30, 2 );
      
      // add cron to schedule the upload of large images using cron
      if ( !wp_next_scheduled( self::DOMAIN . '_schedule_uploads' ) ) {
        wp_schedule_event( time(), 'hourly', self::DOMAIN . '_schedule_uploads' );
      }
      add_action(self::DOMAIN . '_schedule_uploads', array( $this, self::DOMAIN . '_schedule_uploads_callback' ) );
		}
    
		public static function lazy_loader( $class_name ) {

			$file = self::get_plugin_path() . 'class/' . $class_name . '.php';

			if ( file_exists( $file ) )
				require_once $file;
		}

		public static function get_plugin_path() {
			return trailingslashit( dirname( __FILE__ ) );
		}
    
    public function s3media_schedule_uploads_callback(){
      //@TODO: continue from here and uncomment the upload limit logic
      // get a list of images that were not uploaded at run time
      // push them to s3
      // set the related meta info accordingly
    }
    
    public function s3_get_base_url(){
      return $this->s3_base_url;
    }
    
    public function process_uploads( $attachment_ID ){
      
      // $uploads = wp_upload_dir();
      $abs_path = get_attached_file( $attachment_ID );
      
      // only upload to s3 in case the max_upload_size restriction meets
//      $max_upload_size = $this->s3_get_option('max_upload_size');
//      $filesize = round(filesize($abs_path) / 1024, 2);
//      if( isset($max_upload_size) && (int)$max_upload_size < $filesize) {
//        // image is not uploaded to s3, use cron to upload it
//        update_post_meta( $attachment_ID, '_' . self::DOMAIN . '_upload', 'false' );
//        return;
//      }
      
      $relative_path = _wp_relative_upload_path( $abs_path );
      $success = s3media::s3_upload_file( $abs_path, $relative_path );

      if( ! is_wp_error( $success ) && $success ){
        update_post_meta( $attachment_ID, '_' . self::DOMAIN . '_upload', 'true' );
      }else{
        update_post_meta( $attachment_ID, '_' . self::DOMAIN . '_upload', 'false' );
      }
      
    }
    
    public function process_delete_attachment ( $attachment_ID ) {
      // calls to remove the attachment for s3
      
      // main attachment
      $abs_path = get_attached_file($attachment_ID);
      $relative_path = _wp_relative_upload_path($abs_path);
      $success = s3media::s3_delete_object( $relative_path );
           
      if( ! is_wp_error( $success ) && $success ){
        // delete thumbnails as well
        $s3_upload_meta = get_post_meta($attachment_ID, '_' . self::DOMAIN . '_upload_meta', true);
        
        $uploads = wp_upload_dir();
        $abspath = $uploads['path'];

        if( isset( $s3_upload_meta['sizes'] ) ){
          foreach( $s3_upload_meta['sizes'] as $size => $info ){
            $path = $abspath.'/'.$info['file'];
            $uri = _wp_relative_upload_path( $path );
            $success = s3media::s3_delete_object( $uri );
            if( ! is_wp_error( $success ) && $success ){
              
            }else{
              
            }
          }
          delete_post_meta( $attachment_ID, '_' . self::DOMAIN . '_upload_meta' );
        }
        
        delete_post_meta( $attachment_ID, '_' . self::DOMAIN . '_upload' );
      }else{
        //@TODO: what to do in case delete fails....
      }
    }
    
    // @TODO: move to template tags file
    public function get_attachment_id_from_src ($src) {
      global $wpdb;

      $regexp = "/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i";

      $src_new = preg_replace($regexp,'',$src);

      if($src_new != $src){
          $ext = pathinfo($src, PATHINFO_EXTENSION);
          $src = $src_new . '.' .$ext;
      }

      $query = "SELECT ID FROM {$wpdb->posts} WHERE guid='$src'";
      $id = $wpdb->get_var($query);

      return $id;
    }
    
    public function process_get_attachmentment_url( $url ){
      // changed it to s3 url
      $attachment_ID = $this->get_attachment_id_from_src( $url );
      $s3_upload = get_post_meta($attachment_ID, '_' . self::DOMAIN . '_upload' , true);
      if($s3_upload == 'true'){
        $url = $this->s3_base_url. '/' . $this->wp_relative_upload_url( $url );
      }
      return $url;
    }
    
    public function process_get_attachment_thumb_url ( $url ) {
      // change it to s3 for only those who are uploaded
      $attachment_ID = $this->get_attachment_id_from_src( $url );
      $s3_upload_meta = get_post_meta($attachment_ID, '_' . self::DOMAIN . '_upload_meta', true);
      
      if( isset( $s3_upload_meta['sizes'] ) ){
        foreach( $s3_upload_meta['sizes'] as $size => $info ){
          if( false !== strpos( $url, $info['file'] ) ){
            return $this->s3_base_url. '/' . $this->wp_relative_upload_url( $url );
          }
        }
      }
      
      return $url;
    }
    
    public function wp_relative_upload_url( $url ) {
      $new_url = $url;
      $uploads = wp_upload_dir();
      
      if ( 0 === strpos( $new_url, $uploads['baseurl'] ) ) {
        $new_url = str_replace( $uploads['baseurl'], '', $new_url );
        $new_url = ltrim( $new_url, '/' );
      }

      return $new_url;
    }
    
    public function process_update_attachment_metadata ( $data, $attachment_ID ) {
      set_time_limit(300);
      
      $uploads = wp_upload_dir();
      $abspath = $uploads['path'];
      $upload_meta = $data;
      $max_upload_size = $this->s3_get_option('max_upload_size');
      
      if( isset( $data['sizes'] ) ){
        foreach( $data['sizes'] as $size => $info ){
          $path = $abspath.'/'.$info['file'];
          // only upload to s3 in case the max_upload_size restriction meets
//          $filesize = round(filesize($path) / 1024, 2);
//          if( isset($max_upload_size) && (int)$max_upload_size < $filesize) {
//            // image thumnail is not uploaded to s3, use cron to upload it
//            $upload_meta['sizes'][$size][self::DOMAIN.'_upload'] = 'false';
//            continue;
//          }
          
          $uri = _wp_relative_upload_path( $path );
          $success = s3media::s3_upload_file($path, $uri);
          if( ! is_wp_error( $success ) && $success ){
            $upload_meta['sizes'][$size][self::DOMAIN.'_upload'] = 'true';
          }else{
            $upload_meta['sizes'][$size][self::DOMAIN.'_upload'] = 'false';
          }
        }
        
        update_post_meta( $attachment_ID, '_' . self::DOMAIN . '_upload_meta', $upload_meta );
      }
      
      return $data;
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
    
    public function s3_get_option($option){
      return static::$s3media_options->get_option($option);
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
    
    public function s3_get_bucket($bucket){
      $s3 = $this->s3_get();
      try{
        if (($contents = $s3->getBucket($bucket)) !== false) {
          foreach ($contents as $object) {
            print_r($object);
          }
        }
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to get S3 bucket contents.'));
      }
    }
    
    public function s3_get_bucket_location($bucket){
      $s3 = $this->s3_get();
      try{
        if (($location = $s3->getBucketLocation($bucket)) !== false) {
          return $location;
        }
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to get S3 bucket location.'));
      }
    }
    
    // uri can contain path like year/month/day/filename to make sub folders with in the bucket
    public function s3_upload_file($file, $uri){
      set_time_limit(300); // 5 minutes wait if needed - not elegent :(, make it better
      $s3 = $this->s3_get();
      try{
        $bucket = $this->s3_get_option('bucket');
        $input = $s3->inputResource(fopen($file, "rb"), filesize($file));
        
        if ($s3->putObject($input, $bucket, $uri, S3::ACL_PUBLIC_READ)) {
            return true;
        } else {
            return false;
        }
      }catch(Exception $e){
        return WP_Error('broke', __('Unable to upload file.'));
      }
    }
    
    public function s3_delete_object( $uri ) {
      $bucket = $this->s3_get_option('bucket');
      $s3 = $this->s3_get();
      try{
        if ($s3->deleteObject($bucket, $uri)) {
          return true;
        }
        return false;
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
