<?php

class DataStorage {
	
	private static $redis = false;

	public static function init(){
		self::$redis = new Redis();
		self::$redis->connect('127.0.0.1');
	}

	public static function setLastVerificationTimestamp($time = NULL){
		if(is_null($time) || (int) $time !== $time){
			$time = mktime();
		}

		self::$redis->hSet('partyInfo','lastVerificationTimestamp', $time);
	}

	public static function getLastVerificationTimestamp(){
		return (int) self::$redis->hGet('partyInfo', 'lastVerificationTimestamp');
	}

	public static function markStatusAsVerified($status){
		return self::$redis->sAdd('partyInfo:processedEntries', $status);
	}

	public static function isStatusVerified($status){
		return self::$redis->sIsMember('partyInfo:processedEntries', $status);
	}

	public function reset(){
		self::$redis->del('partyInfo');
	}

}