<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2017 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */

namespace shuwon\pic;

use Qiniu\Auth;
use Yii;
use yii\web\Response;
use yii\web\UploadedFile;

class Action extends \yii\base\Action
{
    public $driver;
    /**
     * @var array
     */
    public $config = [];


    public function init()
    {
        //close csrf
        Yii::$app->request->enableCsrfValidation = false;
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (empty($this->driver)) {
            $this->driver = isset(Yii::$app->params['webuploader_driver']) ? Yii::$app->params['webuploader_driver'] : 'local';
        }
        parent::init();
    }

    public function run()
    {
        switch ($this->driver) {
            case 'local':
                return $this->local();
                break;
            case 'qiniu':
                return $this->qiniu();
                break;
        }
    }

    private function local()
    {
        header('Content-type:text/html;charset=utf-8');
        $base64_image_content       = \Yii::$app->request->post('file',null);
        if(!$base64_image_content) return ['code'=>404,'msg'=>'数据不能为空',data=>null];
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
            $type = $result[2];
            $root = Yii::getAlias('@staticroot');
            $path = 'upload/image/' . date('Ymd') . '/';
            $dir = $root . '/' . $path;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = time().".{$type}";
            $new_file = $dir.$name;
            // base64解码后保存图片
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))){
                return [
                    'state' => 'success',
                    'type' => $type,
                    'original' => $new_file,
                    'url' => $path . $name,
                    'title' => $name
                ];
            }else
                return ['code'=>4041,'msg'=>'文件保存失败','data'=>null];
        }


        $uploader = UploadedFile::getInstanceByName('file');
        $root = Yii::getAlias('@staticroot');
        $path = 'upload/image/' . date('Ymd') . '/';
        $dir = $root . '/' . $path;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = time() . '.' . $uploader->extension;
        $uploader->saveAs($dir . $name);
        /**
         * 得到上传文件所对应的各个参数,数组结构
         * array(
         *     "state" => "",          //上传状态，上传成功时必须返回"success"
         *     "url" => "",            //返回的地址
         *     "title" => "",          //新文件名
         *     "original" => "",       //原始文件名
         *     "type" => ""            //文件类型
         *     "size" => "",           //文件大小
         * )
         */
        return [
            'state' => 'success',
            'type' => $uploader->type,
            'size' => $uploader->size,
            'original' => $uploader->name,
            'url' => $path . $name,
            'title' => $name
        ];
    }
    private function resizeImage($imagePath, $thumb, $width = 700, $height = 950)
    {
    	list($imageWidth, $imageHeight) = getimagesize($imagePath);
    	$imagePath = imagecreatefromjpeg($imagePath);
    	if ($width && ($imageWidth < $imageHeight))
    	{
    		$width = ($height / $imageHeight) * $imageWidth;
    	}
    	else
    	{
    		$height = ($width / $imageWidth) * $imageHeight;
    	}
    	$image = imagecreatetruecolor($width, $height);
    	imagecopyresampled($image, $imagePath, 0, 0, 0, 0, $width, $height, $imageWidth, $imageHeight);
    	imagepng($image, $thumb);
    	imagedestroy($image);
    }
    private function qiniu()
    {
        $this->config = array_merge($this->config, Yii::$app->params['qiniu']);
        $bucket = $this->config['bucket'];
        $accessKey = $this->config['accessKey'];
        $secretKey = $this->config['secretKey'];

        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket);

        $ret = ['uptoken' => $upToken];

        return $ret;
    }
    
    private function base64EncodeImage ($image_file) {
    	$base64_image = '';
    	$image_info = getimagesize($image_file);
    	$image_data = fread(fopen($image_file, 'r'), filesize($image_file));
    	$base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
    	return $base64_image;
    }
}