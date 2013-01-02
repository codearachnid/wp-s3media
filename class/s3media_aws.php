<?php

if( !class_exists('s3media_aws')){
	class s3media_aws extends s3media {
		function __construct(){
			// Include the AWS SDK using the phar
			// * @link https://github.com/aws/aws-sdk-php/ AWS SDK Docs
			// this needs to be proplerly pathed since we're in the subfolder
			if( file_exists('aws.phar'))
				require_once 'aws.phar';
		}
	}
}