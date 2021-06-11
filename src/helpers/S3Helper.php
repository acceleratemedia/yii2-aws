<?php

namespace bvb\aws\helpers;

use Aws\S3\S3Client;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * S3Helper contains functions useful for communciating with Amazon's S3 API
 * using their SDK
 */
class S3Helper extends \yii\base\BaseObject
{
    /**
     * Implement the Singleton trait
     */
    use \bvb\singleton\Singleton;

    /**
     * @var array Configuration passed into S3Client class to for instantiation
     */
    public $clientOptions = [];

    /**
     * Returns the client with the supplied configuration
     * @return Aws\S3\S3Client
     */
    private $_client;
    public function getClient()
    {
        if(empty($this->_client)){
            $clientOptions = [
                'version' => 'latest',
                'region' => 'us-east-2'
            ];

            if(isset(Yii::$app->params['aws']['s3client'])){
                $clientOptions = ArrayHelper::merge($clientOptions, Yii::$app->params['aws']['s3client']);
            }

            $clientOptions = ArrayHelper::merge($clientOptions, $this->clientOptions);

            if(!isset($clientOptions['credentials']['key'])){
                throw new InvalidConfigException('A client key must be supplied either through the $clientOptions property or as an application parameter under [aws][s3client][key]');
            }

            if(!isset($clientOptions['credentials']['secret'])){
                throw new InvalidConfigException('A client secret must be supplied either through the $clientOptions property or as an application parameter under [aws][s3client][secret]');
            }

            $this->_client = S3Client::factory($clientOptions);
        }
        return $this->_client;
    }

    /**
     * Upload a file to the specified bucket with the specified key
     * @param string $bucket
     * @param string $key
     * @param string $path
     * @param mixed $metadata
     * @return \Aws\Result PutObject call result with the details of uploading the file.
     */
    public function uploadObject($bucket, $key, $path, $metadata = [])
    {
        return $this->getClient()->putObject([
            'Bucket' => $bucket,
            'Key'    => $key,
            'SourceFile'   => $path,
            'MetaData' => $metadata
        ]);
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
        return $this->getClient()->deleteObject([
            'Bucket' => $bucket,
            'Key'    => $key
        ]);
    }

    /**
     * Returns a list of objects found in the bucket
     * @param string $bucket
     * @return array
     */
    public function listObjects($bucket)
    {
        return $this->getClient()->getIterator('ListObjects', [
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
        $cmd = $this->getClient()->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key
        ]);

        $request = $this->getClient()->createPresignedRequest($cmd, '+10 minutes');

        // Get the actual presigned-url
        return $request->getUri();
    }
}