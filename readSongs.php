<?php
require('config.php');
require('vendor/autoload.php');
require('vendor/facebook/php-sdk-v4/autoload.php');
require('facebookHelper.php');
require('datastorage.php');


if(false){
	$message = 'Temita lindo https://soundcloud.com/lobsterdust/madonna-vs-bruno-mars';
	// $input = 'Temita lindo https://www.youtube.com/watch?v=yu4fOdK-KDs';

	var_dump($message, $url);
	$url = !$url && parseYoutubeUrl($message) ? isYoutubeUrl($message) : $url;
	var_dump($url);
	$url = !$url && parseVimeoUrl($message) ? isVimeoUrl($message) : $url;

	$url = !$url && parseSoundCloudUrl($message) ? isSoundCloudUrl($message) : $url;
	var_dump($url);

	// die('SONG IS '.$url.PHP_EOL);

	$filename = downloadVideoAndExtractAudio($url);
	setAlbumName($filename, 'DJ Kalambre');
	addSongToQueue($filename);

	die('SONG IS '.$url.PHP_EOL);

}

$cliOptions = getopt("",array("reset"));

if(isset($cliOptions['reset']) && $cliOptions['reset']){
	echo "Doing a first reset ...";
	DataStorage::reset();	
	return ;
}


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
$sinceWhen = DataStorage::getLastVerificationTimestamp();




$request = new Facebook\FacebookRequest(
  $session,
  'GET',
  '/'.FB_EVENT_ID.'/feed/?since='.$sinceWhen.'&until='.$untilWhen.'&limit=9999999'
);


$response = $request->execute();
$graphObject = $response->getGraphObject();

$data = $graphObject->asArray();
if(!isset($data['data'])){
	echo "No data";
	return;
}

$data = $data['data'];

while($post = array_pop($data)){

	$postObject = new FacebookGraphObject($post);

	$message = $postObject->getLink();
	$message = !$message ? $postObject->getMessage() : $message;
	
	if(!strlen($message)){
		continue;
	}

	$posterName = $postObject->getPublisherName();

	$id = $postObject->getId();

	if(DataStorage::isStatusVerified($id)){
		continue;
	}

	// DataStorage::markStatusAsVerified($id);
	


	// Does this message look like a Youtube URL?
	$url = false;
	// var_dump($message, $url);
	$url = !$url && parseYoutubeUrl($message) ? isYoutubeUrl($message) : $url;
	// var_dump($url);
	$url = !$url && parseVimeoUrl($message) ? isVimeoUrl($message) : $url;

	$url = !$url && parseSoundCloudUrl($message) ? isSoundCloudUrl($message) : $url;
	// var_dump($url);

	// continue;

	$reply = 'The song has been accepted. Thanks!';
	if(!$url){
		$reply = 'This does not seem to be a YouTube URL...';
		continue;
	}
	// die($reply);

	echo "Received {$url} ".PHP_EOL;

	
	$filename = downloadVideoAndExtractAudio($url);

	if(!strlen($filename)){
		continue;
	}
	setAlbumName($filename, $posterName);
	addSongToQueue($filename);
}

function parseVimeoUrl($url){
	$pattern = '#^(?:http?://)?';    # Optional URL scheme. Either http or https.
	$pattern = '#(?:https?://)?';    # Optional URL scheme. Either http or https.
	$pattern .= '(?:www\.)?';         #  Optional www subdomain.
	$pattern .= '(?:';                #  Group host alternatives:
	$pattern .=   'vimeo.com/';       #    Either youtu.be,
	$pattern .= ')';                  #  End host alternatives.
	$pattern .= '([\w]{7,11})';        # 11 characters (Length of Youtube video ids).
	$pattern .= '(?:.+)?$#x';         # Optional other ending URL parameters.
	preg_match($pattern, $url, $matches);
	return (isset($matches[1])) ? $matches[1] : false;	
}



function isVimeoUrl($url){
	echo "Try Vimeo {$url} ";
	$vimeoKey = parseVimeoUrl($url);

	echo "Got key {$vimeoKey}\n";

	if(!$vimeoKey){
		return false;
	}

	return "http://vimeo.com/{$vimeoKey}";	
}




function parseSoundCloudUrl($url){
	$pattern = '#^(?:http?://)?';    # Optional URL scheme. Either http or https.
	$pattern = '#(?:https?://)?';    # Optional URL scheme. Either http or https.
	$pattern .= '(?:www\.)?';         #  Optional www subdomain.
	$pattern .= '(?:';                #  Group host alternatives:
	$pattern .=   'soundcloud.com/';       #    Either youtu.be,
	$pattern .= ')';                  #  End host alternatives.
	$pattern .= '(.*)';        # 11 characters (Length of Youtube video ids).
	$pattern .= '(?:.+)?$#x';         # Optional other ending URL parameters.
	preg_match($pattern, $url, $matches);
	return (isset($matches[1])) ? $matches[1] : false;	
}



function isSoundCloudUrl($url){
	echo "Try SoundCloud {$url} ";
	$urlPath = parseSoundCloudUrl($url);

	echo "Got key {$urlPath}\n";

	if(!$urlPath){
		return false;
	}

	return "http://soundcloud.com/{$urlPath}";	
}




function parseYoutubeUrl($url) {
	$pattern = '#^(?:https?://)?';    # Optional URL scheme. Either http or https.
	$pattern = '#(?:https?://)?';    # Optional URL scheme. Either http or https.
	$pattern .= '(?:www\.)?';         #  Optional www subdomain.
	$pattern .= '(?:';                #  Group host alternatives:
	$pattern .=   'youtu\.be/';       #    Either youtu.be,
	$pattern .=   '|youtube\.com';    #    or youtube.com
	$pattern .=   '(?:';              #    Group path alternatives:
	$pattern .=     '/embed/';        #      Either /embed/,
	$pattern .=     '|/v/';           #      or /v/,
	$pattern .=     '|/watch\?v=';    #      or /watch?v=,    
	$pattern .=     '|/watch\?.+&v='; #      or /watch?other_param&v=
	$pattern .=   ')';                #    End path alternatives.
	$pattern .= ')';                  #  End host alternatives.
	$pattern .= '([\w-]{11})';        # 11 characters (Length of Youtube video ids).
	$pattern .= '(?:.+)?$#x';         # Optional other ending URL parameters.
	preg_match($pattern, $url, $matches);
	return (isset($matches[1])) ? $matches[1] : false;
}

function isYoutubeUrl($url){
	echo "Try YouTube {$url} ";
	$youtubeKey = parseYoutubeUrl($url);

	if(!$youtubeKey){
		return false;
	}

	return "https://www.youtube.com/watch?v={$youtubeKey}";
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
	$command .= escapeshellarg("--add-metadata").' ';
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

	return  MP3_FOLDER.'/'.$filename;

	exec(COMMAND_TO_APPEND_A_SONG.' "'.MP3_FOLDER.'/'.$filename.'"');
	return true;
}


function setAlbumName($path, $albumName){
	$command = KID3CLI_PATH.' -c \'set album "'.$albumName.'" 2\' '. escapeshellarg($path);
	echo $command;
	exec($command);
}

function addSongToQueue($path){
	exec(COMMAND_TO_APPEND_A_SONG.' '.escapeshellarg($path));
}





