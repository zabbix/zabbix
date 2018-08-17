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
	 * Flag used to not to extend session lifetime in checkAuthentication.
	 */
	static $extend_session = true;

	/**
	 * Initialize guest user session if session id is not present.
	 *
	 * @var bool
	 */
	protected static $init_guest_session = true;

	/**
	 * Disable automatic cookie setting.
	 * First checkAuthentication call (performed in initialization phase) will not be sending cookies.
	 */
	public static function disableSessionCookie() {
		self::$set_cookie = false;
	}

	/**
	 * Disable automatic session extension.
	 */
	public static function disableSessionExtension() {
		self::$extend_session = false;
	}

	/**
	 * Disable automatic fallback to guest user session initialization when no valid session were found.
	 */
	public static function disableGuestAutoLogin() {
		self::$init_guest_session = false;
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
		if ($sessionId === null && !self::$init_guest_session) {
			self::setDefault();
			return false;
		}

		try {
			if ($sessionId !== null) {
				self::$data = API::User()->checkAuthentication([
					'sessionid' => $sessionId,
					'extend' => self::$extend_session
				]);
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
	 * Return true if guest user has access to frontend.
	 *
	 * @return bool
	 */
	public static function isGuestAllowed() {
		$guest = DB::select('users', [
			'output' => ['userid'],
			'filter' => ['alias' => ZBX_GUEST_USER]
		]);

		return getUserGuiAccess($guest[0]['userid']) != GROUP_GUI_ACCESS_DISABLED;
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

	/**
	 * Returns HTTP Authentication user alias value stored in $_SERVER array.
	 *
	 * @return string
	 */
	public static function getHttpRemoteUser() {
		$http_user = '';

		foreach (['PHP_AUTH_USER', 'REMOTE_USER', 'AUTH_USER'] as $key) {
			if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
				$http_user = $_SERVER[$key];
				break;
			}
		}

		return $http_user;
	}

	/**
	 * Authenticate user using credentials passed by webserver. Success returns session id or null otherwise.
	 *
	 * @return null|string
	 */
	public static function authenticateHttpUser() {
		$sessionid = null;
		$http_user = self::getHttpRemoteUser();
		$config = $http_user ? select_config() : [];

		if ($http_user && $config['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED) {
			$parser = new CADNameAttributeParser(['strict' => true]);

			if ($parser->parse($http_user) === CParser::PARSE_SUCCESS) {
				$strip_domain = explode(',', $config['http_strip_domains']);
				$strip_domain = array_map('trim', $strip_domain);

				if ($strip_domain && in_array($parser->getDomainName(), $strip_domain)) {
					$http_user = $parser->getUserName();
				}
			}

			self::$data = API::User()->login([
				'user' => $http_user,
				'password' => '',
				'userData' => true
			]);

			$sessionid = (self::$data && array_key_exists('sessionid', self::$data)) ? self::$data['sessionid'] : null;
		}

		return $sessionid;
	}
}
