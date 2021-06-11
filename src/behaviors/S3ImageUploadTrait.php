<?php
namespace bvb\aws\behaviors;

use bvb\media\common\helpers\StorageHelper;
use bvb\aws\helpers\S3Helper;
use Yii;
use yii\helpers\ArrayHelper;
use yii\imagine\Image;

/**
 * S3ImageUploadTrait contains functions manipulating and creating variations
 * of images as they are uploaded. This is intended to override the functions
 * from \bvb\media\common\behaviors\LocalStorageImageBehavior
 */
trait S3ImageUploadTrait
{

    /**
     * Differs from original in that it doesn't check for a local file and works
     * off of the temp local image
     * @throws \yii\base\InvalidArgumentException
     * @return void
     */
    protected function createVariations()
    {
        foreach ($this->variations as $profile => $config) {
            $variationPath = $this->getVariationUploadPath($profile);
            if ($variationPath !== null) {
                $this->generateImageVariation($config, $this->_file->tempName, $variationPath);
            }
        }
        
        if ($this->deleteOriginalFile) {
            parent::delete();
        }
    }


    /**
     * @param boolean $old
     */
    protected function delete($old = false)
    {
        $oldPath = $this->getUploadPath($old);
        if(!empty($oldPath)){
            S3Helper::getSingleton()->deleteObject($this->bucket, $oldPath);
        }

        $variations = array_keys($this->variations);
        foreach ($variations as $variation) {
            $oldPath = $this->getVariationUploadPath($variation, $old);
            if(!empty($oldPath)){
                S3Helper::getSingleton()->deleteObject($this->bucket, $oldPath);
            }
        }
    }


    /**
     * @param string $attribute
     * @param string $profile
     * @return string|null
     */
    public function getVariationUploadUrl($profile = 'thumb')
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        
        if ($this->createVariationsOnRequest) {
            $this->createVariations();
        }
        
        // --- For performance reasons this does not support placeholders since
        // --- it's an extra request to S3 to see if the image exists so we just
        // --- get the object url based on the variation name
        $urlBasePath = StorageHelper::resolvePath($this->urlBasePath, $model);
        $filename = $model->getOldAttribute($this->attributeName);
        $variationName= $this->getVariationFilename($filename, $profile);
        return S3Helper::getSingleton()->getClient()->getObjectUrl($this->bucket, Yii::getAlias($urlBasePath.'/'.$variationName));
    }

    /**
     * @param array $config
     * @param string $path
     * @param string $variationPath
     */
    protected function generateImageVariation($config, $path, $variationPath)
    {
        $width = ArrayHelper::getValue($config, 'width');
        $height = ArrayHelper::getValue($config, 'height');
        $quality = ArrayHelper::getValue($config, 'quality', 100);
        $mode = ArrayHelper::getValue($config, 'mode', \Imagine\Image\ManipulatorInterface::THUMBNAIL_INSET);
        $bg_color = ArrayHelper::getValue($config, 'bg_color', 'FFF');

        if (!$width || !$height) {
            $image = Image::getImagine()->open($path);
            $ratio = $image->getSize()->getWidth() / $image->getSize()->getHeight();
            if ($width) {
                $height = ceil($width / $ratio);
            } else {
                $width = ceil($height * $ratio);
            }
        }

        // Fix error "PHP GD Allowed memory size exhausted".
        ini_set('memory_limit', '512M');
        // --- Save the image to a temp spot on our server then upload that
        $variationPathInfo = pathinfo($variationPath);
        $tmpVariationPath = sys_get_temp_dir().'/'.$variationPathInfo['basename'];

        Image::$thumbnailBackgroundColor = $bg_color;
        Image::thumbnail($path, $width, $height, $mode)->save($tmpVariationPath, ['quality' => $quality]);

        // --- Upload temp variation to the CDN
        return S3Helper::getSingleton()->uploadObject($this->bucket, $variationPath, $tmpVariationPath);
    }
}
