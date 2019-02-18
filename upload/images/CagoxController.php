<?php
/**
 * @link http://www.shuwon.com/
 * @copyright Copyright (c) 2016 成都蜀美网络技术有限公司
 * @license http://www.shuwon.com/
 */
namespace backend\controllers;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\data\ActiveDataProvider;
use common\models\Cagox;
use common\models\Comment;
use Qiniu\Auth;
/**
 * ca管理控制器。
 * @author 制作人
 * @since 1.0
 */
class CagoxController extends CommonController {

   public function actionIndex() {
        return $this->render('index', [
        		'model' => new Cagox(),
               ]);
    }
    
    public function actionAdd() {
        if(Yii::$app->request->getIsPost()){
            $data = Yii::$app->request->post('Cagox');
            $result = array();
            $model = new Cagox();
            if ($model->load(Yii::$app->request->post())) {
                if ($model->save()) {
                    $result['status'] = 1;
                    $result['message'] = '保存成功';
                    $result['url'] = Url::toRoute('cagox/index');
                }
            }
            $errors = $model->getFirstErrors();
            if ($errors) {
                $result['status'] = 0;
                $result['message'] = current($errors);
            }
            return $this->renderJson($result);
        }else{
            $model = new Cagox();
            $model->cagox_like = 0;
            $model->top = 1;
            return $this->render('add_'.Yii::$app->params['webuploader_driver'], [
            		'model' => $model,
            		]);
        }
    }
    
    public function actionEdit() {
    	if(Yii::$app->request->getIsPost()){
	        $data = Yii::$app->request->post('Cagox');
	        $result = array();
	        if (is_numeric($data['cagox_id']) && $data['cagox_id'] > 0) {
	            $model = Cagox::findOne($data['cagox_id']);
	            if (!$model) {
	                $result['status'] = 0;
	                $result['message'] = '未找到该记录';
	            }
	        }
	        if ($model->load(Yii::$app->request->post())) {
	            if ($model->save()) {
	                $result['status'] = 1;
	                $result['message'] = '保存成功';
	                $result['url'] = Url::toRoute('cagox/index');
	            }
	        }
	        $errors = $model->getFirstErrors();
	        if ($errors) {
	            $result['status'] = 0;
	            $result['message'] = current($errors);
	        }
	        return $this->renderJson($result);
    	}else{
    		$id = Yii::$app->request->get('id');
    		$model = Cagox::findOne($id);
    		return $this->render('edit_'.Yii::$app->params['webuploader_driver'], [
    				'model' => $model
    				]);
    	}
    }

    public function actionList() {
        $query = Cagox::find();
        $query->andFilterWhere(["enabled"=>Yii::$app->request->get("enabled")]);
        $provider = new ActiveDataProvider([
        		'query' => $query,
        		'pagination' => [
        		'pageSize' => 9,
        		],
        		'sort' => [
        		'defaultOrder' => [
        		'cagox_id' => SORT_DESC,
        		]
        		],
        		]);
        return $this->renderPartial('list', ['provider' => $provider]);
    }
	
	/*
	 * 删除评论内容
	 * */
    public function actionDel($id) {
        $result = array();
        $model = Cagox::findOne($id);
        $model->delete();
        $result['status'] = 1;
        $result['message'] = '删除成功';
        return $this->renderJson($result);
    }
    
    /*
     * 删除评论内容
     * */
    public function actionCommentdel($id){
    	$result = array();
        $model = Comment::findOne($id);
        $model->delete();
        $result['status'] = 1;
        $result['message'] = '删除成功';
        return $this->renderJson($result);
    }
    
    
public function actionAjax() {
    	$typeArr = array("jpg", "png", "gif");//允许上传文件格式
    
    	//$path = $_SERVER['DOCUMENT_ROOT']."static/upload/image/". date('Ymd')."/";//上传路径
    	//$path_html = "upload/image/". date('Ymd')."/";//上传路径

    	// Create target dir
    	
    	/*if (!file_exists($path)) {
    		@mkdir($path,0777,true);
    	}*/
    
    	
    	if (isset($_POST)) {
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
    			
    			//mongodb图片上传
    			
    			$base64_img = $this->base64EncodeImage($thumb);
    			$collection = Yii::$app->mongodb->getCollection ('mycollection');
    			
    			//插入操作
    			$data = [
    			'image' => $base64_img
    			];
    			$mongodbobj = $collection->insert($data);
    			$mongodbarr = (array)$mongodbobj;
    			$mongodbid =  $mongodbarr['oid'];
    			
    			//$mongodbid =  '123';
    			
    			echo json_encode(array("error"=>"0","pic"=>$result['name'],"name"=>'testname',"mongodbid"=>$mongodbid));
    			
    	}
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
    public function actionDelpicture() {
    	$result = array();
    
    	$cagox_id = Yii::$app->request->get('cagox_id');
    	$cagox_url = Yii::$app->request->get('cagox_img');
    	$cagox_url_local = Yii::$app->request->get('cagox_img_local');
    
    	$cagox = Cagox::findOne($cagox_id);
    	$new_cagox_url = $cagox->cagox_img;
    	$new_cagox_url = str_ireplace($cagox_url.'|', '', $new_cagox_url);
    	$new_cagox_url = str_ireplace('|'.$cagox_url, '', $new_cagox_url);
    	$new_cagox_url = str_ireplace($cagox_url, '', $new_cagox_url);
    	
    	$new_cagox_url_local = $cagox->cagox_img_local;
    	$new_cagox_url_local = str_ireplace($cagox_url_local.'|', '', $new_cagox_url_local);
    	$new_cagox_url_local = str_ireplace('|'.$cagox_url_local, '', $new_cagox_url_local);
    	$new_cagox_url_local = str_ireplace($cagox_url_local, '', $new_cagox_url_local);
    	 
    	$cagox->cagox_img = $new_cagox_url;
    	$cagox->cagox_img_local = $new_cagox_url_local;
    	$cagox->save();
    
    	$result['status'] = 1;
    	$result['message'] = '删除成功';
    	 
    	return $this->renderJson($result);
    }
    
    public function actionUptoken() {
    	$bucket = Yii::$app->params['qiniu']['bucket'];
    	$accessKey = Yii::$app->params['qiniu']['accessKey'];
    	$secretKey = Yii::$app->params['qiniu']['secretKey'];
    	$auth = new Auth($accessKey, $secretKey);
    
    	$upToken = $auth->uploadToken($bucket);
    
    	$result = array('uptoken' => $upToken);
    
    	return $this->renderJson($result);
    }
    private function base64EncodeImage ($image_file) {
    	$base64_image = '';
    	$image_info = getimagesize($image_file);
    	$image_data = fread(fopen($image_file, 'r'), filesize($image_file));
    	$base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
    	return $base64_image;
    }
}