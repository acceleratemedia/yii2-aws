<?php
namespace bvb\aws\behaviors;

use bvb\aws\helpers\S3Helper;
use bvb\media\common\helpers\StorageHelper;
use Yii;

/**
 * S3UploadTrait contains functions for saving and deleting a file by communicating
 * with S3 storage and is intended to override the functions of the same name in
 * \bvb\media\common\behaviors\LocalStorageBehavior
 */
trait S3UploadTrait
{
    /**
     * @var string Name of the bucket that files will be uploaded to
     */
    public $bucket;

    /**
     * @param string $filename
     * @return boolean
     */
    protected function isFilenameUnique($filename)
    {
        $uploadBasePath = StorageHelper::resolvePath($this->uploadBasePath, $this->owner);
        $key = Yii::getAlias($uploadBasePath.'/'.$filename);
        return !S3Helper::getSingleton()->getClient()->doesObjectExist($this->bucket, $key);
    }

    /**
     * This method is called at the end of inserting or updating a record.
     */
    public function afterSave()
    {
        if ($this->_file instanceof \yii\web\UploadedFile) {
            $path = $this->getUploadPath();
            $this->save($this->_file, $path);
            $this->afterUpload();
        }
    }

    /**
     * Saves the uploaded file.
     * @param UploadedFile $file the uploaded file instance
     * @return boolean true whether the file is saved successfully
     */
    protected function save($file, $path)
    {
        return S3Helper::getSingleton()->uploadObject($this->bucket, $path, $file->tempName);
    }

    /**
     * Deletes old file.
     * @param string $attribute
     * @param boolean $old
     */
    protected function delete($old = false)
    {
        $oldPath = $this->getUploadPath($old);
        if(!empty($oldPath)){
            S3Helper::getSingleton()->deleteObject($this->bucket, $oldPath);
        }
    }

    /**
     * Returns file url for the attribute.
     * @return string|null
     */
    public function getUploadUrl()
    {
        /** @var BaseActiveRecord $model */
        $urlBasePath = StorageHelper::resolvePath($this->urlBasePath, $this->owner);
        $filename = $this->owner->getOldAttribute($this->attributeName);
        return S3Helper::getSingleton()->getClient()->getObjectUrl($this->bucket, Yii::getAlias($urlBasePath.'/'.$filename));
    }
}
