<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2016 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */

namespace shuwon\video;

use yii\base\Exception;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\InputWidget;

class Webuploader extends InputWidget{
    //默认配置
    protected $_options;
    public $server;
    public $domain;
    public $driver;
    public $server_qiniu;
    public function init()
    {
        parent::init();
        \Yii::setAlias('@webuploader', __DIR__);
        if (empty($this->driver)) {
            $this->driver = isset(\Yii::$app->params['webuploader_driver']) ? \Yii::$app->params['webuploader_driver'] : 'local';
        }
        if ($this->driver == 'local') {
            // 初始化@static别名,默认@web/static,最好根据自己的需求提前设置好@static别名
            $static = \Yii::getAlias('@static', false);
            if (!$static) {
                \Yii::setAlias('@static', '@web/static');
            }
        } else if ($this->driver == 'qiniu') {
            if (empty($this->domain)) {
                $this->domain = \Yii::$app->params['qiniu']['domain'];
            }
            if (empty($this->server_qiniu)) {
            	$this->server_qiniu = \Yii::$app->params['qiniu']['server'];
            }
            if (empty($this->domain)) {
                throw new Exception('七牛上传方式必须设置根域名');
            }
        }
        
        $this->options['boxId'] = isset($this->options['boxId']) ? $this->options['boxId'] : $this->options['id'].'_box';
        $this->options['innerHTML'] = isset($this->options['innerHTML']) ? $this->options['innerHTML'] : '<video controls="controls" src="" width="200"></video><div id="picker_'.$this->options['id'].'" /><button class="btn btn-default" type="button">选择视频</button></div>';
        $this->options['innerHTML'] .= '<div id="webuploaderList_'.$this->options['id'].'" class="uploader-list"></div>';
        $this->options['previewWidth'] = isset($this->options['previewWidth']) ? $this->options['previewWidth'] : '250';
        $this->options['previewHeight'] = isset($this->options['previewHeight']) ? $this->options['previewHeight'] : '150';
    }
    public function run()
    {
        call_user_func([$this, 'register' . ucfirst($this->driver) . 'ClientJs']);
        $value = Html::getAttributeValue($this->model, $this->attribute);
        $content = $value ? '<video controls="controls" src="'.(strpos($value, 'http:') === false ? (\Yii::getAlias('@static') . '/' . $value) : $value).'" width="200"></video><div id="picker_'.$this->options['id'].'"><button class="btn btn-default" type="button">替换视频</button></div>'
	        :
	        $this->options['innerHTML'];

        if($this->hasModel()){
            return Html::tag('div', $content, ['id'=>$this->options['boxId']]) . Html::activeHiddenInput($this->model, $this->attribute);
        }else{
            return Html::tag('div', $content, ['id'=>$this->options['boxId']]) . Html::hiddenInput($this->name, $this->value);
        }
    }
     
    /**
     * 注册js
     */
    private function registerLocalClientJs()
    {
        WebuploaderAsset::register($this->view);
        $web = \Yii::getAlias('@static');
        $server = $this->server ?: Url::to(['/site/video']);
        $swfPath = \Yii::getAlias('@webuploader/assets');
        $this->view->registerJs(<<<JS
var uploader = WebUploader.create({
        auto: true,
        fileVal: 'file',
        // swf文件路径
        swf: '{$swfPath}/Uploader.swf',

        // 文件接收服务端。gy
        server: '{$server}',

        // 选择文件的按钮。可选。
        // 内部根据当前运行是创建，可能是input元素，也可能是flash.
        pick: '#picker_{$this->options['id']}',

        accept: {
            title: 'Video',
            extensions: 'flv,mpg,mpeg,avi,wmv,mov,asf,rm,rmvb,mkv,m4v,mp4',
            mimeTypes: 'video/*'
        },

        // 不压缩image, 默认如果是jpeg，文件上传前会压缩一把再上传！
        resize: false
    });
// 文件上传过程中创建进度条实时显示。
uploader.on( 'uploadProgress', function( file, percentage ) {
    var li = $( '#webuploaderList_{$this->options['id']}'),
        percent = li.find('.progress .progress-bar');

    // 避免重复创建
    if ( !percent.length ) {
        percent = $('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar"></div></div>')
                .appendTo( li )
                .find('.progress-bar');
    }

    percent.css( 'width', percentage * 100 + '%' );
});
// 完成上传完了，成功或者失败，先删除进度条。
uploader.on( 'uploadSuccess', function( file, data ) {
    $( '#picker_{$this->options['id']}' ).next().fadeOut();
    $( '#picker_{$this->options['id']}' ).prev().attr('src','{$web}/'+data.url);
    $( '#{$this->options['id']}' ).val(data.url);
    $('button[type="button"]').html('替换文件')
});
JS
        );
    }

    /**
     * 注册js
     */
    private function registerQiniuClientJs()
    {
        WebuploaderAsset::register($this->view);
        $tokenUrl = $this->server ?: Url::to(['/site/video']);
        $swfPath = \Yii::getAlias('@webuploader/assets');
        $this->view->registerJs(<<<JS
$.get("{$tokenUrl}",function(res) {
    var uploader = WebUploader.create({
        auto: true,
        fileVal: 'file',
        chunked :false,
        // swf文件路径
        swf: '{$swfPath}/Uploader.swf',

        // 文件接收服务端。gy
        server: '{$this->server_qiniu}',

        // 选择文件的按钮。可选。
        // 内部根据当前运行是创建，可能是input元素，也可能是flash.
        pick: '#picker_{$this->options['id']}',
        
        accept: {
            title: 'Video',
            extensions: 'flv,mpg,mpeg,avi,wmv,mov,asf,rm,rmvb,mkv,m4v,mp4',
            mimeTypes: 'video/*'
        },

        // 不压缩image, 默认如果是jpeg，文件上传前会压缩一把再上传！
        resize: false,

        formData: {
            token: res.uptoken
        }
    });
// 文件上传过程中创建进度条实时显示。
uploader.on( 'uploadProgress', function( file, percentage ) {
    var li = $( '#webuploaderList_{$this->options['id']}'),
        percent = li.find('.progress .progress-bar');

    // 避免重复创建
    if ( !percent.length ) {
        percent = $('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar"></div></div>')
                .appendTo( li )
                .find('.progress-bar');
    }

    percent.css( 'width', percentage * 100 + '%' );
});
    // 完成上传完了，成功或者失败，先删除进度条。
    uploader.on( 'uploadSuccess', function( file, data ) {
        var url = data.key;
        $( '#picker_{$this->options['id']}' ).next().fadeOut();
        $( '#picker_{$this->options['id']}' ).prev().attr('src','{$this->domain}/'+url);
        $( '#{$this->options['id']}' ).val(url);
    });
}, 'json');

JS
        );
    }
} 