<?php
namespace bvb\aws\behaviors;

use bvb\aws\AwsHelper;

/**
 * UploadBehavior uploads the file to Amazon's S3 Storage
 */
class UploadBehavior extends \bvb\media\common\behaviors\LocalStorageBehavior
{
    /**
     * Use this trait to override the functions that save and delete a file to
     * communicate with S3 Storage instead
     */
    use S3UploadTrait;
}
