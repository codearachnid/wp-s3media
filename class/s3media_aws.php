<?php

if( !class_exists('s3media_aws')){
	class s3media_aws extends s3media {
    public $config;
    public $root_bucket;
    public $s3;
    
		function __construct(){
			// Include the AWS SDK using the phar
			// * @link https://github.com/aws/aws-sdk-php/ AWS SDK Docs
			// this needs to be proplerly pathed since we're in the subfolder
      
			if( file_exists( parent::get_plugin_path().'/aws.phar') )
				require_once parent::get_plugin_path().'/aws.phar';
      
      $options = static::$s3media_options->get_options();
      
      $this->config = self::config($options);
      
      $this->root_bucket = $options['bucket'];
      
      use Aws\Common\Aws;
      use Aws\S3\Enum\CannedAcl;
      use Aws\S3\Exception\S3Exception;
      
      $this->s3 = Aws::factory($this->config)->get('s3');
      
		}
    
    public static function lazy_loader( $class_name ) {

			$file = parent::get_plugin_path() . '/' . str_replace('\\', '/', $class_name) . '.php';
      require_once $file;
      
		}
    
    public static function config($options){
      
      return array(
        'key'    => $options['access_key'],
        'secret' => $options['secret_key'],
        'region' => 'us-west-2'
      );
    }
    
    public function upload_file($file, $sub_dir){
      try {
          $this->s3->putObject(array(
              'Bucket' => $this->root_bucket . '/' .$sub_dir,
              'Key'    => $file->slug,
              'Body'   => fopen($file->abs_path, 'r'),
              'ACL'    => CannedAcl::PUBLIC_READ
          ));
      } catch (S3Exception $e) {
          return WP_Error('broke', __("The file was not uploaded."));
      }
    }
	}
}