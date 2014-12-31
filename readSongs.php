<?php
require('vendor/autoload.php');
require('vendor/facebook/php-sdk-v4/autoload.php');

Facebook\FacebookSession::setDefaultApplication('93917592302', '2d0f42e7d1a228fce41605309c80a2fb');
$session = Facebook\FacebookSession::newAppSession();

$request = new Facebook\FacebookRequest(
  $session,
  'GET',
  '/1529633110618292/feed/?since=1419991714'
);
$response = $request->execute();
$graphObject = $response->getGraphObject();

$data = $graphObject->asArray();
if(!isset($data['data'])){
	return;
}

$data = $data['data'];

foreach($data as $post){

	if(!isset($post->message)){
		continue;
	}

	$id = $post->id;

	$file = '/tmp/'.$id;
	if(file_exists($file)){
		continue;
	}

	file_put_contents($file, "{$post->from->name} queued {$post->message}");
	$message = $post->message;

	var_dump($message);
}
