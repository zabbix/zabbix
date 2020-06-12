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
final class CCookieSession implements \SessionHandlerInterface {

	private const COOKIE_NAME = ZBX_SESSION_NAME;

	private const COOKIE_MAX_SIZE = 4096;

	/**
	 * Class consturctor. Set session handlers and start session.
	 */
	public function __construct() {
		if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
			// Set use_cookie to 0 because we manually set session id
			ini_set('session.use_cookies', 0);
			// Set serialize method because we need have abillity to encode / decode session data.
			ini_set('session.serialize_handler', 'php_serialize');

			session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'],
				[$this, 'write'], [$this, 'destroy'], [$this, 'gc']
			);

			$this->session_start();

			CSessionHelper::set('sessionid', CSessionHelper::getId());
			CSessionHelper::set('last_access', time());
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
		$data = CCookieHelper::get(self::COOKIE_NAME);

		return $data ? $this->decode($data) : '';
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
		$session_data = $this->encode($session_data);

		if (strlen($session_data) > self::COOKIE_MAX_SIZE) {
			throw new \Exception("Session too big.");
		}

		if (!CCookieHelper::set(self::COOKIE_NAME, $session_data, 0)) {
			throw new \Exception('Session cookie not set.');
		}

		return true;
	}

	private function session_start(): bool {
		if (!CCookieHelper::has(self::COOKIE_NAME)) {
			return session_start();
		}

		$cookie = $this->decode(CCookieHelper::get(self::COOKIE_NAME));
		$data = unserialize($cookie);

		if (array_key_exists('sessionid', $data)) {
			session_id($data['sessionid']);
		}

		return session_start();
	}

	private function encode(string $data): string {
		return base64_encode($data);
	}

	private function decode(string $data): string {
		return base64_decode($data);
	}
}
