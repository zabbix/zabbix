<?php declare(strict_types = 1);
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

	/**
	 * Cookie name.
	 */
	public const COOKIE_NAME = ZBX_SESSION_NAME;

	/**
	 * Class consturctor. Set session handlers and start session.
	 */
	public function __construct() {
		if (!headers_sent() && session_status() === PHP_SESSION_NONE) {

			// Set use standard cookie PHPSESSID to false.
			ini_set('session.use_cookies', '0');
			// Set serialize method to standard serialize / unserialize.
			ini_set('session.serialize_handler', 'php_serialize');

			session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'],
				[$this, 'write'], [$this, 'destroy'], [$this, 'gc']
			);

			if (!$this->session_start()) {
				throw new \Exception(_('Session initialization error.'));
			}

			CSessionHelper::set('sessionid', CSessionHelper::getId());
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @return boolean
	 */
	public function close() {
		echo ob_get_clean();

		return true;
	}

	/**
	 * @inheritDoc
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
	 * @inheritDoc
	 *
	 * @param integer $maxlifetime
	 *
	 * @return integer
	 */
	public function gc($maxlifetime) {
		return true;
	}

	/**
	 * @inheritDoc
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
	 * @inheritDoc
	 *
	 * @param string $session_id
	 *
	 * @return string
	 */
	public function read($session_id) {
		return $this->parseData();
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $session_id
	 * @param string $session_data
	 *
	 * @return boolean
	 */
	public function write($session_id, $session_data) {
		$session_data = $this->prepareData($session_data);

		if (!CCookieHelper::set(self::COOKIE_NAME, $session_data, 0)) {
			throw new \Exception(_('Cannot set session cookie.'));
		}

		return true;
	}

	/**
	 * Run session_start.
	 *
	 * @return boolean
	 */
	protected function session_start(): bool {
		$session_data = $this->parseData();

		if (mb_strlen($session_data) === 0) {
			return session_start();
		}

		$sessionid = $this->extractSessionId($session_data);
		if ($sessionid) {
			session_id($sessionid);
		}

		return session_start();
	}

	/**
	 * Extract session id from session data.
	 *
	 * @param string $session_data
	 *
	 * @return string|null
	 */
	protected function extractSessionId(string $session_data): ?string {
		$session_data = unserialize($session_data);

		if (array_key_exists('sessionid', $session_data)) {
			return $session_data['sessionid'];
		}

		return null;
	}

	/**
	 * Prepare session data.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function prepareData(string $data): string {
		$data = unserialize($data);

		return base64_encode(serialize($data));
	}

	/**
	 * Parse session data.
	 *
	 * @return string
	 */
	protected function parseData(): string {
		if (CCookieHelper::has(self::COOKIE_NAME)) {
			return base64_decode(CCookieHelper::get(self::COOKIE_NAME));
		}

		return '';
	}
}
