<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	public function __construct($arg = array()) {
		$this->ds = false;
		$this->info = array();
		$this->cnf = array(
			'host' => 'ldap://localhost',
			'port' => '389',
			'bind_dn' => 'uid=admin,ou=system',
			'bind_password' => '',
			'base_dn' => 'ou=users,ou=system',
			'search_attribute' => 'uid',
			'userfilter' => '(%{attr}=%{user})',
			'groupkey' => 'cn',
			'mapping' => array(
				'alias' => 'uid',
				'userid' => 'uidnumbera',
				'passwd' => 'userpassword'
			),
			'referrals' => 0,
			'version' => 3,
			'starttls' => null,
			'deref' => null
		);

		if (is_array($arg)) {
			$this->cnf = zbx_array_merge($this->cnf, $arg);
		}

		if (!function_exists('ldap_connect')) {
			error('LDAP lib error. Cannot find needed functions.');
			return false;
		}
	}

	public function connect() {
		// connection already established
		if ($this->ds) {
			return true;
		}

		$this->bound = 0;

		if (!$this->ds = ldap_connect($this->cnf['host'], $this->cnf['port'])) {
			error('LDAP: couldn\'t connect to LDAP server.');

			return false;
		}

		// set protocol version and dependend options
		if ($this->cnf['version']) {
			if (!ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, $this->cnf['version'])) {
				error('Setting LDAP Protocol version '.$this->cnf['version'].' failed.');
			}
			else {
				// use TLS (needs version 3)
				if (isset($this->cnf['starttls']) && !ldap_start_tls($this->ds)) {
					error('Starting TLS failed.');
				}

				// needs version 3
				if (!zbx_empty($this->cnf['referrals'])
						&& !ldap_set_option($this->ds, LDAP_OPT_REFERRALS, $this->cnf['referrals'])) {
					error('Setting LDAP referrals to off failed.');
				}
			}
		}

		// set deref mode
		if (isset($this->cnf['deref']) && !ldap_set_option($this->ds, LDAP_OPT_DEREF, $this->cnf['deref'])) {
			error('Setting LDAP Deref mode '.$this->cnf['deref'].' failed.');
		}

		return true;
	}

	public function checkPass($user, $pass) {
		if (!$pass) {
			return false;
		}

		if (!$this->connect()) {
			return false;
		}

		$dn = null;

		// indirect user bind
		if (!empty($this->cnf['bind_dn']) && !empty($this->cnf['bind_password'])) {
			// use superuser credentials
			if (!ldap_bind($this->ds, $this->cnf['bind_dn'], $this->cnf['bind_password'])) {
				error('LDAP: cannot bind by given Bind DN.');

				return false;
			}

			$this->bound = 2;
		}
		elseif (!empty($this->cnf['bind_dn']) && !empty($this->cnf['base_dn']) && !empty($this->cnf['userfilter'])) {
			// special bind string
			$dn = $this->makeFilter($this->cnf['bind_dn'], array('user' => $user, 'host' => $this->cnf['host']));
		}
		elseif (zbx_strpos($this->cnf['base_dn'], '%{user}')) {
			// direct user bind
			$dn = $this->makeFilter($this->cnf['base_dn'], array('user' => $user, 'host' => $this->cnf['host']));
		}
		else {
			// anonymous bind
			if (!ldap_bind($this->ds)) {
				error('LDAP: can not bind anonymously.');

				return false;
			}
		}

		// try to bind to with the dn if we have one.
		if ($dn) {
			// user/password bind
			if (!ldap_bind($this->ds, $dn, $pass)) {
				return false;
			}

			$this->bound = 1;

			return true;
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
			if (!ldap_bind($this->ds, $dn, $pass)) {
				return false;
			}

			$this->bound = 1;

			return true;
		}

		return false;
	}

	private function getUserData($user) {
		if (!$this->connect()) {
			return false;
		}

		// force superuser bind if wanted and not bound as superuser yet
		if (!empty($this->cnf['bind_dn']) && !empty($this->cnf['bind_password']) && ($this->bound < 2)) {
			if (!ldap_bind($this->ds, $this->cnf['bind_dn'], $this->cnf['bind_password'])) {
				return false;
			}
			$this->bound = 2;
		}

		// with no superuser creds we continue as user or anonymous here
		$info['user'] = $user;
		$info['host'] = $this->cnf['host'];

		// get info for given user
		$base = $this->makeFilter($this->cnf['base_dn'], $info);

		if (isset($this->cnf['userfilter']) && !empty($this->cnf['userfilter'])) {
			$filter = $this->makeFilter($this->cnf['userfilter'], $info);
		}
		else {
			$filter = '(ObjectClass=*)';
		}
		$sr = ldap_search($this->ds, $base, $filter);
		$result = ldap_get_entries($this->ds, $sr);

		// don't accept more or less than one response
		if ($result['count'] != 1) {
			error('LDAP: User not found.');
			return false;
		}

		$user_result = $result[0];
		ldap_free_result($sr);

		// general user info
		$info['dn'] = $user_result['dn'];
		$info['name'] = $user_result['cn'][0];
		$info['grps'] = array();

		// overwrite if other attribs are specified.
		if (is_array($this->cnf['mapping'])) {
			foreach ($this->cnf['mapping'] as $localkey => $key) {
				$info[$localkey] = isset($user_result[$key])?$user_result[$key][0]:null;
			}
		}
		$user_result = zbx_array_merge($info,$user_result);

		// get groups for given user if grouptree is given
		if (isset($this->cnf['grouptree']) && isset($this->cnf['groupfilter'])) {
			$base = $this->makeFilter($this->cnf['grouptree'], $user_result);
			$filter = $this->makeFilter($this->cnf['groupfilter'], $user_result);
			$sr = ldap_search($this->ds, $base, $filter, array($this->cnf['groupkey']));

			if (!$sr) {
				error('LDAP: Reading group memberships failed.');
				return false;
			}

			$result = ldap_get_entries($this->ds, $sr);

			foreach ($result as $grp) {
				if (!empty($grp[$this->cnf['groupkey']][0])) {
					$info['grps'][] = $grp[$this->cnf['groupkey']][0];
				}
			}
		}

		// always add the default group to the list of groups
		if (isset($conf['defaultgroup']) && !str_in_array($conf['defaultgroup'], $info['grps'])) {
			$info['grps'][] = $conf['defaultgroup'];
		}

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
