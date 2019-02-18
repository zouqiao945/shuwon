<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2017 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */
 
namespace shuwon\pic;

use yii\web\AssetBundle;
class WebuploaderAsset extends AssetBundle{
    public $css = [
        'webuploader.css',
    ];
    public $js = [
        'webuploader.min.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
    public function init()
    {
        $this->sourcePath = __DIR__ . '/assets';
        parent::init();
    }
}