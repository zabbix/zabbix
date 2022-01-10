<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
class CCookieSession implements SessionHandlerInterface {

	/**
	 * Cookie name.
	 */
	public const COOKIE_NAME = ZBX_SESSION_NAME;

	/**
	 * Class constructor. Set session handlers and start session.
	 */
	public function __construct() {
		// Set use standard cookie PHPSESSID to false.
		ini_set('session.use_cookies', '0');

		session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'], [$this, 'write'],
			[$this, 'destroy'], [$this, 'gc']
		);
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
	 * @param string $session_id
	 * @param string $session_data
	 *
	 * @return boolean
	 */
	public function write($session_id, $session_data) {
		session_decode($session_data);
		$session_data = $this->prepareData(CSessionHelper::getAll());

		if (!CCookieHelper::set(self::COOKIE_NAME, $session_data, $this->isAutologinEnabled() ? time() + SEC_PER_MONTH : 0)) {
			throw new \Exception(_('Cannot set session cookie.'));
		}

		return true;
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

	protected function isAutologinEnabled(): bool {
		return (CWebUser::$data['autologin'] === '1');
	}
}
