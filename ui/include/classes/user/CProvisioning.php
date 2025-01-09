<?php declare(strict_types = 0);
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


/**
 * Class used for user fields, media and groups provisioning.
 */
class CProvisioning {

	public const AUDITLOG_USERNAME = 'System';

	/**
	 * User directory data array.
	 *
	 * @var int    $userdirectory['userdirectoryid']
	 * @var int    $userdirectory['provision_status']
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

	public function __construct(array $userdirectory, array $mapping_roles) {
		$this->userdirectory = $userdirectory;
		$this->mapping_roles = $mapping_roles;
	}

	/**
	 * Create instance for specific user directory by id.
	 *
	 * @param int $userdirectoryid  User directory id to create CProvisioning instance for.
	 */
	public static function forUserDirectoryId($userdirectoryid): self {
		$userdirectories = API::getApiService('userdirectory')->get([
			'output' => ['userdirectoryid', 'idp_type', 'provision_status', 'user_username', 'user_lastname',
				'host', 'port', 'base_dn', 'bind_dn', 'search_attribute', 'start_tls', 'idp_entityid', 'sso_url',
				'slo_url', 'username_attribute', 'sp_entityid', 'nameid_format', 'sign_messages', 'sign_assertions',
				'sign_authn_requests', 'sign_logout_requests', 'sign_logout_responses', 'encrypt_nameid',
				'encrypt_assertions', 'search_filter', 'group_basedn', 'group_name', 'group_member', 'user_ref_attr',
				'group_filter', 'group_membership'
			],
			'userdirectoryids' => [$userdirectoryid],
			'selectProvisionMedia' => ['userdirectory_mediaid', 'mediatypeid', 'name', 'attribute', 'active',
				'severity', 'period'
			],
			'selectProvisionGroups' => ['name', 'roleid', 'user_groups']
		]);
		$userdirectory = reset($userdirectories);

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
			$mapping_roles = DB::select('role', [
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
				'group_basedn', 'group_name', 'group_member', 'user_ref_attr', 'group_filter', 'group_membership',
				'user_username', 'user_lastname', 'bind_password'
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
	 * Get array of attributes to request from external source when requesting user data.
	 *
	 * @return array Array of attributes names to request from external source.
	 */
	public function getUserIdpAttributes(): array {
		$fields = $this->userdirectory['idp_type'] == IDP_TYPE_LDAP
			? ['user_username', 'user_lastname', 'search_attribute', 'group_membership', 'user_ref_attr']
			: ['user_username', 'user_lastname'];

		$attributes = array_intersect_key($this->userdirectory, array_flip($fields));
		$attributes = array_merge($this->getUserIdpMediaAttributes(), $attributes);

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
	 * Get array of media attributes to request from external source.
	 *
	 * @return array
	 */
	public function getUserIdpMediaAttributes(): array {
		$attributes = array_column($this->userdirectory['provision_media'], 'attribute');

		return array_keys(array_flip(array_filter($attributes, 'strlen')));
	}

	/**
	 * Return array with user fields created from matched provision data on external user data attributes.
	 *
	 * @param array $idp_user        User data from external source, LDAP/SAML.
	 * @param bool  $case_sensitive  How IdP attributes should be matched.
	 *
	 * @return array
	 */
	public function getUserAttributes(array $idp_user, bool $case_sensitive = true): array {
		$user_idp_fields = array_filter([
			'name' => $this->userdirectory['user_username'],
			'surname' => $this->userdirectory['user_lastname']
		], 'strlen');
		$user = [];
		$idp_user_lowercased = [];

		if (!$case_sensitive) {
			foreach ($idp_user as $idp_attr => $idp_value) {
				$idp_user_lowercased[strtolower($idp_attr)] = $idp_value;
			}
		}

		foreach ($user_idp_fields as $user_field => $idp_field) {
			if (array_key_exists($idp_field, $idp_user)) {
				$user[$user_field] = $idp_user[$idp_field];

				continue;
			}

			$idp_field = strtolower($idp_field);

			if (array_key_exists($idp_field, $idp_user_lowercased)) {
				$user[$user_field] = $idp_user_lowercased[$idp_field];
			}
		}

		return $user;
	}

	/**
	 * Get userdirectory media mappings defined for attributes in $idp_attrs.
	 *
	 * @param array $idp_attrs       Array of strings with attributes names from IdP.
	 * @param bool  $case_sensitive  How IdP attributes should be matched.
	 *
	 * @return array
	 */
	public function getUserMediaMappingByAttribute(array $idp_attrs, bool $case_sensitive = true): array {
		$mappings = [];

		if (!$case_sensitive) {
			$idp_attrs += array_flip(array_map('strtolower', array_keys($idp_attrs)));
		}

		foreach ($this->userdirectory['provision_media'] as $provision_media) {
			if (array_key_exists($provision_media['attribute'], $idp_attrs)) {
				$mappings[$provision_media['userdirectory_mediaid']] = $provision_media;
			}
		}

		return $mappings;
	}

	/**
	 * Return array with user media created from matched provision_media on external user data attributes.
	 *
	 * @param array $idp_user        User data from external source, LDAP/SAML.
	 * @param bool  $case_sensitive  How IdP attributes should be matched.
	 *
	 * @return array of medias, media properties
	 *         []['name']
	 *         []['mediatypeid']
	 *         []['sendto']
	 *         []['active']
	 *         []['severity']
	 *         []['period']
	 *         []['userdirectory_mediaid']
	 */
	public function getUserMedias(array $idp_user, bool $case_sensitive = true): array {
		$user_medias = [];
		$idp_user_lowercased = [];

		if (!$case_sensitive) {
			foreach ($idp_user as $idp_attr => $idp_value) {
				$idp_user_lowercased[strtolower($idp_attr)] = $idp_value;
			}
		}

		foreach ($this->userdirectory['provision_media'] as $idp_attributes) {
			$idp_field = $idp_attributes['attribute'];

			if (array_key_exists($idp_field, $idp_user)) {
				$user_medias[] = [
					'name' => $idp_attributes['name'],
					'mediatypeid' => $idp_attributes['mediatypeid'],
					'sendto' =>	[$idp_user[$idp_field]],
					'active' => $idp_attributes['active'],
					'severity' => $idp_attributes['severity'],
					'period' => $idp_attributes['period'],
					'userdirectory_mediaid' => $idp_attributes['userdirectory_mediaid']
				];

				continue;
			}

			$idp_field = strtolower($idp_field);

			if (array_key_exists($idp_field, $idp_user_lowercased)) {
				$user_medias[] = [
					'name' => $idp_attributes['name'],
					'mediatypeid' => $idp_attributes['mediatypeid'],
					'sendto' =>	[$idp_user_lowercased[$idp_field]],
					'active' => $idp_attributes['active'],
					'severity' => $idp_attributes['severity'],
					'period' => $idp_attributes['period'],
					'userdirectory_mediaid' => $idp_attributes['userdirectory_mediaid']
				];
			}
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

		if (!$this->userdirectory['provision_groups']) {
			return $user;
		}

		$roleids = [];
		$user_groups = [];

		foreach ($this->userdirectory['provision_groups'] as $provision_group) {
			$match = ($provision_group['name'] === '*');

			if (strpos($provision_group['name'], '*') === false) {
				$match = in_array($provision_group['name'], $group_names);
			}
			elseif (!$match) {
				$regex = preg_quote($provision_group['name'], '/');
				$regex = '/'.str_replace('\\*', '.*', $regex).'/';
				$match = false;

				foreach ($group_names as $group_name) {
					$match = $match || preg_match($regex, $group_name);
				}
			}

			if ($match) {
				$roleids[$provision_group['roleid']] = true;
				$user_groups = array_merge($user_groups, $provision_group['user_groups']);
			}
		}

		if (!$user_groups) {
			return $user;
		}

		$user['usrgrps'] = array_values(array_column($user_groups, null, 'usrgrpid'));
		$roles = array_intersect_key($this->mapping_roles, $roleids);

		if ($roles) {
			CArrayHelper::sort($roles, [
				['field' => 'type', 'order' => ZBX_SORT_DOWN],
				['field' => 'name', 'order' => ZBX_SORT_UP]
			]);

			['roleid' => $user['roleid']] = reset($roles);
		}

		return $user;
	}
}
