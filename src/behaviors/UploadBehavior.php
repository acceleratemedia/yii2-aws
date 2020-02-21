<?php

namespace bvb\aws\behaviors;

use bvb\aws\AwsHelper;
use Closure;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * UploadBehavior automatically uploads file and fills the specified attribute
 * with a value of the name of the uploaded file.
 *
 * To use UploadBehavior, insert the following code to your ActiveRecord class:
 *
 * ```php
 * use bvb\aws\behaviors\UploadBehavior;
 *
 * function behaviors()
 * {
 *     return [
 *         [
 *             'class' => UploadBehavior::class,
 *             'attribute' => 'file',
 *             'path' => '@webroot/upload/{id}',
 *         ],
 *     ];
 * }
 * ```
 *
 * Based off of \mohore\file\UploadBehavior
 */
class UploadBehavior extends Behavior
{
    /**
     * @event Event an event that is triggered after a file is uploaded.
     */
    const EVENT_AFTER_UPLOAD = 'afterUpload';

    /**
     * @var string the attribute which holds the attachment.
     */
    public $attribute;

    /**
     * @var string The name of the buckey on S3 which you will be uploading to
     */
    public $bucket;

    /**
     * @var string the base path or path alias to the directory in which to save files.
     */
    public $path;

    /**
     * @var bool Getting file instance by name
     */
    public $instanceByName = false;

    /**
     * @var boolean|callable generate a new unique name for the file
     * set true or anonymous function takes the old filename and returns a new name.
     * @see self::generateFileName()
     */
    public $generateNewName = true;

    /**
     * @var boolean If `true` current attribute file will be deleted
     */
    public $unlinkOnSave = true;

    /**
     * @var boolean If `true` current attribute file will be deleted after model deletion.
     */
    public $unlinkOnDelete = true;

    /**
     * @var boolean $deleteTempFile whether to delete the temporary file after saving.
     */
    public $deleteTempFile = true;

    /**
     * @var UploadedFile the uploaded file instance.
     */
    private $_file;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->attribute === null) {
            throw new InvalidConfigException('The "attribute" property must be set.');
        }
        if ($this->path === null) {
            throw new InvalidConfigException('The "path" property must be set.');
        }
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * This method is invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        if (($file = $model->getAttribute($this->attribute)) instanceof UploadedFile) {
            $this->_file = $file;
        } else {
            if ($this->instanceByName === true) {
                $this->_file = UploadedFile::getInstanceByName($this->attribute);
            } else {
                $this->_file = UploadedFile::getInstance($model, $this->attribute);
            }
        }
        if ($this->_file instanceof UploadedFile) {
            $this->_file->name = $this->getFileName($this->_file);
            $model->setAttribute($this->attribute, $this->_file);
        }
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     */
    public function beforeSave()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;

        if ($this->_file instanceof UploadedFile) {
            if (!$model->getIsNewRecord() && $model->isAttributeChanged($this->attribute)) {
                if ($this->unlinkOnSave === true) {
                    $this->delete($this->attribute, true);
                }
            }
            $model->setAttribute($this->attribute, $this->_file->name);
        } else {
            // Protect attribute
            unset($model->{$this->attribute});
        }
    }

    /**
     * This method is called at the end of inserting or updating a record.
     * @throws \yii\base\InvalidArgumentException
     */
    public function afterSave()
    {
        if ($this->_file instanceof UploadedFile) {
            $path = $this->getUploadPath($this->attribute);
            $this->save($this->_file, $path);
            $this->afterUpload();
        }
    }

    /**
     * This method is invoked after deleting a record.
     */
    public function afterDelete()
    {
        $attribute = $this->attribute;
        if ($this->unlinkOnDelete && $attribute) {
            $this->delete($attribute);
        }
    }

    /**
     * Returns file path for the attribute.
     * @param string $attribute
     * @param boolean $old
     * @return string|null the file path.
     */
    public function getUploadPath($attribute, $old = false)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $path = StorageHelper::resolvePath($this->path, $media);
        $fileName = ($old === true) ? $model->getOldAttribute($attribute) : $model->$attribute;

        return $fileName ? $path . '/' . $fileName : null;
    }

    /**
     * Returns the UploadedFile instance.
     * @return UploadedFile
     */
    protected function getUploadedFile()
    {
        return $this->_file;
    }

    /**
     * Saves the uploaded file.
     * @param UploadedFile $file the uploaded file instance
     * @return boolean true whether the file is saved successfully
     */
    protected function save($file)
    {
        return $this->getAwsHelper()->uploadObject($this->bucket, $this->getUploadPath($this->attribute), $file->tempName);
        
    }

    /**
     * Deletes old file.
     * @param string $attribute
     * @param boolean $old
     */
    protected function delete($attribute, $old = false)
    {
        return $this->getAwsHelper()->deleteObject($this->bucket, $this->getUploadPath($this->attribute, $old));
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    protected function getFileName($file)
    {
        if ($this->generateNewName) {
            return $this->generateNewName instanceof Closure
                ? call_user_func($this->generateNewName, $file)
                : $this->generateFileName($file);
        } else {
            return $this->sanitize($file->name);
        }
    }

    /**
     * Replaces characters in strings that are illegal/unsafe for filename.
     *
     * #my*  unsaf<e>&file:name?".png
     *
     * @param string $filename the source filename to be "sanitized"
     * @return boolean string the sanitized filename
     */
    public static function sanitize($filename)
    {
        return str_replace([' ', '"', '\'', '&', '/', '\\', '?', '#'], '-', $filename);
    }

    /**
     * Generates random filename.
     * @param UploadedFile $file
     * @return string
     */
    protected function generateFileName($file)
    {
        return uniqid() . '.' . $file->extension;
    }

    /**
     * This method is invoked after uploading a file.
     * The default implementation raises the [[EVENT_AFTER_UPLOAD]] event.
     * You may override this method to do postprocessing after the file is uploaded.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    protected function afterUpload()
    {
        $this->owner->trigger(self::EVENT_AFTER_UPLOAD);
    }

    /**
     * Gets the AWS Helper for communicating with S3 via the API
     * @return \bvb\aws\AwsHelper
     */
    protected function getAwsHelper()
    {
        return AwsHelper::getSingleton();
    }
}
