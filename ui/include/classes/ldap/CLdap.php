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


class CLdap {

	const BIND_NONE = 0;
	const BIND_ANONYMOUS = 1;
	const BIND_CONFIG_CREDENTIALS = 2;
	const BIND_DNSTRING = 3;

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
	const ERR_QUERY_FAILED = 15;

	const DEFAULT_FILTER_USER = '(%{attr}=%{user})';
	const DEFAULT_FILTER_GROUP = '(%{groupattr}=%{user})';
	const DEFAULT_MEMBERSHIP_ATTRIBUTE = 'memberOf';

	/**
	 * Type of binding made to LDAP server. One of static::BIND_ constant value.
	 *
	 * @var int
	 */
	public $bound;

	/**
	 * @var int
	 */
	public $error;

	/**
	 * Bind type to use when searching in LDAP tree. One of static::BIND_ constant value.
	 *
	 * @var int
	 */
	public $bind_type;

	/**
	 * Bind DN string, may contain placeholders when BIND_TYPE_DNSTRING is detected.
	 *
	 * @var string
	 */
	protected $bind_dn;

	/**
	 * @var array $cnf  LDAP connection settings.
	 */
	protected $cnf = [
		'host'				=> '',
		'port'				=> '',
		'bind_dn'			=> '',
		'bind_password'		=> '',
		'base_dn'			=> '',
		'search_attribute'	=> '',
		'search_filter'		=> '',
		'group_basedn'		=> '',
		'group_name'		=> '',
		'group_member'		=> '',
		'group_filter'		=> '',
		'group_membership'	=> '',
		'referrals'			=> 0,
		'version'			=> 3,
		'start_tls'			=> ZBX_AUTH_START_TLS_OFF,
		'deref'				=> null
	];

	/**
	 * Placeholders with value used for building bind or search filter query.
	 * Key is placeholder name, %{attr} and value is placeholder value to replace to.
	 *
	 * @var array
	 */
	protected $placeholders = [];

	/**
	 * Established LDAP connection resource, for PHP8.1.0+ LDAP\Connection class instance.
	 *
	 * @var bool|resource|LDAP\Connection
	 */
	protected $ds;

	public function __construct(array $config = []) {
		$this->ds = false;
		$this->bound = static::BIND_NONE;
		$this->error = static::ERR_NONE;

		$this->cnf = zbx_array_merge($this->cnf, $config);

		if ($this->cnf['search_filter'] === '') {
			$this->cnf['search_filter'] = static::DEFAULT_FILTER_USER;
		}

		if ($this->cnf['group_filter'] === '') {
			$this->cnf['group_filter'] = static::DEFAULT_FILTER_GROUP;
		}

		if ($this->cnf['group_membership'] === '') {
			$this->cnf['group_membership'] = static::DEFAULT_MEMBERSHIP_ATTRIBUTE;
		}

		$this->initBindAttributes();
		$this->error = $this->moduleEnabled() ? static::ERR_NONE : static::ERR_PHP_EXTENSION;
	}

	/**
	 * Check is the PHP extension enabled.
	 *
	 * @return bool
	 */
	public function moduleEnabled(): bool {
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

		if ($this->ds !== false) {
			return true;
		}

		$this->bound = static::BIND_NONE;

		$uri = $this->cnf['ldap_start_tls'] === ZBX_AUTH_START_TLS_OFF
			? printf('ldap://%s:%s', $this->cnf['host'], $this->cnf['port'])
			: printf('ldaps://%s:%s', $this->cnf['host'], $this->cnf['port']);

		if (!$this->ds = @ldap_connect($uri)) {
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

		return $this->error == static::ERR_NONE;
	}

	/**
	 * Bind to LDAP server. Set $this->bound to type of successful binding.
	 * Arguments $user and $password are required when bind type BIND_DNSTRING is set.
	 *
	 * Bind types:
	 * BIND_CONFIG_CREDENTIALS - Special configuration user is used to bind and search.
	 * BIND_ANONYMOUS          - Anonymous user is used to bind and search.
	 * BIND_DNSTRING           - Logging in user is used to bind and search.
	 *
	 * Both arguments $user and $password are required for bind type BIND_DNSTRING only.
	 *
	 * @param string $user      User name value.
	 * @param string $password  Password value.
	 *
	 * @return bool
	 */
	public function bind($user = null, $password = null): bool {
		$this->bound = static::BIND_NONE;

		if ($this->bind_type == static::BIND_ANONYMOUS) {
			if (!@ldap_bind($this->ds)) {
				$this->error = static::ERR_BIND_ANON_FAILED;

				return false;
			}

			$this->bound = static::BIND_ANONYMOUS;

			return true;
		}

		$dn = $this->bind_dn;
		$dn_password = $this->cnf['bind_password'];

		if ($this->bind_type == static::BIND_DNSTRING) {
			if ($user === null || $password === null) {
				$this->error = static::ERR_BIND_DNSTRING_UNAVAILABLE;

				return false;
			}

			$dn = $this->makeFilter($this->bind_dn, ['%{user}' => $user], LDAP_ESCAPE_DN);
			$dn_password = $password;
		}

		if (!@ldap_bind($this->ds, $dn, $dn_password)) {
			$this->error = static::ERR_BIND_FAILED;

			return false;
		}

		$this->bound = $this->bind_type;

		return true;
	}

	/**
	 * Check validity of user credentials. Do not allow to check credentials when password is empty.
	 *
	 * @param string $user  User name attribute value.
	 * @param string $pass  User password attribute value.
	 *
	 * @return bool
	 */
	public function checkCredentials(string $user, string $pass): bool {
		if (!$pass) {
			$this->error = static::ERR_USER_NOT_FOUND;

			return false;
		}

		if (!$this->connect() || !$this->bind($user, $pass)) {
			return false;
		}

		if (!$this->bind($user, $pass)) {
			if ($this->bind_type == static::BIND_DNSTRING) {
				$this->error = static::ERR_USER_NOT_FOUND;
			}

			return false;
		}

		if ($this->bound == static::BIND_ANONYMOUS || $this->bound == static::BIND_CONFIG_CREDENTIALS) {
			// No need for user default attributes, only 'dn'.
			$users = $this->search($this->cnf['base_dn'], $this->cnf['search_filter'], ['%{user}' => $user], ['dn']);

			if ($users['count'] != 1) {
				// Multiple users matched criteria.
				$this->error = static::ERR_USER_NOT_FOUND;

				return false;
			}

			if (!array_key_exists('dn', $users[0]) || !@ldap_bind($this->ds, $users[0]['dn'], $pass)) {
				$this->error = static::ERR_USER_NOT_FOUND;

				return false;
			}
		}

		return true;
	}

	/**
	 * Get array of user groups. Is not available for bind type BIND_DNSTRING if password is not supplied.
	 *
	 * @param array  $attributes  Array of group attributes to return for every group.
	 * @param string $user        User username value.
	 * @param string $password    User password value, is required only for BIND_DNSTRING.
	 *
	 * @return array Array of arrays of matched group.
	 */
	public function getGroupAttributes(array $attributes, string $user, $password = null): array {
		if ($attributes == [] || !$this->connect() || !$this->bind($user, $password)) {
			return [];
		}

		$placeholders = [
			'%{user}'	=> $user,
			'%{groupattr}'	=> $this->cnf['group_member']
		];
		$results = $this->search($this->cnf['group_basedn'], $this->cnf['group_filter'], $placeholders, $attributes);
		$groups = [];

		if ($results['count'] == 0) {
			return $groups;
		}

		$attributes = array_flip(array_map('strtolower', $attributes));

		for ($j = 0; $j < $results['count']; $j++) {
			$result = $results[$j];
			$result_attributes = array_intersect_key($result, $attributes);

			if (!$result_attributes) {
				continue;
			}

			foreach ($result_attributes as &$value) {
				$value = $value[0];
			}
			unset($value);

			$groups[] = $result_attributes;
		}

		return $groups;
	}

	/**
	 * Get user data with specified attributes. Not available for bind type BIND_DNSTRING if password is not supplied.
	 * Mapped attribute names will be set to lower case.
	 *
	 * @param array  $attributes  Array of LDAP tree attributes names to be returned.
	 * @param string $user        User to search attributes for.
	 * @param string $password    (optional) User password, required only for BIND_DNSTRING.
	 *
	 * @return array Associative array of user attributes.
	 */
	public function getUserAttributes(array $attributes, string $user, $password = null): array {
		if ($attributes == [] || !$this->connect() || !$this->bind($user, $password)) {
			return [];
		}

		$placeholders = ['%{user}' => $user];
		$results = $this->search($this->cnf['base_dn'], $this->cnf['search_filter'], $placeholders, $attributes);
		$user = [];

		if ($results['count'] == 0) {
			return $user;
		}

		$results = $results[0];

		if ($results['count'] == 0) {
			return $user;
		}

		$group_key = strtolower($this->cnf['group_membership']);
		$group_name_key = strtolower($this->cnf['group_name']);

		for ($i = 0; $i < $results['count']; $i++) {
			$key = $results[$i];

			$user[$key] = $results[$i] === $group_key
				? $this->getGroupPatternsFromDns($group_name_key, $results[$key])
				: $results[$key][0];
		}

		return $user;
	}

	/**
	 * Extract the group pattern from given DN strings.
	 * For DN string "cn=zabbix-admins,ou=Group,dc=example,dc=org" and the "Group name attribute" set to "cn",
	 * the string "zabbix-admins" will be stored to the $groups array.
	 *
	 * @param string $group_name_key  Lower case group name attribute for which to extract value from RDN.
	 * @param array  $group_dns       Array of DN strings.
	 *
	 * @return array Strings with the extracted groups, if any.
	 */
	public function getGroupPatternsFromDns(string $group_name_key, array $group_dns): array {
		$groups = [];

		foreach ($group_dns as $group_dn) {
			$rdns = ldap_explode_dn($group_dn, 0);

			if (!is_array($rdns)) {
				continue;
			}

			foreach ($rdns as $rdn) {
				if (strpos($rdn, '=') === false) {
					continue;
				}

				/*
				 * For multi-value RDNs $rdn_key will be set to key of first key-value pair, the rest of string as value.
				 * For example for RDN "cn=John Doe+mail=jdoe@example.com" $rdn_value is "John Doe+mail=jdoe@example.com".
				 */
				[$rdn_key, $rdn_value] = explode('=', $rdn, 2);

				if (strtolower($rdn_key) !== $group_name_key) {
					continue;
				}

				// Convert escaped charcodes, f.e. 'Universit\C4\81te' => 'Universitāte'.
				$groups[] = preg_replace_callback('/\\\\([0-9A-F]{2})/i', function (array $match): string {
					return chr(hexdec($match[1]));
				}, $rdn_value);
			}
		}

		return $groups;
	}

	/**
	 * Setter for additional placeholders supported in bind or search query.
	 *
	 * @param array $placeholders  Associative array where key is placeholder in form %{name}.
	 */
	public function setQueryPlaceholders(array $placeholders) {
		$this->placeholders = $placeholders;
	}

	/**
	 * Return user data with medias, groups, roleid and user attributes matched from LDAP user data according
	 * provisioning options. All attributes are matched in case insensitive way.
	 *
	 * @param CProvisioning $provisioning      Provisioning class instance.
	 * @param string        $username          Username of user to get provisioned data for.
	 *
	 * @return array
	 */
	public function getProvisionedData(CProvisioning $provisioning, string $username): array {
		$ldap_groups = [];
		$user = [
			'medias' => [],
			'usrgrps' => [],
			'roleid' => 0
		];
		$config = $provisioning->getIdpConfig();
		$user_attributes = $provisioning->getUserIdpAttributes();
		$idp_user = $this->getUserAttributes($user_attributes, $username);
		$user = $provisioning->getUserAttributes($idp_user, false);
		$user['medias'] = $provisioning->getUserMedias($idp_user, false);

		if ($config['group_membership'] !== '') {
			$group_key = strtolower($config['group_membership']);

			if (array_key_exists($group_key, $idp_user) && is_array($idp_user[$group_key])) {
				$ldap_groups = $idp_user[$group_key];
			}
		}
		else if ($config['group_filter'] !== '') {
			$user_ref_attr = strtolower($config['user_ref_attr']);

			if ($user_ref_attr !== '' && array_key_exists($user_ref_attr, $idp_user)) {
				$this->setQueryPlaceholders(['%{ref}' => $idp_user[$user_ref_attr]]);
			}

			$group_attributes = $provisioning->getGroupIdpAttributes();
			$ldap_groups = $this->getGroupAttributes($group_attributes, $username);
			$ldap_groups = array_column($ldap_groups, strtolower($config['group_name']));
		}

		$user = array_merge($user, $provisioning->getUserGroupsAndRole($ldap_groups));

		return $user;
	}

	/**
	 * Setup bind attributes according LDAP configuration.
	 */
	protected function initBindAttributes() {
		$this->bind_type = static::BIND_ANONYMOUS;

		if ($this->cnf['bind_dn'] !== '' && $this->cnf['bind_password'] !== '') {
			$this->bind_type = static::BIND_CONFIG_CREDENTIALS;
			$this->bind_dn = $this->cnf['bind_dn'];
		}
		elseif ($this->cnf['bind_dn'] !== '' && $this->cnf['search_filter'] !== static::DEFAULT_FILTER_USER) {
			$this->bind_type = static::BIND_DNSTRING;
			$this->bind_dn = $this->cnf['bind_dn'];
		}
		elseif (strpos($this->cnf['base_dn'], '%{user}') !== false) {
			$this->bind_type = static::BIND_DNSTRING;
			$this->bind_dn = $this->cnf['base_dn'];
		}
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
	 * @return string Filter string with replaced placeholders in it.
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

	/**
	 * Search for entry in LDAP tree for specified $dn and $filter.
	 * Requested attributes in resulting array, will be set in lowercase.
	 *
	 * @param string $dn            DN string value, supports placeholders.
	 * @param string $filter        Filter string, supports placeholders.
	 * @param array  $placeholders  Associative array of placeholders for creating base and filter for ldap_search.
	 * @param array  $attributes    List of attributes to be returned.
	 *
	 * @return array Array of ldap_get_entries.
	 */
	protected function search(string $dn, string $filter, array $placeholders, array $attributes): array {
		$this->error = static::ERR_NONE;
		$base = $this->makeFilter($dn, $placeholders, LDAP_ESCAPE_DN);
		$filter = $this->makeFilter($filter, $placeholders + $this->placeholders, LDAP_ESCAPE_FILTER);
		$resource = @ldap_search($this->ds, $base, $filter, $attributes);
		$results = false;

		if ($resource !== false) {
			$results = @ldap_get_entries($this->ds, $resource);
			ldap_free_result($resource);
		}

		if ($resource === false || $results === false) {
			$this->error = static::ERR_QUERY_FAILED;

			return ['count' => 0];
		}

		return $results;
	}
}
