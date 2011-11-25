<?php



class NexmoReceipt {

	const STATUS_DELIVERED = 'DELIVERED';
	const STATUS_EXPIRED = 'EXPIRED';
	const STATUS_FAILED = 'FAILED';
	const STATUS_BUFFERED = 'BUFFERED';

	public $from = '';
	public $to = '';
	public $network = '';
	public $id = '';
	public $status = '';
	public $received_time = 0;

	public $found = false;

	public function __construct ($data = false) {
		if (!$data) $data = $_GET;

		if (!isset($data['msisdn'], $data['network-code'], $data['messageId'])) {
			return;
		}

		// Flag that a receipt was found
		$this->found = true;

		// Get the relevant data
		$this->to = $data['msisdn'];
		$this->from = $data['to'];
		$this->network = $data['network-code'];
		$this->id = $data['messageId'];
		$this->status = strtoupper($data['status']);

		// Format the date into timestamp
		$dp = date_parse_from_format('ymdGi', $data['scts']);
		$this->received_time = mktime($dp['hour'], $dp['minute'], $dp['second'], $dp['month'], $dp['day'], $dp['year']);
	}


	public function exists () {
		return $this->found;
	}
}