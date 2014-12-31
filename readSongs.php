<?php
require('config.php');
require('vendor/autoload.php');
require('vendor/facebook/php-sdk-v4/autoload.php');
require('datastorage.php');



// return downloadVideoAndExtractAudio("https://www.youtube.com/watch?v=wZKSecK35DA");




// Init the data storage... I'm using Redis. Use whatever you like better.
DataStorage::init();

$lastVerificationTimestamp = DataStorage::getLastVerificationTimestamp();

try {
	Facebook\FacebookSession::setDefaultApplication(FB_APP_ID, FB_SECRET_KEY);
	$session = Facebook\FacebookSession::newAppSession();
} catch(Exception $e){
	$session = false;
}

if(!$session){	
	echo "Can not get Facebook session. Verify the appKey and secret in the config file.";
	exit();
}

$untilWhen = mktime();
$sinceWhen = $untilWhen-TIME_BETWEEN_RUNS;

$request = new Facebook\FacebookRequest(
  $session,
  'GET',
  '/'.FB_EVENT_ID.'/feed/?since='.$sinceWhen.'&until='.$untilWhen.'&limit=10'
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

	if(DataStorage::isStatusVerified($id)){
		continue;
	}

	DataStorage::markStatusAsVerified($id);

	$message = $post->message;

	// Does this message look like a Youtube URL?
	$url = isYoutubeUrl($post->message);

	$reply = 'The song has been accepted. Thanks!';
	if(!$url){
		$reply = 'This does not seem to be a YouTube URL...';
	}

	echo "Received {$url} ".PHP_EOL;
	downloadVideoAndExtractAudio($url);

}


function isYoutubeUrl($url){
	$rx = '~
    ^(?:https?://)?              # Optional protocol
     (?:www\.)?                  # Optional subdomain
     (?:youtube\.com|youtu\.be)  # Mandatory domain name
     /watch\?v=([^&]+)           # URI with video id as capture group 1
     ~x';
	$isYoutubeUrl = preg_match($rx, trim($url), $matches);

	if(!$isYoutubeUrl){
		return false;
	}

	if(!isset($matches[1])){
		return false;
	}

	return "https://www.youtube.com/watch?v={$matches[1]}";
}

function downloadVideoAndExtractAudio($url){
	if(!file_exists(MP3_FOLDER)){
		mkdir(MP3_FOLDER);
	}

	if(!is_dir(MP3_FOLDER) || !is_writable(MP3_FOLDER)){
		throw new Exception(MP3_FOLDER ." is not a directory or is not writable", 1);
	}

	$return = array();
	$exitCode = 0;
	$filename = false;

	chdir(MP3_FOLDER);
	$command = YOUTUBEDL_PATH.' ';
	$command .= escapeshellarg("--extract-audio").' ';
	$command .= escapeshellarg("--no-playlist").' ';
	$command .= escapeshellarg("--id").' ';
	$command .= escapeshellarg("--restrict-filenames").' ';
	$command .= escapeshellarg("--no-overwrites").' ';
	$command .= escapeshellarg($url).' ';

	exec($command, $return, $exitCode);

	if($exitCode){
		return false;
	}

	$rx = '~\[download\] Destination: (.*)~';

	foreach($return as $line){
		preg_match($rx, trim($line), $matches);

		if(isset($matches[1])){
			$filename = $matches[1];
			break;
		}
	}

	if(!$filename){
		return false;
	}
	exec(COMMAND_TO_APPEND_A_SONG.' "'.MP3_FOLDER.'/'.$filename.'"');
	return true;
}