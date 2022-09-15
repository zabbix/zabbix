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

	const ERR_NONE = 0;
	const ERR_PHP_EXTENSION = 1;
	const ERR_SERVER_UNAVAILABLE = 2;
	const ERR_BIND_FAILED = 3;
	const ERR_BIND_ANON_FAILED = 4;
	const ERR_USER_NOT_FOUND = 5;
	const ERR_OPT_PROTOCOL_FAILED = 10;
	const ERR_OPT_TLS_FAILED = 11;
	const ERR_OPT_REFERRALS_FAILED = 12;
	const ERR_OPT_DEREF_FAILED = 13;
	const ERR_BIND_DNSTRING_UNAVAILABLE = 14;

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
		'referrals'			=> 0,
		'version'			=> 3,
		'start_tls'			=> ZBX_AUTH_START_TLS_OFF,
		'deref'				=> null
	];

	const BIND_NONE = 0;
	const BIND_ANONYMOUS = 1;
	const BIND_CONFIG_CREDENTIALS = 2;
	const BIND_DNSTRING = 3;

	/**
	 * Type of binding made to LDAP server. One of static::BIND_ constant value.
	 *
	 * @var int
	 */
	public $bound = self::BIND_NONE;

	/**
	 * @var int
	 */
	public $error = self::ERR_NONE;

	/**
	 * Estabilished LDAP connection resource or LDAP\Connection for PHP8.1.0+
	 *
	 * @var bool|resource|LDAP\Connection
	 */
	protected $ds = false;

	public function __construct($config = []) {
		if (is_array($config)) {
			$this->cnf = zbx_array_merge($this->cnf, $config);
		}

		if ($this->cnf['search_filter'] === '') {
			$this->cnf['search_filter'] = '(%{attr}=%{user})';
		}

		$this->error = $this->moduleEnabled() ? static::ERR_NONE : static::ERR_PHP_EXTENSION;
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

	/**
	 * Initialize connection to LDAP server. Set LDAP connection options defined in configuration when required.
	 * Return true on success.
	 *
	 * @return bool
	 */
	public function connect(): bool {
		$this->error = static::ERR_NONE;

		if ($this->ds) {
			return true;
		}

		$this->bound = static::BIND_NONE;

		if (!$this->ds = @ldap_connect($this->cnf['host'], $this->cnf['port'])) {
			$this->error = static::ERR_SERVER_UNAVAILABLE;

			return false;
		}

		// Set protocol version and dependent options.
		if ($this->cnf['version']) {
			if (!@ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, $this->cnf['version'])) {
				$this->error = static::ERR_OPT_PROTOCOL_FAILED;
			}
			elseif ($this->cnf['version'] == 3) {
				if ($this->cnf['start_tls'] && !@ldap_start_tls($this->ds)) {
					$this->error = static::ERR_OPT_TLS_FAILED;
				}

				if (!$this->cnf['referrals']
						&& !@ldap_set_option($this->ds, LDAP_OPT_REFERRALS, $this->cnf['referrals'])) {
					$this->error = static::ERR_OPT_REFERRALS_FAILED;
				}
			}
		}

		if (isset($this->cnf['deref']) && !@ldap_set_option($this->ds, LDAP_OPT_DEREF, $this->cnf['deref'])) {
			$this->error = static::ERR_OPT_DEREF_FAILED;
		}

		return !$this->error;
	}

	/**
	 * Bind to LDAP server. Set $this->bound to type of binding when bind successfull.
	 * Arguments $user and $password are required when configuration is set to bind with BIND_DNSTRING.
	 *
	 * Bind types:
	 * BIND_CONFIG_CREDENTIALS - Special configuration user is used to bind and search.
	 * BIND_ANONYMOUS          - Anonymous user is used to bind and search.
	 * BIND_DNSTRING           - Loging in user is used to bind and search.
	 *
	 * Both arguments $user and $password are required for bind type BIND_DNSTRING only.
	 *
	 * @param string $user      User name value.
	 * @param string $password  Password value.
	 *
	 * @return bool
	 */
	public function bind($user = null, $password = null): bool {
		$placeholders = ['%{user}' => $user];
		$bind = $this->getBindConfig();

		if ($bind['bind_type'] == static::BIND_DNSTRING) {
			$bind['dn'] = $this->makeFilter($bind['dn'], $placeholders, LDAP_ESCAPE_DN);
			$bind['dn_password'] = $password;
		}

		if ($bind['bind_type'] == static::BIND_ANONYMOUS) {
			if (!@ldap_bind($this->ds)) {
				$this->error = static::ERR_BIND_ANON_FAILED;

				return false;
			}
		}
		else {
			if (!@ldap_bind($this->ds, $bind['dn'], $bind['dn_password'])) {
				$this->error = static::ERR_BIND_FAILED;

				return false;
			}
		}

		$this->bound = $bind['bind_type'];

		return true;
	}

	/**
	 * Check validity of user credentials. Do not allow to check credentials when password is empty.
	 *
	 * @param string $user  User name attribute value.
	 * @param string $pass  User password attribute value
	 */
	public function checkCredentials(string $user, string $pass): bool {
		if (!$pass) {
			$this->error = static::ERR_USER_NOT_FOUND;

			return false;
		}

		if (!$this->connect() || !$this->bind($user, $pass)) {
			return false;
		}

		if ($this->bound == static::BIND_ANONYMOUS || $this->bound == static::BIND_CONFIG_CREDENTIALS) {
			// No need for user default attributes, only 'dn'.
			$users = $this->getUserAttributes($user, ['dn']);

			if (array_key_exists('count', $users) && $users['count'] == 1) {
				$user = $users[0];
			}
			else {
				// Multiple users matched criteria.
				$this->error = static::ERR_USER_NOT_FOUND;

				return false;
			}

			if (!array_key_exists('dn', $user) || !@ldap_bind($this->ds, $user['dn'], $pass)) {
				$this->error = static::ERR_BIND_FAILED;

				return false;
			}
		}

		return true;
	}

	/**
	 * Get user data with specified attributes. Is not available for bind type BIND_DNSTRING
	 * if password is not supplied.
	 *
	 * @param array  $attributes  Array of LDAP tree attributes names to be returned.
	 * @param string $user        User to search attributes for.
	 * @param string $password    User password, is required only for BIND_DNSTRING.
	 */
	public function getUserData(array $attributes, string $user, $password = null) {
		$bind_config = $this->getBindConfig();

		if ($bind_config['bind_type'] == static::BIND_DNSTRING && $password === null) {
			$this->error = static::ERR_BIND_DNSTRING_UNAVAILABLE;

			return [];
		}

		$this->bind($user, $password);
		$results = $this->getUserAttributes($user, $attributes);

		return $this->getFormattedResult($results[0], $attributes);
	}


	/**
	 * Get configuration required to binding.
	 *
	 * @return array
	 *         ['bind_type']    Type of binding according service configuration.
	 *         ['dn']           Base DN string, is set for all types except BIND_ANONYMOUS
	 *         ['dn_password']  Password for binding, is set for BIND_CONFIG_CREDENTIALS only
	 *                          BIND_DNSTRING will require user password.
	 */
	protected function getBindConfig(): array {
		$config = [
			'bind_type' => static::BIND_ANONYMOUS
		];

		if ($this->cnf['bind_dn'] !== '' && $this->cnf['bind_password'] !== '') {
			$config = [
				'bind_type' => static::BIND_CONFIG_CREDENTIALS,
				'dn' => $this->cnf['bind_dn'],
				'dn_password' => $this->cnf['bind_password']
			];
		}
		elseif ($this->cnf['bind_dn'] !== '' && $this->cnf['search_filter'] !== '(%{attr}=%{user})') {
			$config = [
				'bind_type' => static::BIND_DNSTRING,
				'dn' => $this->cnf['bind_dn']
			];
		}
		elseif (strpos($this->cnf['base_dn'], '%{user}') !== false) {
			$config = [
				'bind_type' => static::BIND_DNSTRING,
				'dn' => $this->cnf['base_dn']
			];
		}

		return $config;
	}

	/**
	 * Get user attributes data as associative array.
	 *
	 * @param string $user        Username to get data for.
	 * @param array  $attributes  List of attributes to be returned.
	 *
	 * @return array
	 */
	protected function getUserAttributes($user, array $attributes) {
		$info = ['%{user}' => $user];
		$base = $this->makeFilter($this->cnf['base_dn'], $info, LDAP_ESCAPE_DN);
		$filter = $this->makeFilter($this->cnf['search_filter'], $info, LDAP_ESCAPE_FILTER);
		$resource = @ldap_search($this->ds, $base, $filter, $attributes);
		$results = $resource !== false ? @ldap_get_entries($this->ds, $resource) : [];

		ldap_free_result($resource);

		return $results;
	}

	/**
	 * Get associative array of desired attributes from ldap_search response entry.
	 * Only existing attributes will be defined in resulting array.
	 *
	 * @param array $search_result  Single array from arrays returned by ldap_search.
	 * @param array $attributes     Desired attributes list.
	 *
	 * @return array
	 */
	protected function getFormattedResult(array $search_result, array $attributes): array {
		$result = [];

		foreach ($attributes as $key) {
			if (!array_key_exists($key, $search_result)) {
				continue;
			}

			if (is_array($search_result[$key])) {
				unset($search_result[$key]['count']);
			}

			$result[$key] = $search_result[$key];
		}

		return $result;
	}

	/**
	 * Replaces placeholders found in string with their data.
	 *
	 * @param string $filter         Filter string where to replace placeholders.
	 * @param array  $placeholders   Associative array for replacement in $filter string.
	 *                               Placeholders %{attr}, %{host} will be added by default,
	 *                               array key should be in form %{placeholder_key_value}.
	 * @param int    escape_context  Resulting string usage context:
	 *                                 LDAP_ESCAPE_FILTER - use result string as filter argument of ldap_search.
	 *                                 LDAP_ESCAPE_DN     - use result string as base dn.
	 *
	 * @return string
	 */
	protected function makeFilter(string $filter, array $placeholders, $escape_context): string {
		$replace_pairs = $placeholders + [
			'%{attr}'	=> $this->cnf['search_attribute'],
			'%{host}'	=> $this->cnf['host']
		];

		foreach ($replace_pairs as &$value) {
			$value = ldap_escape($value, '', $escape_context);
		}
		unset($value);

		return strtr($filter, $replace_pairs);
	}
}
