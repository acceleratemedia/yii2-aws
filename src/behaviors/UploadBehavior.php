<?php
namespace accelm\aws\behaviors;

use accelm\aws\AwsHelper;

/**
 * UploadBehavior uploads the file to Amazon's S3 Storage
 */
class UploadBehavior extends \accelm\media\common\behaviors\LocalStorageBehavior
{
    /**
     * Use this trait to override the functions that save and delete a file to
     * communicate with S3 Storage instead
     */
    use S3UploadTrait;
}
