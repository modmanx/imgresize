<?php

$config = include('config.php');

define('RAXSDK_CONNECTTIMEOUT', 30);

require 'vendor/autoload.php';

if(isset($_GET['r'])){
	$r = $_GET['r'];
	$httppos = strpos($r, 'http');
	$f = substr($r, $httppos);
	$r = substr($r, 0, $httppos - 1);
	$r = explode('/', $r);
	if(count($r) == 2){
		$_GET['w'] = $r[0];
		$_GET['h'] = $r[1];
		$_GET['f'] = $f;
	}
}

if(!isset($_GET['f'])){
	die('define $_GET-f');
}

$w = isset($_GET['w']) ? intval($_GET['h']) : 100;
$h = isset($_GET['h']) ? intval($_GET['h']) : 100;
$opt = isset($_GET['opt']) ? $_GET['opt'] : 'auto';

$file_url = $_GET['f'];

$ext = pathinfo($file_url, PATHINFO_EXTENSION);

$qs_hash = sha1($w . '_' . $h . '_' . $opt . '_' . $file_url);

$cache_dir = __DIR__ . DIRECTORY_SEPARATOR . $config['cache_dir'];

if(!file_exists($cache_dir)){
	die('create dir ' . $cache_dir);
}

$resolve_cache_file = $cache_dir . 'resolve.json';

if(!file_exists($resolve_cache_file)){
	file_put_contents($resolve_cache_file, '{}');
}

$resolve_cache = json_decode(file_get_contents($resolve_cache_file), true);

if(isset($resolve_cache[$qs_hash])){
	header('Location: ' . $resolve_cache[$qs_hash]);
	exit;
}

echo '<pre>';

$tmpfilename = $qs_hash;
$tmpfile = $cache_dir . $tmpfilename;

$connection = new \OpenCloud\Rackspace(RACKSPACE_UK,
	array('username' => $config['rackspace_api_username'],
	       'apiKey' => $config['rackspace_api_key']));

$ostore = $connection->objectStoreService('cloudFiles', 'LON', 'publicURL');

$cont = $ostore->getContainer($config['rackspace_container']);

try{
	// $obj = $cont->getObject($tmpfilename);
	// echo $obj->getPublicUrl();
	$objs = $cont->objectList(array('prefix' => $qs_hash));
	$url = false;
	foreach($objs as $obj){
		$url = $obj->getPublicUrl();
		break;
	}
}catch(\Exception $e){
	// echo 'not found';
	// print_r($e->getMessage());
	// die();
}

if($url){
	$resolve_cache[$qs_hash] = $url->__toString();
	file_put_contents($resolve_cache_file, json_encode($resolve_cache));
	header('Location: ' . $url);
}

$fc = file_get_contents($file_url);
file_put_contents($tmpfile, $fc);
// $tmpfile = $cache_dir . '273a06ff2238cd2d44a62aead6f487c351e5d0cc';

$exif_imgt = @exif_imagetype($tmpfile);

if(!$exif_imgt){
	die('not an image');
}

if(IMAGETYPE_GIF == $exif_imgt){
	$file_ext = 'gif';
}else if(IMAGETYPE_JPEG == $exif_imgt){
	$file_ext = 'jpg';
}else if(IMAGETYPE_PNG == $exif_imgt){
	$file_ext = 'png';
}else{
	die('not supported image');
}

$tmpfilename .= '.'.$file_ext;
rename($tmpfile, $tmpfile . '.' . $file_ext);
$tmpfile = $tmpfile . '.' . $file_ext;

require_once('php-image-magician/php_image_magician.php');  

try{

	// *** Open JPG image
	$mobj = new imageLib($tmpfile);

	$mobj->resizeImage($w, $h, $opt);

	$mobj->saveImage($tmpfile, 85);

	$finfo = finfo_open(FILEINFO_MIME_TYPE);

	$mimetype = finfo_file($finfo, $tmpfile);

    $handle = fopen($tmpfile, "r");

    $obj = $cont->uploadObject($tmpfilename, $handle, array(
	    'test_tag' => 10
	));

	$url = $obj->getPublicUrl()->__toString();
	$resolve_cache[$qs_hash] = $url;
	file_put_contents($resolve_cache_file, json_encode($resolve_cache));

	@fclose($handle);

	// echo $mimetype;

    // $ocfile = $cont->dataObject();
    // $ocfile->setName($tmpfilename);
    // $ocfile->setContent($handle);
    // $ocfile->setContentType($mimetype);
    // $ocfile->setMetadata(array(
    // 		'Content-type' => $mimetype
    // 	), true);
    // $mtd = $ocfile->getMetadata();
    // print_r($mtd);
    // $ocfile->update();

	@unlink($tmpfile);

	header('Location: ' . $url);

}catch(\Exception $e){

	print_r($e->getMessage());
	print_r($e);

	unlink($tmpfile);
	exit;

}

@unlink($tmpfile);
exit;

