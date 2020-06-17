<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Session wrapper uses cookie for session store.
 */
class CCookieSession implements \SessionHandlerInterface {

	private const COOKIE_NAME = ZBX_SESSION_NAME;

	private const COOKIE_MAX_SIZE = 4096;

	private const SIGN_ALGO = 'aes-256-ecb';

	private $key;

	/**
	 * Class consturctor. Set session handlers and start session.
	 */
	public function __construct() {
		if (!headers_sent() && session_status() === PHP_SESSION_NONE) {

			$config = select_config();
			$this->key = $config['session_key'];

			if (!$this->key) {
				throw new \Exception('Please define session secret key'); // FIXME:
			}

			// Set use standard cookie PHPSESSID to false.
			ini_set('session.use_cookies', 0);
			// Set serialize method to standard serialize / unserialize.
			ini_set('session.serialize_handler', 'php_serialize');

			session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'],
				[$this, 'write'], [$this, 'destroy'], [$this, 'gc']
			);

			if (!$this->session_start()) {
				throw new \Exception('Cannot start session.'); // FIXME:
			}

			CSessionHelper::set('sessionid', CSessionHelper::getId());
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return boolean
	 */
	public function close() {
		echo ob_get_clean();

		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $session_id
	 *
	 * @return boolean
	 */
	public function destroy($session_id) {
		CCookieHelper::unset(self::COOKIE_NAME);

		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param integer $maxlifetime
	 *
	 * @return integer
	 */
	public function gc($maxlifetime) {
		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $save_path
	 * @param string $session_name
	 *
	 * @return boolean
	 */
	public function open($save_path, $session_name) {
		ob_start();

		return session_status() === PHP_SESSION_ACTIVE;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $session_id
	 *
	 * @return string
	 */
	public function read($session_id) {
		return $this->parseData();
	}

	/**
	 * Undocumented function
	 *
	 * @param string $session_id
	 * @param string $session_data
	 *
	 * @return boolean
	 */
	public function write($session_id, $session_data) {
		$cookies = $this->prepareData(unserialize($session_data));

		foreach ($cookies as $name => $cookie) {
			if (!CCookieHelper::set($name, $cookie, 0)) {
				throw new \Exception('Session cookie not set.'); // FIXME:
			}
		}

		return true;
	}

	private function session_start(): bool {
		$session_data = $this->parseData();

		if (mb_strlen($session_data) === 0 || !$this->checkSign($session_data)) {
			return session_start();
		}

		$session_data = unserialize($session_data);

		if (array_key_exists('sessionid', $session_data)) {
			session_id($session_data['sessionid']);
		}

		return session_start();
	}

	private function checkSign(string $data): bool {
		$data = unserialize($data);
		$session_sign = $data['sign'];
		unset($data['sign']);
		$sign = $this->sign(serialize($data));

		return hash_equals($session_sign, $sign);
	}

	private function sign(string $data): string {
		return openssl_encrypt($data, self::SIGN_ALGO, $this->key);
	}

	private function prepareData(array $data): array {
		if (array_key_exists('sign', $data)) {
			unset($data['sign']);
		}

		$data['sign'] = $this->sign(serialize($data));

		return $this->splitData($data);
	}

	private function splitData(array $data): array {
		$cookies = [];
		$offset = 0;
		$raw = base64_encode(serialize($data));
		$block = mb_substr($raw, self::COOKIE_MAX_SIZE * $offset, self::COOKIE_MAX_SIZE);

		while ($block !== false) {
			$cookies[self::COOKIE_NAME.'_'.$offset] = $block;
			$offset++;
			$block = substr($raw, self::COOKIE_MAX_SIZE * $offset, self::COOKIE_MAX_SIZE);
		}

		return $cookies;
	}

	private function parseData(): string {
		$session_data = '';
		$cookies = CCookieHelper::getAll();

		foreach ($cookies as $name => $value) {
			if (strpos($name, self::COOKIE_NAME) === 0) {
				$session_data .= $value;
			}
		}

		return base64_decode($session_data);
	}
}
