<?php

namespace bvb\aws\actions;

use bvb\aws\helpers\AwsHelper;
use Yii;
use yii\base\Action;

/**
 * ListObjects uses the AWS PHP SDK to list objects in the specified bucket
 */
class ListObjects extends Action
{
    /**
     * The bucket we are listing objects from
     * @param string
     */
    public $bucket;

    /** 
     * 
     * @return array
     */
    public function run($bucket = null)
    {

        if(empty($bucket)){
            $bucket = $this->bucket;
        }
        if(empty($bucket)){
            throw new BadRequestHttpException('No bucket was specified to list objects from');
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return AwsHelper::getSingleton()->listObjects($bucket);
    }
}
