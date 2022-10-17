<?php declare(strict_types = 0);
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Code responsible for user fields, media and groups provisioning.
 */
class CProvisioning {

	public const AUDITLOG_USERNAME = 'System';

	/**
	 * User directory data array.
	 *
	 * @var int    $userdirectory['userdirectoryid']
	 * @var int    $userdirectory['provisioin_status']
	 * @var string $userdirectory['user_username']
	 * @var string $userdirectory['user_lastname']
	 * @var string $userdirectory['search_attribute']
	 * @var array  $userdirectory['provision_media']
	 * @var string $userdirectory['provision_media'][]['mediatypeid']
	 * @var string $userdirectory['provision_media'][]['attribute']
	 * @var string $userdirectory['provision_groups']
	 *
	 * @var array
	 */
	protected $userdirectory = [];

	/**
	 * Array of user roles data used in group mappings.
	 *
	 * @var array  $mapping_roles[]
	 * @var int    $mapping_roles[roleid]['roleid']
	 * @var int    $mapping_roles[roleid]['user_type']
	 * @var string $mapping_roles[roleid]['name']
	 */
	protected $mapping_roles = [];

	/**
	 * Array of media types data used in media mappings.
	 *
	 * @var array $mapping_mediatypes[]
	 * @var int   $mapping_mediatypes[mediatypeid]['mediatypeid']
	 * @var int   $mapping_mediatypes[mediatypeid]['type']
	 */
	protected $mapping_mediatypes = [];

	public function __construct(array $userdirectory, array $mapping_roles) {
		$this->userdirectory = $userdirectory;
		$this->mapping_roles = $mapping_roles;
	}

	/**
	 * Create instance for specific user directory by it id.
	 *
	 * @param int $userdirectoryid  User directory id to create CProvisioning instance for.
	 */
	public static function forUserDirectoryId($userdirectoryid): self {
		[$userdirectory] = API::UserDirectory()->get([
			'output' => ['userdirectoryid', 'idp_type', 'provision_status', 'user_username', 'user_lastname',
				'host', 'port', 'base_dn', 'bind_dn', 'search_attribute', 'start_tls',
				'search_filter', 'group_basedn', 'group_name', 'group_member', 'group_filter', 'group_membership',
				'idp_entityid', 'sso_url', 'slo_url', 'username_attribute', 'sp_entityid', 'nameid_format', 'sign_messages',
				'sign_assertions', 'sign_authn_requests', 'sign_logout_requests', 'sign_logout_responses', 'encrypt_nameid',
				'encrypt_assertions'
			],
			'userdirectoryids' => [$userdirectoryid],
			'selectProvisionMedia' => API_OUTPUT_EXTEND,
			'selectProvisionGroups' => API_OUTPUT_EXTEND
		]);

		if (!$userdirectory || $userdirectory['provision_status'] == JIT_PROVISIONING_DISABLED) {
			return new self($userdirectory, []);
		}

		if ($userdirectory['idp_type'] == IDP_TYPE_LDAP) {
			$userdirectory += DB::select('userdirectory_ldap', [
				'output' => ['bind_password'],
				'filter' => ['userdirectoryid' => $userdirectoryid]
			])[0];
		}

		$mapping_roles = [];

		if ($userdirectory['provision_groups']) {
			$mapping_roles = API::Role()->get([
				'output' => ['roleid', 'name', 'type'],
				'roleids' => array_column($userdirectory['provision_groups'], 'roleid', 'roleid'),
				'preservekeys' => true
			]);
		}

		return new self($userdirectory, $mapping_roles);
	}

	/**
	 * Get configuration options for idp.
	 *
	 * @return array
	 */
	public function getIdpConfig(): array {
		$keys = [
			IDP_TYPE_LDAP	=> ['host', 'port', 'base_dn', 'bind_dn', 'search_attribute', 'start_tls', 'search_filter',
				'group_basedn', 'group_name', 'group_member', 'group_filter', 'group_membership', 'user_username',
				'user_lastname', 'bind_password'
			],
			IDP_TYPE_SAML	=> ['idp_entityid', 'sso_url', 'slo_url', 'username_attribute', 'sp_entityid',
				'nameid_format', 'sign_messages', 'sign_assertions', 'sign_authn_requests', 'sign_logout_requests',
				'sign_logout_responses', 'encrypt_nameid', 'encrypt_assertions', 'group_name', 'user_username',
				'user_lastname', 'scim_status'
			]
		];

		return array_key_exists($this->userdirectory['idp_type'], $keys)
			? array_intersect_key($this->userdirectory, array_flip($keys[$this->userdirectory['idp_type']]))
			: [];
	}

	/**
	 * Get provisioning status.
	 *
	 * @return bool  Return true when enabled.
	 */
	public function isProvisioningEnabled(): bool {
		return $this->userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED;
	}

	/**
	 * Get provisioning user directory database id.
	 *
	 * @return int
	 */
	public function getUserdirectoryId(): int {
		return $this->userdirectory['userdirectoryid'];
	}
	/**
	 * Get array of attributes to request from external source when requesting user data.
	 *
	 * @return array Array of attributes names to request from external source.
	 */
	public function getUserIdpAttributes(): array {
		$attributes = [];

		switch ($this->userdirectory['idp_type']) {
			case IDP_TYPE_LDAP:
				$fields = ['user_username', 'user_lastname', 'search_attribute', 'group_membership'];

				break;

			case IDP_TYPE_SAML:
				$fields = ['user_username', 'user_lastname'];

				break;
		}

		$attributes = array_intersect_key($this->userdirectory, array_flip($fields));
		$attributes = array_merge(array_column($this->userdirectory['provision_media'], 'attribute'), $attributes);

		return array_keys(array_flip(array_filter($attributes, 'strlen')));
	}

	/**
	 * Get array of attributes to request from external source when requesting user group data.
	 *
	 * @return array
	 */
	public function getGroupIdpAttributes(): array {
		$attributes = [$this->userdirectory['group_name']];

		return array_values(array_filter($attributes, 'strlen'));
	}

	/**
	 * Return array with user data created from external user data attributes.
	 *
	 * @param array $idp_user       User data from external source.
	 *
	 * @return array
	 *         ['medias']                   Array of user media extracted from external user data.
	 *                                      Empty array when no media found.
	 *         ['medias'][]['mediatypeid']  User media type id.
	 *         ['medias'][]['sendto']       Array with single entry of media notification recipient.
	 *         ['usrgrps']                  Array of user groups extracted from external user data.
	 *                                      Is set when user groups data were found in external data.
	 *         ['usrgrps'][]['usrgrpid']    Matched user group id.
	 */
	public function getUser(array $idp_user): array {
		$user = array_merge(['medias' => []], $this->getUserAttributes($idp_user, $this->userdirectory));
		$group_key = $this->userdirectory['idp_type'] == IDP_TYPE_LDAP ? $this->userdirectory['group_membership'] : '';

		if ($this->userdirectory['provision_media']) {
			$user['medias'] = $this->getUserMedias($idp_user);
		}

		if ($group_key !== '' && array_key_exists($group_key, $idp_user) && is_array($idp_user[$group_key])) {
			/**
			 * Attribute to search for groups in user data defined, and if there will be no match, 'usrgrps' key
			 * should exist to do not attempt to query LDAP for user groups once again.
			 */
			$user['usrgrps'] = [];
			$user = array_merge($user, $this->getUserGroupsAndRole($idp_user[$group_key]));
		}

		return $user;
	}

	/**
	 * Return array with user fields created from matched provision data on external user data attributes.
	 *
	 * @param array $idp_user    User data from external source, LDAP/SAML.
	 *
	 * @return array
	 */
	public function getUserAttributes(array $idp_user): array {
		$user = [];

		if ($this->userdirectory['user_username'] !== ''
				&& array_key_exists($this->userdirectory['user_username'], $idp_user)) {
			$user['name'] = $idp_user[$this->userdirectory['user_username']];
		}

		if ($this->userdirectory['user_lastname'] !== ''
				&& array_key_exists($this->userdirectory['user_lastname'], $idp_user)) {
			$user['surname'] = $idp_user[$this->userdirectory['user_lastname']];
		}

		return $user;
	}

	/**
	 * Return array with user media created from matched provision_media on external user data attributes.
	 *
	 * @param array $idp_user    User data from external source, LDAP/SAML.
	 *
	 * @return array
	 *         []['mediatypeid']
	 *         []['sendto']
	 */
	public function getUserMedias(array $idp_user): array {
		$user_medias = [];
		$attributes = array_column($this->userdirectory['provision_media'], null, 'mediatypeid');

		foreach ($attributes as $mediatypeid => $idp_attributes) {
			$idp_attribute = strtolower($idp_attributes['attribute']);

			if (!array_key_exists($idp_attribute, $idp_user)) {
				continue;
			}

			$user_medias[] = [
				'name' => $idp_attributes['name'],
				'mediatypeid' => $mediatypeid,
				'sendto' =>	[$idp_user[$idp_attribute]],
			];
		}

		return $user_medias;
	}

	/**
	 * Return array with user groups matched to provision groups criteria and roleid.
	 *
	 * @param array  $group_names         User group names data from external source, LDAP/SAML.
	 *
	 * @return array
	 *         ['roleid']                 Matched roleid to set for user. Is 0 when no match.
	 *         ['usrgrps']                Matched mapping user groups to set for user. Empty array when no match.
	 *         ['usrgrps'][]['usrgrpid']  User group
	 */
	public function getUserGroupsAndRole(array $group_names): array {
		$user = ['usrgrps' => [], 'roleid' => 0];

		if (!$group_names || !$this->userdirectory['provision_groups']) {
			return $user;
		}

		$roleids = [];
		$groups = [];

		foreach ($this->userdirectory['provision_groups'] as $provision_group) {
			if (strpos($provision_group['name'], '*') === false) {
				$match = in_array($provision_group['name'], $group_names);
			}
			else {
				$regex = preg_quote($provision_group['name'], '/');
				$regex = '/'.str_replace('\\*', '.*', $regex).'/';
				$match = (bool) array_filter($group_names, function ($group_name) use ($regex) {
					return preg_match($regex, $group_name);
				});
			}

			if ($match) {
				$roleids[$provision_group['roleid']] = 1;
				$groups = array_merge($groups, $provision_group['user_groups']);
			}
		}

		if (!$groups) {
			return $user;
		}

		$roles = array_intersect_key($this->mapping_roles, $roleids);
		CArrayHelper::sort($roles, [
			['field' => 'type', 'order' => ZBX_SORT_DOWN],
			['field' => 'name', 'order' => ZBX_SORT_UP]
		]);
		['roleid' => $user['roleid']] = reset($roles);
		$user['usrgrps'] = $groups;

		return $user;
	}
}
