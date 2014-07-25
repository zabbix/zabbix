<?php

namespace Zabbix\Test;

class APITestResponse {
	const TYPE_EXCEPION = 1;
	const TYPE_RESPONSE = 2;

	/**
	 * Response type, see above.
	 *
	 * @var integer
	 */
	protected $type;

	/**
	 * Current result.
	 *
	 * @var mixed
	 */
	protected $result;

	/**
	 * Current id.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * JSON-RPC version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Exception message.
	 *
	 * @var string
	 */
	protected $message;

	/**
	 * Esception data.
	 *
	 * @var string
	 */
	protected $data;

	/**
	 * @param array $result
	 * @param null $id
	 * @param array $response
	 * @return APITestResponse
	 */
	public static function createTestResponse($result = array(), $id = null, $response = array()) {
		$responseObject = new self;

		$responseObject->result = $result;
		$responseObject->id = is_null($id) ? rand() : $id;
		$responseObject->type = APITestResponse::TYPE_RESPONSE;
		$responseObject->version = isset($response['version']) ? $response['version'] : '2.0';

		return $responseObject;
	}

	/**
	 * Checks if current response is an exception.
	 *
	 * @return bool
	 */
	public function isException() {
		return $this->type == APITestResponse::TYPE_EXCEPION;
	}

	/**
	 * Checks if current response is a normal response.
	 *
	 * @return bool
	 */
	public function isResponse() {
		return $this->type == APITestResponse::TYPE_RESPONSE;
	}

	/**
	 * Returns current response type.
	 *
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * API result getter (if it is not an exception).
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getResult() {
		if (!$this->isResponse()) {
			throw new \Exception('Can not return response result: I am an exception');
		}

		return $this->result;
	}
}
