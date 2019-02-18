<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2017 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */
 
namespace shuwon\pic;

use yii\base\Exception;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\VarDumper;
use yii\validators\ValidationAsset;
use yii\widgets\InputWidget;

class Webuploader extends InputWidget{
    //默认配置
    protected $_options;
    public $server;
    public $domain;
    public $driver;
    public $server_qiniu;
    public $width;
    public $realwidth;
    public $height;
    public $realheight;
    public function init()
    {
        parent::init();
        $this->width or $this->width = 300;
        $this->height or $this->height = $this->width*0.75;
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
        $this->options['innerHTML'] = isset($this->options['innerHTML']) ? $this->options['innerHTML'] : '<img src="/admin/img/nopic.jpg" height="150"/><div id="picker_'.$this->options['id'].'"><button class="btn btn-default" id="replace" type="button">选择图片</button></div>';
        $this->options['innerHTML'] .= '<div id="webuploaderList_'.$this->options['id'].'" class="uploader-list"></div>';
        $this->options['previewWidth'] = isset($this->options['previewWidth']) ? $this->options['previewWidth'] : '260';
        $this->options['previewHeight'] = isset($this->options['previewHeight']) ? $this->options['previewHeight'] : '260';

    }
    public function run()
    {
        call_user_func([$this, 'register' . ucfirst($this->driver) . 'ClientJs']);
        $value = Html::getAttributeValue($this->model, $this->attribute);
        $content = $value ? '<img src="'.(strpos($value, 'http:') === false ? (\Yii::getAlias('@static') . '/' . $value) : $value).'" height="150"/><div id="picker_'.$this->options['id'].'"><button class="btn btn-default" id="replace" type="button">替换图片</button></div>'
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

        $op_id = $this->options['id'];
       // WebuploaderAsset::register($this->view);
        $web = \Yii::getAlias('@static');
        $server = $this->server ?: Url::to(['/site/pic']);
        $swfPath = \Yii::getAlias('@webuploader/assets');
        $this->view->registerJs(<<<JS
        /*
    *Start 图片裁剪压缩
    * 对应 input 父级div添加class:UpclipImg
    */ 
    $('.content').after('<div class="imgClip"><div class="imgClipBox"><div class="upload-headimg">上传图片<i class="fa fa-times btn_close"></i></div><div id="clipArea"></div><input type="file" id="file"><div class="imgData"><div id="view"></div></div><div class="btnClip"><button type="button" id="clipBtn" class="pop-cancel">确定</button><button type="button" class="btn_cancel">取消</button></div></div></div>');
    function Clip(objDiv) { 
        objDiv.click(function (e) {
            $('#clipArea').html('')
            $('.imgClip').addClass('active')
            var clipArea = new bjj.PhotoClip("#clipArea", {
                size: [{$this->width}, {$this->height}], // 截取框的宽和高组成的数组。
                outputSize: [{$this->realwidth}, {$this->realheight}], // 输出图像的宽和高组成的数组。默认值为[0,0]，表示输出图像原始大小
                //outputType: "jpg", // 指定输出图片的类型，可选 "jpg" 和 "png" 两种种类型，默认为 "jpg"
                file: "#file", // 上传图片的<input type="file">控件的选择器或者DOM对象
                view: "#view", // 显示截取后图像的容器的选择器或者DOM对象
                ok: "#clipBtn", // 确认截图按钮的选择器或者DOM对象
                PicSize:0, //限制大小（单位kb，0为不限制大小）
                quality:.8, //压缩图片的质量 0-1（没特别需求为0.8，图片看不出变化）
                loadStart: function () {
                    console.log("照片读取中");
                },
                loadComplete: function () {
                    console.log("照片读取完成");
                    $('#file').addClass('active')
                },
                loadError: function (event) {
                    console.log("加载失败");
                },
                clipFinish: function (dataURL) {
                    console.log(dataURL);
                    $.ajax({
                        url: '{$server}',
                        cache:false,
                        type: 'POST',
                        //dataType: "JSON",
                        //processData:false,
                        data: {
                            file:dataURL,
                        },
                        success : function(rs){
                            $( '#picker_{$this->options['id']}' ).prev().attr('src',dataURL);
                            $('#{$op_id}').val(rs.url)
                        },
                        error: function(e){
                        }
                    });
                    //objDiv.find('input').val(dataURL)
                }
            });
        })
        //取消
        $('.btn_close').click(function (e) { 
            $('.imgClip').removeClass('active')
            $('#file').removeClass('active')
            $('#clipArea').html('')
        })
        $('.btn_cancel').click(function (e) { 
            $('.imgClip').removeClass('active')
            $('#file').removeClass('active')
            $('#clipArea').html('')
        })
        //切图
        $('#clipBtn').click(function (e) { 
            $('.imgClip').removeClass('active')
            $('#file').removeClass('active')
            setTimeout(function (e) {
			$('#clipArea').html('')
        },1000)
        })
     }
     var ewm=$('#picker_$op_id #replace')
     
     Clip(ewm)

JS
        );
    }

    /**
     * 注册js
     */
    private function registerQiniuClientJs()
    {
        WebuploaderAsset::register($this->view);
        $tokenUrl = $this->server ?: Url::to(['/site/image']);
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
            title: 'Images',
            extensions: 'gif,jpg,jpeg,bmp,png,ico',
            mimeTypes: 'image/*'
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
	    $('button[type=button]').html('替换文件')
    });
}, 'json');

JS
        );
    }
} 