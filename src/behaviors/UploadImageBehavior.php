<?php

namespace bvb\aws\behaviors;

/**
 * UploadImageBehavior implements the S3UploadTrait to communicate with S3 storage
 * while keeping all of the image functionality from its parent class
 */
class UploadImageBehavior extends \bvb\media\common\behaviors\LocalStorageImageBehavior
{
    /**
     * Use this trait to override the functions that save and delete a file to
     * communicate with S3 Storage instead
     */
    use S3UploadTrait, S3ImageUploadTrait {
        S3ImageUploadTrait::delete insteadof S3UploadTrait;
    }
}
