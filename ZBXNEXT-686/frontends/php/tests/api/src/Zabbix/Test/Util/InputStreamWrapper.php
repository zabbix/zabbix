<?php

namespace Zabbix\Test\Util;

class InputStreamWrapper {
	protected static $data;
	protected static $length;
	protected static $position = 0;

	public function stream_open($path, $mode, $options, &$opened_path) {
		if ($path !== 'php://input') {
			throw new \Exception('Sorry, we support nothing but php://input at the moment');
		}

		self::$position = 0;

		return true;
	}

	public function stream_write($data) {
		self::$data = $data;

		self::$length = strlen($data);

		return self::$length;
	}

	public function stream_read($index) {
		// check this code for really long data
		$chunk = min($index, self::$length - self::$position);

		$data = substr(self::$data, self::$position, $chunk);

		self::$position += $chunk;

		return $data;
	}

	public function stream_stat() {
		return array();
	}

	public function stream_eof() {
		return self::$position >= self::$length;
	}
}
