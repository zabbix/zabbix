<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Session wrapper uses cookie for session store.
 */
class CCookieSession implements SessionHandlerInterface {

	/**
	 * Cookie name.
	 */
	public const COOKIE_NAME = ZBX_SESSION_NAME;

	/**
	 * Cookie lifetime.
	 */
	public $lifetime = 0;

	/**
	 * Class constructor. Set session handlers and start session.
	 */
	public function __construct() {
		// Set use standard cookie PHPSESSID to false.
		ini_set('session.use_cookies', '0');

		session_set_save_handler($this, true);
	}

	/**
	 * @inheritDoc
	 *
	 * @return boolean
	 */
	public function close(): bool {
		echo ob_get_clean();

		return true;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $id
	 *
	 * @return boolean
	 */
	public function destroy($id): bool {
		return CCookieHelper::unset(self::COOKIE_NAME);
	}

	/**
	 * @inheritDoc
	 *
	 * @param integer $maxlifetime
	 *
	 * @return integer
	 */
	#[\ReturnTypeWillChange]
	public function gc($maxlifetime) {
		return true;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $path
	 * @param string $name
	 *
	 * @return boolean
	 */
	public function open($path, $name): bool {
		ob_start();

		return session_status() === PHP_SESSION_ACTIVE;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function read($id) {
		$session_data = json_decode($this->parseData(), true);

		if (!is_array($session_data)) {
			return '';
		}

		foreach ($session_data as $key => $value) {
			CSessionHelper::set($key, $value);
		}

		return session_encode();
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $id
	 * @param string $data
	 *
	 * @return boolean
	 */
	public function write($id, $data): bool {
		session_decode($data);
		$data = $this->prepareData(CSessionHelper::getAll());

		return CCookieHelper::set(self::COOKIE_NAME, $data, $this->lifetime);
	}

	/**
	 * Run session_start.
	 *
	 * @param string $sessionid
	 *
	 * @return boolean
	 */
	public function session_start(string $sessionid): bool {
		if (headers_sent() || session_status() !== PHP_SESSION_NONE) {
			return false;
		}

		session_id($sessionid);

		return session_start();
	}

	/**
	 * Extract session id from session data.
	 *
	 * @return string|null
	 */
	public function extractSessionId(): ?string {
		$session_data = $this->parseData();

		if ($session_data === '') {
			return null;
		}

		$session_data = json_decode($session_data, true);

		if (!is_array($session_data) || !array_key_exists('sessionid', $session_data)) {
			return null;
		}

		return $session_data['sessionid'];
	}

	/**
	 * Prepare session data.
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	protected function prepareData(array $data): string {
		return base64_encode(json_encode($data));
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
