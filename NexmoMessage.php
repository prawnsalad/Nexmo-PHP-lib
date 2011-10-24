<?php

/**
 * Class NexmoMessage handles the methods and properties of sending an SMS message.
 * 
 * Usage: $var = new NexoMessage ( $account_key, $account_password );
 * Methods:
 *     sendText ( $to, $from, $message )
 *     sendBinary ( $to, $from, $body, $udh )
 *     pushWap ( $to, $from, $title, $url, $validity = 172800000 )
 *     displayOverview( $nexmo_response=null )
 *     
 *     inboundText ( $data=null )
 *     reply ( $text )
 *     
 *
 */

class NexmoMessage {

	// Nexmo account credentials
	private $nx_key = '';
	private $nx_password = '';

	/**
	 * @var string Nexmo server URI
	 *
	 * We're sticking with the JSON interface here since json
	 * parsing is built into PHP and requires no extensions.
	 * This will also keep any debugging to a minimum due to
	 * not worrying about which parser is being used.
	 */
	var $nx_uri = 'https://rest.nexmo.com/sms/json';

	
	/**
	 * @var array The most recent parsed Nexmo response.
	 */
	private $nexmo_response = '';
	

	/**
	 * @var bool If recieved an inbound message
	 */
	var $inbound_message = false;


	// Current message
	public $to = '';
	public $from = '';
	public $text = '';

	
	function NexmoMessage ($nx_key, $nx_password) {
		$this->nx_key = $nx_key;
		$this->nx_password = $nx_password;
	}



	/**
	 * Prepare new text message.
	 */
	function sendText ( $to, $from, $message ) {
	
		// Making sure strings are UTF-8 encoded
		if ( !is_numeric($from) && !mb_check_encoding($from, 'UTF-8') ) {
			trigger_error('$from needs to be a valid UTF-8 encoded string');
			return false;
		}

		if ( !mb_check_encoding($message, 'UTF-8') ) {
			trigger_error('$message needs to be a valid UTF-8 encoded string');
			return false;
		}
		
		$containsUnicode = max(array_map('ord', str_split($message))) > 127;
		
		// Make sure $from is valid
		$from = $this->validateOriginator($from);

		// URL Encode
		$from = urlencode( $from );
		$message = urlencode( $message );
		
		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'text' => $message,
			'type' => $containsUnicode ? 'unicode' : 'text'
		);
		return $this->sendRequest ( $post );
		
	}
	
	
	/**
	 * Prepare new WAP message.
	 */
	function sendBinary ( $to, $from, $body, $udh ) {
	
		//Binary messages must be hex encoded
		$body = bin2hex ( $body );
		$udh = bin2hex ( $udh );

		// Make sure $from is valid
		$from = $this->validateOriginator($from);

		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'type' => 'binary',
			'body' => $body,
			'udh' => $udh
		);
		return $this->sendRequest ( $post );
		
	}
	
	
	/**
	 * Prepare new binary message.
	 */
	function pushWap ( $to, $from, $title, $url, $validity = 172800000 ) {

		// Making sure $title and $url are UTF-8 encoded
		if ( !mb_check_encoding($title, 'UTF-8') || !mb_check_encoding($url, 'UTF-8') ) {
			trigger_error('$title and $udh need to be valid UTF-8 encoded strings');
			return false;
		}
		
		// Make sure $from is valid
		$from = $this->validateOriginator($from);

		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'type' => 'wappush',
			'url' => $url,
			'title' => $title,
			'validity' => $validity
		);
		return $this->sendRequest ( $post );
		
	}
	
	
	/**
	 * Prepare and send a new message.
	 */
	private function sendRequest ( $data ) {
	 	// Build the post data
	 	$data = array_merge($data, array('username' => $this->nx_key, 'password' => $this->nx_password));
	 	$post = '';
	 	foreach($data as $k => $v){
	 		$post .= "&$k=$v";
	 	}

		$to_nexmo = curl_init( $this->nx_uri );
		curl_setopt( $to_nexmo, CURLOPT_POST, true );
		curl_setopt( $to_nexmo, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $to_nexmo, CURLOPT_POSTFIELDS, $post );
		$from_nexmo = curl_exec( $to_nexmo );
		curl_close ( $to_nexmo );
		
		$from_nexmo = str_replace('-', '', $from_nexmo);
		
		return $this->nexmoParse( $from_nexmo );
	 
	 }
	
	
	/**
	 * Parse server response.
	 */
	private function nexmoParse ( $from_nexmo ) {
		
		$response_obj = json_decode( $from_nexmo );
		
		if ($response_obj) {
			$this->nexmo_response = $response_obj;
			return $response_obj;

		} else {
			// A malformed response
			$this->nexmo_response = array();
			return false;
		}
		
	}


	/**
	 * Validate an originator string
	 *
	 * If the originator ('from' field) is invalid, some networks may reject the network
	 * whilst stinging you with the financial cost! While this cannot correct them, it
	 * will try its best to correctly format them.
	 */
	private function validateOriginator($inp){
		// Remove any invalid characters
		$ret = preg_replace('/[^a-zA-Z0-9]/', '', (string)$inp);

		if(preg_match('/[a-zA-Z]/', $inp)){
			
			// Alphanumeric format so make sure it's < 11 chars
			$ret = substr($ret, 0, 11);

		} else {

			// Numerical, remove any prepending '00'
			if(substr($ret, 0, 2) == '00'){
				$ret = substr($ret, 2);
				$ret = substr($ret, 0, 15);
			}
		}
		
		return (string)$ret;
	}




	public function displayOverview( $nexmo_response=null ){
		$info = (!$nexmo_response) ? $this->nexmo_response : $nexmo_response;

		//How many messages were sent?
		if ( $info->messagecount > 1 ) {
		
			$start = '<p>Your message was sent in ' . $info->messagecount . ' parts ';
		
		} else {
		
			$start = '<p>Your message was sent ';
		
		}
		
		//Check each message for errors
		$error = '';
		if (!is_array($info->messages)) $info->messages = array();

		foreach ( $info->messages as $message ) {
			if ( $message->status != 0) {
				$error .= $message->errortext . ' ';
			}
		}
		
		
		//Complete parsed response
		if ( $error == '' ) {
			$complete = 'and delivered successfully.</p>';
		} else {
			$complete = 'but there was an error: ' . $error . '</p>';
		}

		return $start . $complete;
	}







	/**
	 * Inbound text methods
	 */
	

	/**
	 * Check for any inbound messages, using $_GET by default.
	 *
	 * This will set the current message to the inbound
	 * message allowing for a future reply() call.
	 */
	public function inboundText( $data=null ){
		if(!$data) $data = $_GET;

		if(!isset($data['text'], $data['msisdn'], $data['to'])) return false;

		// Get the relevant data
		$this->to = $data['to'];
		$this->from = $data['msisdn'];
		$this->text = $data['text'];

		// Flag that we have an inbound message
		$this->inbound_message = true;

		return true;
	}


	/**
	 * Reply the current message if one is set.
	 */
	public function reply ($message) {
		// Make sure we actually have a text to reply to
		if (!$this->inbound_message) {
			return false;
		}

		return $this->sendText($this->from, $this->to, $message);
	}

}