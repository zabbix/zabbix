<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CWebUser {

	public static $data = null;

	/**
	 * Flag used to ignore setting authentication cookie performed checkAuthentication.
	 */
	static $set_cookie = true;

	/**
	 * Disable automatic cookie setting.
	 * First checkAuthentication call (performed in initialization phase) will not be sending cookies.
	 */
	public static function disableSessionCookie() {
		self::$set_cookie = false;
	}

	/**
	 * Tries to login a user and populates self::$data on success.
	 *
	 * @param string $login			user login
	 * @param string $password		user password
	 *
	 * @throws Exception if user cannot be logged in
	 *
	 * @return bool
	 */
	public static function login($login, $password) {
		try {
			self::setDefault();

			self::$data = API::User()->login([
				'user' => $login,
				'password' => $password,
				'userData' => true
			]);

			if (!self::$data) {
				throw new Exception();
			}

			if (self::$data['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
				error(_('GUI access disabled.'));
				throw new Exception();
			}

			$result = (bool) self::$data;

			if (isset(self::$data['attempt_failed']) && self::$data['attempt_failed']) {
				CProfile::init();
				CProfile::update('web.login.attempt.failed', self::$data['attempt_failed'], PROFILE_TYPE_INT);
				CProfile::update('web.login.attempt.ip', self::$data['attempt_ip'], PROFILE_TYPE_STR);
				CProfile::update('web.login.attempt.clock', self::$data['attempt_clock'], PROFILE_TYPE_INT);
				$result &= CProfile::flush();
			}

			// remove guest session after successful login
			$result &= DBexecute('DELETE FROM sessions WHERE sessionid='.zbx_dbstr(get_cookie(ZBX_SESSION_NAME)));

			if ($result) {
				self::setSessionCookie(self::$data['sessionid']);
			}

			return $result;
		}
		catch (Exception $e) {
			self::setDefault();
			return false;
		}
	}

	/**
	 * Log-out the current user.
	 */
	public static function logout() {
		self::$data['sessionid'] = self::getSessionCookie();
		self::$data = API::User()->logout([]);
		CSession::destroy();
		zbx_unsetcookie(ZBX_SESSION_NAME);
	}

	public static function checkAuthentication($sessionId) {
		try {
			if ($sessionId !== null) {
				self::$data = API::User()->checkAuthentication(['sessionid' => $sessionId]);
			}

			if ($sessionId === null || empty(self::$data)) {
				self::setDefault();
				self::$data = API::User()->login([
					'user' => ZBX_GUEST_USER,
					'password' => '',
					'userData' => true
				]);

				if (empty(self::$data)) {
					clear_messages(1);
					throw new Exception();
				}
				$sessionId = self::$data['sessionid'];
			}

			if (self::$data['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
				throw new Exception();
			}

			if (self::$set_cookie) {
				self::setSessionCookie($sessionId);
			}
			else {
				self::$set_cookie = true;
			}

			return $sessionId;
		}
		catch (Exception $e) {
			self::setDefault();
			return false;
		}
	}

	/**
	 * Shorthand method for setting current session ID in cookies.
	 *
	 * @param string $sessionId		Session ID string
	 */
	public static function setSessionCookie($sessionId) {
		$autoLogin = self::isGuest() ? false : (bool) self::$data['autologin'];

		zbx_setcookie(ZBX_SESSION_NAME, $sessionId,  $autoLogin ? strtotime('+1 month') : 0);
	}

	/**
	 * Retrieves current session ID from cookie named as defined in ZBX_SESSION_NAME.
	 *
	 * @return string
	 */
	public static function getSessionCookie() {
		return get_cookie(ZBX_SESSION_NAME);
	}

	public static function setDefault() {
		self::$data = [
			'alias' => ZBX_GUEST_USER,
			'userid' => 0,
			'lang' => 'en_gb',
			'type' => '0',
			'debug_mode' => false
		];
	}

	/**
	 * Returns the type of the current user.
	 *
	 * @static
	 *
	 * @return int
	 */
	public static function getType() {
		return self::$data['type'];
	}

	/**
	 * Returns true if debug mode is enabled.
	 *
	 * @return bool
	 */
	public static function getDebugMode() {
		return (self::$data['debug_mode']);
	}

	/**
	 * Returns true if the current user is logged in.
	 *
	 * @return bool
	 */
	public static function isLoggedIn() {
		return (self::$data['userid']);
	}

	/**
	 * Returns true if the user is not logged in or logged in as Guest.
	 *
	 * @return bool
	 */
	public static function isGuest() {
		return (self::$data['alias'] == ZBX_GUEST_USER);
	}

	/**
	 * Returns refresh rate in seconds.
	 *
	 * @return int
	 */
	public static function getRefresh() {
		return timeUnitToSeconds(self::$data['refresh']);
	}

	/**
	 * Returns interface language attribute value for HTML lang tag.
	 *
	 * @return string
	 */
	public static function getLang() {
		return (self::$data) ? substr(self::$data['lang'], 0, strpos(self::$data['lang'], '_')) : 'en';
	}
}
