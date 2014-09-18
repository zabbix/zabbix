<?php

namespace Zabbix\Test;

class APITestResponse {
	/**
	 * Response type, see above.
	 *
	 * @var integer
	 */
	protected $type;


	protected $result;

	protected $error;

	/**
	 * Current id.
	 *
	 * @var string
	 */
	protected $id;

	public function __construct(array $contents) {
		if (isset($contents['result'])) {
			$this->result = $contents['result'];
		}
		if (isset($contents['error'])) {
			$this->error = $contents['error'];
		}
		$this->id = $contents['id'];
	}

	/**
	 * Checks if current response is an exception.
	 *
	 * @return bool
	 */
	public function isError() {
		return (bool) $this->error;
	}

	/**
	 * API result getter (if it is a plain result).
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getResult() {
		return $this->result;
	}

	/**
	 * API error message getter (if it is an exception).
	 *
	 * @throws \Exception
	 */
	public function getError() {
		return $this->error;
	}

	public function getResponseData() {
		return $this->isError() ? $this->getError() : $this->getResult();
	}
}
