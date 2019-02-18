<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2017 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */

namespace shuwon\images;

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
    	/*
        $uploader = UploadedFile::getInstanceByName('file');
        
        $root = Yii::getAlias('@staticroot');
        $path = 'upload/image/' . date('Ymd') . '/';
        $dir = $root . '/' . $path;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = time() . '.' . $uploader->extension;
        $uploader->saveAs($dir . $name);
       
       */
    	$name = $_FILES['file']['name'];
    	$size = $_FILES['file']['size'];
    	$name_tmp = $_FILES['file']['tmp_name'];
    	$type = strtolower(substr(strrchr($name, '.'), 1)); //获取文件类型
    	
    	//压缩图片
    	$pic_name = time() . rand(10000, 99999) . "." . $type;
    	$thumb='test_thumb.jpg';
    	$this->resizeImage($name_tmp, $thumb);
    	
        //存储图片到图片服务器
        header('content-type:text/html;charset=utf8');
         
        $curl = curl_init();
        if($type=='jpeg'){
        	$cfile = curl_file_create($thumb,'image/jpeg','testpic');
        }elseif($type=='jpg'){
        	$cfile = curl_file_create($thumb,'image/jpg','testpic');
        }elseif($type=='png'){
        	$cfile = curl_file_create($thumb,'image/png','testpic');
        }elseif($type=='gif'){
        	$cfile = curl_file_create($thumb,'image/gif','testpic');
        }
         
        //$data = array('img'=>'@'. $path.$pic_name);
        $data = array('myimage' => $cfile);
        curl_setopt($curl, CURLOPT_URL, "http://182.150.41.12:900/uploadimg");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        //curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        
        
        $base64_img = $this->base64EncodeImage($thumb);
        $collection = Yii::$app->mongodb->getCollection ('mycollection');
        //插入操作
        $data = [
        		'image' => $base64_img
        ];
        $mongodbobj = $collection->insert($data);
        $mongodbarr = (array)$mongodbobj;
        $mongodbid =  $mongodbarr['oid'];

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
            
            'url' => $base64_img,
            'title' => $name,
        	'mongodbid' => $mongodbid,
        	'localimg' => $result['name']
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

        $auth = new \shuwon\qiniu\Auth($accessKey, $secretKey);
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