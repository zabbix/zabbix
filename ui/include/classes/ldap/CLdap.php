<?php
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


class CLdap {

	const ERR_PHP_EXTENSION = 1;
	const ERR_SERVER_UNAVAILABLE = 2;
	const ERR_BIND_FAILED = 3;
	const ERR_BIND_ANON_FAILED = 4;
	const ERR_USER_NOT_FOUND = 5;
	const ERR_OPT_PROTOCOL_FAILED = 10;
	const ERR_OPT_TLS_FAILED = 11;
	const ERR_OPT_REFERRALS_FAILED = 12;
	const ERR_OPT_DEREF_FAILED = 13;

	/**
	 * @var array $cnf  LDAP connection settings.
	 */
	private $cnf = [
		'host'				=> '',
		'port'				=> '',
		'bind_dn'			=> '',
		'bind_password'		=> '',
		'base_dn'			=> '',
		'search_attribute'	=> '',
		'search_filter'		=> '',
		'groupkey'			=> 'cn',
		'mapping'			=> [
			'username' => 'uid',
			'userid' => 'uidnumbera',
			'passwd' => 'userpassword'
		],
		'referrals'			=> 0,
		'version'			=> 3,
		'start_tls'			=> ZBX_AUTH_START_TLS_OFF,
		'deref' => null
	];

	/**
	 * @var int
	 */
	public $error;

	public function __construct($arg = []) {
		$this->ds = false;
		$this->info = [];

		if (is_array($arg)) {
			$this->cnf = zbx_array_merge($this->cnf, $arg);
		}

		if ($this->cnf['search_filter'] === '') {
			$this->cnf['search_filter'] = '(%{attr}=%{user})';
		}

		$this->error = $this->moduleEnabled() ? 0 : static::ERR_PHP_EXTENSION;
	}

	/**
	 * Check is the PHP extension enabled.
	 *
	 * @return bool
	 */
	public function moduleEnabled() {
		return function_exists('ldap_connect') && function_exists('ldap_set_option') && function_exists('ldap_bind')
			&& function_exists('ldap_search') && function_exists('ldap_get_entries')
			&& function_exists('ldap_free_result') && function_exists('ldap_start_tls');
	}

	public function connect() {
		$this->error = 0;

		// connection already established
		if ($this->ds) {
			return true;
		}

		$this->bound = 0;

		if (!$this->ds = @ldap_connect($this->cnf['host'], $this->cnf['port'])) {
			$this->error = static::ERR_SERVER_UNAVAILABLE;

			return false;
		}

		// Set protocol version and dependent options.
		if ($this->cnf['version']) {
			if (!@ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, $this->cnf['version'])) {
				$this->error = static::ERR_OPT_PROTOCOL_FAILED;
			}
			else {
				// use TLS (needs version 3)
				if ($this->cnf['start_tls'] && !@ldap_start_tls($this->ds)) {
					$this->error = static::ERR_OPT_TLS_FAILED;
				}

				// needs version 3
				if (!zbx_empty($this->cnf['referrals'])
						&& !@ldap_set_option($this->ds, LDAP_OPT_REFERRALS, $this->cnf['referrals'])) {
					$this->error = static::ERR_OPT_REFERRALS_FAILED;
				}
			}
		}

		// set deref mode
		if (isset($this->cnf['deref']) && !@ldap_set_option($this->ds, LDAP_OPT_DEREF, $this->cnf['deref'])) {
			$this->error = static::ERR_OPT_DEREF_FAILED;
		}

		return !$this->error;
	}

	public function checkPass($user, $pass) {
		if (!$pass) {
			$this->error = static::ERR_USER_NOT_FOUND;

			return false;
		}

		if (!$this->connect()) {
			return false;
		}

		$dn = null;

		// indirect user bind
		if (!empty($this->cnf['bind_dn']) && !empty($this->cnf['bind_password'])) {
			// use superuser credentials
			if (!@ldap_bind($this->ds, $this->cnf['bind_dn'], $this->cnf['bind_password'])) {
				$this->error = static::ERR_BIND_FAILED;

				return false;
			}

			$this->bound = 2;
		}
		elseif (!empty($this->cnf['bind_dn']) && !empty($this->cnf['base_dn']) && !empty($this->cnf['search_filter'])) {
			// special bind string
			$dn = $this->makeFilter($this->cnf['bind_dn'], ['user' => $user, 'host' => $this->cnf['host']]);
		}
		elseif (strpos($this->cnf['base_dn'], '%{user}')) {
			// direct user bind
			$dn = $this->makeFilter($this->cnf['base_dn'], ['user' => $user, 'host' => $this->cnf['host']]);
		}
		else {
			// anonymous bind
			if (!@ldap_bind($this->ds)) {
				$this->error = static::ERR_BIND_ANON_FAILED;

				return false;
			}
		}

		// try to bind to with the dn if we have one.
		if ($dn) {
			// user/password bind
			if (!@ldap_bind($this->ds, $dn, $pass)) {
				$this->error = static::ERR_USER_NOT_FOUND;

				return false;
			}

			$this->bound = 1;
		}
		else {
			// see if we can find the user
			$this->info = $this->getUserData($user);

			if (empty($this->info['dn'])) {
				return false;
			}
			else {
				$dn = $this->info['dn'];
			}

			// try to bind with the dn provided
			if (!@ldap_bind($this->ds, $dn, $pass)) {
				$this->error = static::ERR_USER_NOT_FOUND;

				return false;
			}

			$this->bound = 1;
		}

		return true;
	}

	private function getUserData($user) {
		if (!$this->connect()) {
			return false;
		}

		// force superuser bind if wanted and not bound as superuser yet
		if (!empty($this->cnf['bind_dn']) && !empty($this->cnf['bind_password']) && ($this->bound < 2)) {
			if (!@ldap_bind($this->ds, $this->cnf['bind_dn'], $this->cnf['bind_password'])) {
				$this->error = static::ERR_BIND_FAILED;

				return false;
			}
			$this->bound = 2;
		}

		// with no superuser creds we continue as user or anonymous here
		$info['user'] = $user;
		$info['host'] = $this->cnf['host'];

		// get info for given user
		$base = $this->makeFilter($this->cnf['base_dn'], $info);
		$filter = $this->makeFilter($this->cnf['search_filter'], $info);
		$sr = @ldap_search($this->ds, $base, $filter);
		$result = $sr !== false ? @ldap_get_entries($this->ds, $sr) : [];

		// don't accept more or less than one response
		if (!$result || $result['count'] != 1) {
			$this->error = $result ? static::ERR_USER_NOT_FOUND : static::ERR_BIND_FAILED;

			return false;
		}

		$user_result = $result[0];
		ldap_free_result($sr);

		// general user info
		$info['dn'] = $user_result['dn'];
		$info['name'] = $user_result['cn'][0];
		$info['grps'] = [];

		// overwrite if other attributes are specified.
		if (is_array($this->cnf['mapping'])) {
			foreach ($this->cnf['mapping'] as $localkey => $key) {
				$info[$localkey] = isset($user_result[$key])?$user_result[$key][0]:null;
			}
		}
		$user_result = zbx_array_merge($info,$user_result);

		return $info;
	}

	private function makeFilter($filter, $placeholders) {
		$placeholders['attr'] = $this->cnf['search_attribute'];
		preg_match_all("/%{([^}]+)/", $filter, $matches, PREG_PATTERN_ORDER);

		// replace each match
		foreach ($matches[1] as $match) {
			// take first element if array
			if (is_array($placeholders[$match])) {
				$value = $placeholders[$match][0];
			}
			else {
				$value = $placeholders[$match];
			}
			$filter = str_replace('%{'.$match.'}', $value, $filter);
		}
		return $filter;
	}
}
