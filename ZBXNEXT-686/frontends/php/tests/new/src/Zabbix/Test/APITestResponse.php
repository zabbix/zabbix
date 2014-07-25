<?php

namespace Zabbix\Test;

class APITestResponse {
	const TYPE_EXCEPTION = 1;
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
	 * Exception data.
	 *
	 * @var string
	 */
	protected $data;

	/**
	 * Exception code.
	 *
	 * @var integer
	 */
	protected $code;

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

	public static function createTestException($message, $data = '', $code = -1, $id = null, $response = array()) {
		$responseObject = new self;

		$responseObject->message = $message;
		$responseObject->data = $data;
		$responseObject->code = $code;
		$responseObject->id = is_null($id) ? rand() : $id;
		$responseObject->type = APITestResponse::TYPE_EXCEPTION;
		$responseObject->version = isset($response['version']) ? $response['version'] : '2.0';

		return $responseObject;
	}

	/**
	 * Checks if current response is an exception.
	 *
	 * @return bool
	 */
	public function isException() {
		return $this->type == APITestResponse::TYPE_EXCEPTION;
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
	 * API result getter (if it is a plain result).
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function getResult() {
		if (!$this->isResponse()) {
			throw new \Exception('Can not return response result: I am not a plain result');
		}

		return $this->result;
	}

	/**
	 * API error message getter (if it is an exception).
	 *
	 * @throws \Exception
	 */
	public function getMessage() {
		if (!$this->isException()) {
			throw new \Exception('Can not return error message: I am not an exception');
		}

		return $this->message;
	}

	/**
	 * API error code getter (if it is an exception).
	 *
	 * @throws \Exception
	 */
	public function getCode() {
		if (!$this->isException()) {
			throw new \Exception('Can not return error code: I am not an exception');
		}

		return $this->code;
	}

	/**
	 * API error data getter (if it is an exception).
	 *
	 * @throws \Exception
	 */
	public function getData() {
		if (!$this->isException()) {
			throw new \Exception('Can not return error data: I am not an exception');
		}

		return $this->data;
	}
}
