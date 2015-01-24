<?php

class FacebookGraphObject {
	private $post;

	public function __construct($post){
		$this->post = $post;
	}



















	public function getPublisherName(){
		if(isset($this->post->from) && isset($this->post->from->name)){
			return $this->post->from->name;
		}

		return "";
	}

	public function getMessage(){
		if(isset($this->post->message)){
			return $this->post->message;
		}

		return "";
	}

	public function getId(){
		return $this->post->id;
	}


	public function getLink(){
		if(isset($this->post->link)){
			return $this->post->link;
		}
		return false;
	}
}