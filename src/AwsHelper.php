<?php

namespace bvb\aws;

use Aws\S3\S3Client;
use Yii;

/**
 * AwsHelper is a base class for using Amazon AWS services
 */
class AwsHelper
{
    /**
     * Amazon S3Client class
     * @var Aws\S3\S3Client
     */
    private $_client;

    /**
     * Set up the client with the credentials
     * @return void
     */
    public function __construct($args = [])
    {
        $defaults = [
            'version' => 'latest',
            'region' => 'us-east-2',
            'credentials' => [
                'key' => Yii::$app->params['aws']['s3-client']['key'],
                'secret' => Yii::$app->params['aws']['s3-client']['secret']
            ]
        ];

        $this->_client = S3Client::factory(array_merge($defaults, $args));
    }

    /**
     * Since these are all basically stateless we only ever want to instantiate one so let's use
     * this function to set them in the container and get them that way
     * @return \bvb\aws\AwsHelper
     */
    static function getSingleton()
    {
        if(!Yii::$container->hasSingleton(static::class)){
            Yii::$container->setSingleton(static::class);
        }
        return Yii::$container->get(static::class);
    }

    /**
     * Upload a file to the specified bucket with the specified key
     * @param string $bucket
     * @param string $key
     * @param string $path_to_file
     * @param mixed $metadata
     * @return \Aws\Result PutObject call result with the details of uploading the file.
     */
    public function uploadObject($bucket, $key, $path_to_file, $metadata = [])
    {
        $result = $this->_client->putObject([
            'Bucket' => $bucket,
            'Key'    => $key,
            'SourceFile'   => $path_to_file,
            'MetaData' => $metadata
        ]);

        return $result['statusCode'] == 200;
    }


    /**
     * Removes the null version (if there is one) of an object and inserts a delete marker, which 
     * becomes the latest version of the object. If there isn't a null version, Amazon S3 does not remove any objects.
     * @param string $bucket
     * @param string $key
     * @return \Aws\Result PutObject call result with the details of uploading the file.
     */
    public function deleteObject($bucket, $key)
    {
        $result = $this->_client->deleteObject([
            'Bucket' => $bucket,
            'Key'    => $key
        ]);
        
        return $result['statusCode'] == 204;
    }
}