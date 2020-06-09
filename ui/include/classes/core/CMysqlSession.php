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
 * Session wrapper uses MySQL for session store.
 */
class CMysqlSession implements SessionHandlerInterface {

	/**
	 * Class consturctor. Set session handlers and start session.
	 */
	public function __construct() {
		if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
			ini_set("session.use_cookies", 0);

			session_set_save_handler([$this, 'open'], [$this, 'close'], [$this, 'read'],
				[$this, 'write'], [$this, 'destroy'], [$this, 'gc']
			);

			session_start();
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return boolean
	 */
	public function close() {
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
		// if (!headers_sent()) {
			CCookieHelper::unset('ZBX_'.$session_id);
		// }
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
		$data = CCookieHelper::get('ZBX_'.$session_id);

		return $data ? $data : '';
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
		// if (!headers_sent()) {
			CCookieHelper::set('ZBX_'.$session_id, $session_data);
		// }

		return true;
	}
}
