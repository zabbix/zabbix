<?php
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


class CWebUser {

	public static $data = null;

	/**
	 * Flag used to not to extend session lifetime in checkAuthentication.
	 */
	static $extend_session = true;

	/**
	 * Disable automatic session extension.
	 */
	public static function disableSessionExtension() {
		self::$extend_session = false;
	}

	/**
	 * Tries to login a user and populates self::$data on success.
	 *
	 * @param string $login     user login
	 * @param string $password  user password
	 *
	 * @throws Exception if user cannot be logged in
	 *
	 * @return bool
	 */
	public static function login(string $login, string $password): bool {
		try {
			self::$data = API::User()->login([
				'username' => $login,
				'password' => $password,
				'userData' => true
			]);

			if (!self::$data) {
				throw new Exception();
			}

			API::getWrapper()->auth = [
				'type' => CJsonRpc::AUTH_TYPE_COOKIE,
				'auth' => self::$data['sessionid']
			];

			if (self::$data['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
				error(_('GUI access disabled.'));
				throw new Exception();
			}

			if (self::$data['mfaid'] == 0 && isset(self::$data['attempt_failed']) && self::$data['attempt_failed']) {
				CProfile::init();
				CProfile::update('web.login.attempt.failed', self::$data['attempt_failed'], PROFILE_TYPE_INT);
				CProfile::update('web.login.attempt.ip', self::$data['attempt_ip'], PROFILE_TYPE_STR);
				CProfile::update('web.login.attempt.clock', self::$data['attempt_clock'], PROFILE_TYPE_INT);
				if (!CProfile::flush()) {
					return false;
				}
			}

			return true;
		}
		catch (Exception $e) {
			self::setDefault();
			return false;
		}
	}

	/**
	 * Log-out the current user.
	 */
	public static function logout(): void {
		if (API::User()->logout([])) {
			self::$data = null;
			session_destroy();
		}
	}

	public static function checkAuthentication(string $sessionid): bool {
		self::$data = API::User()->checkAuthentication([
			'sessionid' => $sessionid,
			'extend' => self::$extend_session
		]);

		return self::$data && self::$data['gui_access'] != GROUP_GUI_ACCESS_DISABLED;
	}

	/**
	 * Checks access of authenticated user to specific access rule.
	 *
	 * @param string $rule_name  Rule name.
	 *
	 * @return bool  Returns true if user has access to specified rule, false - otherwise.
	 *
	 * @throws Exception
	 */
	public static function checkAccess(string $rule_name): bool {
		if (empty(self::$data) || self::$data['roleid'] == 0) {
			return false;
		}

		return CRoleHelper::checkAccess($rule_name, self::$data['roleid']);
	}

	/**
	 * Sets user data defaults.
	 */
	public static function setDefault(): void {
		self::$data = [
			'sessionid' => CEncryptHelper::generateKey(),
			'username' => ZBX_GUEST_USER,
			'userid' => 0,
			'lang' => CSettingsHelper::getPublic(CSettingsHelper::DEFAULT_LANG),
			'theme' => CSettingsHelper::getPublic(CSettingsHelper::DEFAULT_THEME),
			'type' => 0,
			'gui_access' => GROUP_GUI_ACCESS_SYSTEM,
			'debug_mode' => false,
			'roleid' => 0,
			'autologin' => 0
		];
	}

	/**
	 * Returns the type of the current user.
	 *
	 * @return int
	 */
	public static function getType() {
		return self::$data ? self::$data['type'] : 0;
	}

	/**
	 * Returns true if debug mode is enabled.
	 *
	 * @return bool
	 */
	public static function getDebugMode() {
		return (self::$data && self::$data['debug_mode']);
	}

	/**
	 * Returns true if the current user is logged in.
	 *
	 * @return bool
	 */
	public static function isLoggedIn() {
		return (self::$data && self::$data['userid']);
	}

	/**
	 * Returns true if the user is not logged in or logged in as Guest.
	 *
	 * @return bool
	 */
	public static function isGuest() {
		return (self::$data && self::$data['username'] == ZBX_GUEST_USER);
	}

	/**
	 * Return true if guest user has access to frontend.
	 *
	 * @return bool
	 */
	public static function isGuestAllowed() {
		$guest = DB::select('users', [
			'output' => ['userid'],
			'filter' => ['username' => ZBX_GUEST_USER]
		]);

		return check_perm2system($guest[0]['userid'])
			&& getUserGuiAccess($guest[0]['userid']) != GROUP_GUI_ACCESS_DISABLED;
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
	 * Get user IP address.
	 *
	 * @return string
	 */
	public static function getIp(): string {
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Check whether user has enabled autologin.
	 *
	 * @return bool
	 */
	public static function isAutologinEnabled(): bool {
		return (CWebUser::$data['autologin'] === '1');
	}
}
