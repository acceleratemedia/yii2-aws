<?php

namespace bvb\aws\helpers;

use Aws\S3\S3Client;
use Yii;

/**
 * AwsHelper is a base class for using Amazon AWS services
 */
class AwsHelper
{
    /**
     * Implement the Singleton trait
     */
    use Singleton;

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

    /**
     * Returns a list of objects found in the bucket
     * @param string $bucket
     * @return array
     */
    public function listObjects($bucket)
    {
        return $this->_client->getIterator('ListObjects', [
            'Bucket' => $bucket
        ]);
    }


    /**
     * Returns a pre-signed request to a file on Amazon
     * @param string $bucket
     * @param string $key
     * @return string
     */
    public function getPresignedRequest($bucket, $key)
    {
        // Creating a presigned URL
        $cmd = $this->_client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key
        ]);

        $request = $this->_client->createPresignedRequest($cmd, '+10 minutes');

        // Get the actual presigned-url
        return $request->getUri();
    }
}