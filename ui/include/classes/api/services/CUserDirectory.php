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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CUserDirectory extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'test' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'userdirectory';
	protected $tableAlias = 'ud';
	protected $sortColumns = ['host', 'name'];

	/**
	 * @var array
	 */
	protected $output_fields = ['userdirectoryid', 'name', 'description', 'host', 'port', 'base_dn', 'bind_dn',
		'search_attribute', 'start_tls', 'search_filter', 'idp_type', 'idp_entityid', 'sso_url', 'slo_url',
		'username_attribute', 'sp_entityid', 'nameid_format', 'sign_attributes', 'encrypt_attributes',
		'provision_status', 'lastname_attribute', 'group_basedn', 'group_name', 'group_member', 'group_filter',
		'group_membership'
	];

	/**
	 * List of supported values in 'sign_attributes' field when Identity provider is SAML.
	 *
	 * @var array
	 */
	protected const SAML_SIGH_ATTRIBUTES = [
		'messages', 'assertions', 'authn_requests', 'logout_requests', 'logout_responses'
	];

	/**
	 * List of supported values in 'encrypt_attributes' field when Identity provider is SAML.
	 *
	 * @var array
	 */
	protected const SAML_ENCRYPT_ATTRIBUTES = ['nameid', 'assertions'];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'userdirectoryids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'userdirectoryid' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'host' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>						['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'provision_status' =>			['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])],
				'idp_type' =>					['type' => API_INTS32, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', [IDP_TYPE_LDAP, IDP_TYPE_SAML])]
			]],
			'search' =>						['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_fill_keys(
				['base_dn', 'bind_dn', 'description', 'host', 'name', 'search_attribute', 'search_filter'],
				['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			)],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode']), 'default' => null],
			'selectProvisionMedia' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['name', 'mediatypeid', 'attribute']), 'default' => null],
			'selectProvisionGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['is_fallback', 'fallback_status', 'name', 'roleid', 'user_groups']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
		}

		$db_userdirectories = [];

		$sql = $this->createSelectQuery($this->tableName, $options);
		$resource = DBselect($sql, $options['limit']);

		while ($row = DBfetch($resource)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_userdirectories[$row['userdirectoryid']] = $row;
		}

		if ($db_userdirectories) {
			$db_userdirectories = $this->addRelatedObjects($options, $db_userdirectories);
			$db_userdirectories = $this->unsetExtraFields($db_userdirectories, ['userdirectoryid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_userdirectories = array_values($db_userdirectories);
			}
		}

		return $db_userdirectories;
	}

	/**
	 * @param array $options
	 * @param array $result
	 *
	 * @return array
	 */
	protected function addRelatedObjects(array $options, array $result): array {
		$result = parent::addRelatedObjects($options, $result);

		self::addRelatedUserGroups($options, $result);
		self::addRelatedProvisionMedia($options, $result);
		self::addRelatedProvisionGroups($options, $result);

		return $result;
	}

	/**
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedUserGroups(array $options, array &$result): void {
		if ($options['selectUsrgrps'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['usrgrps'] = [];
		}
		unset($row);

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			$output = ['userdirectoryid'];
		}
		elseif ($options['selectUsrgrps'] === API_OUTPUT_EXTEND) {
			$output = ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid'];
		}
		else {
			$output = array_unique(array_merge(['userdirectoryid'], $options['selectUsrgrps']));
		}

		$db_usergroups = API::UserGroup()->get([
			'output' => $output,
			'filter' => ['userdirectoryid' => array_keys($result)]
		]);

		foreach ($db_usergroups as $db_usergroup) {
			$result[$db_usergroup['userdirectoryid']]['usrgrps'][] =
				array_diff_key($db_usergroup, array_flip(['userdirectoryid']));
		}

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['usrgrps'] = (string) count($row['usrgrps']);
			}
			unset($row);
		}
	}

	/**
	 * Add provision media objects.
	 *
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedProvisionMedia(array $options, array &$result): void {
		if ($options['selectProvisionMedia'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['provision_media'] = [];
		}
		unset($row);

		if ($options['selectProvisionMedia'] === API_OUTPUT_EXTEND) {
			$output = ['userdirectoryid', 'name', 'mediatypeid', 'attribute'];
		}
		else {
			$output = array_unique(array_merge(['userdirectoryid'], $options['selectProvisionMedia']));
		}

		$db_provisioning_media = DB::select('provision_media', [
			'output' => $output,
			'filter' => [
				'userdirectoryid' => array_keys($result)
			]
		]);

		foreach ($db_provisioning_media as $db_provisioning_media) {
			$result[$db_provisioning_media['userdirectoryid']]['provision_media'][]
				= array_diff_key($db_provisioning_media, array_flip(['userdirectoryid']));
		}
	}

	/**
	 * Add provision user group objects.
	 *
	 * @param array $options
	 * @param array $result
	 */
	private static function addRelatedProvisionGroups(array $options, array &$result): void {
		if ($options['selectProvisionGroups'] === null) {
			return;
		}

		foreach ($result as &$row) {
			$row['provision_groups'] = [];
		}
		unset($row);

		if ($options['selectProvisionGroups'] === API_OUTPUT_EXTEND) {
			$output = ['userdirectoryid', 'is_fallback', 'fallback_status', 'name', 'roleid', 'user_groups'];
		}
		else {
			$output = array_unique(array_merge(['userdirectoryid'], $options['selectProvisionGroups']));
		}

		$usergroup = array_search('user_groups', $output);
		$user_groups_requested = $usergroup !== false;
		if ($user_groups_requested) {
			unset($output[$usergroup]);
		}

		$db_provision_idpgroups = DB::select('provision_idpgroup', [
			'output' => $output,
			'filter' => [
				'userdirectoryid' => array_keys($result)
			],
			'preservekeys' => true
		]);

		$db_provision_usergroups = $user_groups_requested && $db_provision_idpgroups
			? DB::select('provision_usergroup', [
				'output' => ['usrgrpid', 'provision_idpgroupid'],
				'filter' => [
					'provision_idpgroupid' => array_keys($db_provision_idpgroups)
				]
			])
			: [];

		$provision_usergroups = [];
		foreach ($db_provision_usergroups as $usrgrp) {
			$provision_usergroups[$usrgrp['provision_idpgroupid']][] = [
				'usrgrpid' => $usrgrp['usrgrpid']
			];
		}

		foreach ($db_provision_idpgroups as $db_provision_idpgroup) {
			$idpgroup = array_diff_key($db_provision_idpgroup, array_flip(['userdirectoryid']));
			if ($user_groups_requested) {
				$provision_idpgroupid = $db_provision_idpgroup['userdirectoryid'];
				$idpgroup['user_groups'] = array_key_exists($provision_idpgroupid, $provision_usergroups)
					? $provision_usergroups[$provision_idpgroupid]
					: [];
			}

			$result[$db_provision_idpgroup['userdirectoryid']]['provision_groups'][] = $idpgroup;
		}
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $userdirectories): array {
		self::validateCreate($userdirectories);

		// Properties 'sign_attributes' and 'encrypt_attributes' are stored in json-serialized array.
		foreach ($userdirectories as &$userdirectory) {
			if (array_key_exists('sign_attributes', $userdirectory)) {
				$userdirectory['sign_attributes'] = json_encode($userdirectory['sign_attributes']);
			}
			if (array_key_exists('encrypt_attributes', $userdirectory)) {
				$userdirectory['encrypt_attributes'] = json_encode($userdirectory['encrypt_attributes']);
			}
		}
		unset($userdirectory);

		$db_count = DB::select('userdirectory', ['countOutput' => true]);
		$userdirectoryids = DB::insert('userdirectory', $userdirectories);
		$provision_media = [];

		$provision_groups_count = array_sum(array_map(function ($userdirectory) {
			return array_key_exists('provision_groups', $userdirectory) ? count($userdirectory['provision_groups']) : 0;
		}, $userdirectories));

		foreach ($userdirectories as $index => &$userdirectory) {
			$userdirectory['userdirectoryid'] = $userdirectoryids[$index];

			if (array_key_exists('provision_media', $userdirectory)) {
				foreach ($userdirectory['provision_media'] as $media) {
					$provision_media[] = [
						'userdirectoryid' => $userdirectory['userdirectoryid']
					] + $media;
				}
			}

			if (array_key_exists('provision_groups', $userdirectory)) {
				foreach ($userdirectory['provision_groups'] as $groups) {
					$groups['userdirectoryid'] = $userdirectory['userdirectoryid'];
					$idpgroupid = DB::insertBatch('provision_idpgroup', [$groups]);

					$user_groups = array_map(function ($usrgrp) use ($idpgroupid) {
						return ['provision_idpgroupid' => reset($idpgroupid)] + $usrgrp;
					}, $groups['user_groups']);

					if ($user_groups) {
						DB::insertBatch('provision_usergroup', $user_groups);
					}
				}
			}
		}
		unset($userdirectory);

		if ($provision_media) {
			DB::insertBatch('provision_media', $provision_media);
		}

		// TODO
		//self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USERDIRECTORY, $userdirectories);

		if (!$db_count) {
			API::Authentication()->update(['ldap_userdirectoryid' => reset($userdirectoryids)]);
		}

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$userdirectories): void {
		$api_input_rules = self::getValidationRules('create');

		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($userdirectories);
		self::checkSamlExists($userdirectories);
	}

	/**
	 * Validate if only one user directory of type IDP_SAML exists.
	 *
	 * @return void
	 */
	private static function checkSamlExists($userdirectories): void {
		$userdirectories = array_filter($userdirectories, function ($userdirectory) {
			return $userdirectory['idp_type'] == IDP_TYPE_SAML;
		});
		if (count($userdirectories) == 0) {
			return;
		}

		$saml_userdirectory = DB::select('userdirectory', [
			'countOutput' => true,
			'filter' => [
				'idp_type' => IDP_TYPE_SAML
			]
		]);

		if ($saml_userdirectory != 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Only one user directory of type "SAML" can exist.'));
		}
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $userdirectories): array {
		self::validateUpdate($userdirectories, $db_userdirectories);

		$upd_userdirectories = [];

		foreach ($userdirectories as $userdirectory) {
			$upd_userdirectory = DB::getUpdatedValues('userdirectory', $userdirectory,
				$db_userdirectories[$userdirectory['userdirectoryid']]
			);

			if ($upd_userdirectory) {
				$upd_userdirectories[] = [
					'values' => $upd_userdirectory,
					'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
				];
			}
		}

		$this->updateProvisionMedia($userdirectories);
		$this->updateProvisionGroups($userdirectories);

		if ($upd_userdirectories) {
			DB::update('userdirectory', $upd_userdirectories);

			// TODO:
//			self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USERDIRECTORY, $userdirectories,
//				$db_userdirectories
//			);
		}

		return ['userdirectoryids' => array_column($userdirectories, 'userdirectoryid')];
	}

	/**
	 * Validate function for 'update' method.
	 *
	 * Validation is performed in multiple steps. First we check if userdirectoryid(s) are present. Then we extend each
	 * of given $userdirectories objects with 'idp_type' property from database, then perform full input validation.
	 *
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateUpdate(array &$userdirectories, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => [
			'userdirectoryid' => ['type' => API_ID, 'flags' => API_REQUIRED]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = DB::select('userdirectory', [
			'output' => ['name', 'idp_type'],
			'userdirectoryids' => array_column($userdirectories, 'userdirectoryid'),
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectories)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($userdirectories as &$userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];
			if (!array_key_exists('idp_type', $userdirectory)) {
				$userdirectory['idp_type'] = $db_userdirectory['idp_type'];
			}

			if ($userdirectory['idp_type'] != $db_userdirectory['idp_type']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'idp_type', _('cannot be changed'))
				);
			}
		}
		unset($userdirectory);

		$api_input_rules = self::getValidationRules('update');
		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($userdirectories, $db_userdirectories);
	}

	/**
	 * Check for unique names.
	 *
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException if userdirectory name is not unique.
	 */
	private static function checkDuplicates(array $userdirectories, array $db_userdirectories = null): void {
		$names = [];

		foreach ($userdirectories as $userdirectory) {
			if (!array_key_exists('name', $userdirectory)) {
				continue;
			}

			if ($db_userdirectories === null
					|| $userdirectory['name'] !== $db_userdirectories[$userdirectory['userdirectoryid']]['name']) {
				$names[] = $userdirectory['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('userdirectory', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('User directory "%1$s" already exists.',
				$duplicates[0]['name'])
			);
		}
	}

	/**
	 * @param array $userdirectoryids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $userdirectoryids): array {
		self::validateDelete($userdirectoryids, $db_userdirectories);

		DB::delete('userdirectory', ['userdirectoryid' => $userdirectoryids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_USERDIRECTORY, $db_userdirectories);

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array      $userdirectoryids
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateDelete(array $userdirectoryids, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectoryids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = API::UserDirectory()->get([
			'output' => ['userdirectoryid', 'name'],
			'userdirectoryids' => $userdirectoryids,
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectoryids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$userdirectories_left = API::UserDirectory()->get(['countOutput' => true]) - count($userdirectoryids);
		$auth = API::Authentication()->get([
			'output' => ['ldap_userdirectoryid', 'authentication_type', 'ldap_configured']
		]);

		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)
				&& ($auth['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED || $userdirectories_left > 0)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default user directory.'));
		}

		$db_groups = API::UserGroup()->get([
			'output' => ['userdirectoryid'],
			'filter' => [
				'gui_access' => [GROUP_GUI_ACCESS_LDAP, GROUP_GUI_ACCESS_SYSTEM],
				'userdirectoryid' => $userdirectoryids
			],
			'limit' => 1
		]);

		if ($db_groups) {
			$db_group = reset($db_groups);

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete user directory "%1$s".',
				$db_userdirectories[$db_group['userdirectoryid']]['name'])
			);
		}

		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)) {
			// If last (default) is removed, reset default userdirectoryid to prevent from foreign key constraint.
			API::Authentication()->update(['ldap_userdirectoryid' => 0]);
		}
	}

	/**
	 * Test user against specific userdirectory connection.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 *
	 * @return bool
	 */
	public function test(array $userdirectory): bool {
		self::validateTest($userdirectory);

		$user = [
			'username' => $userdirectory['test_username'],
			'password' => $userdirectory['test_password']
		];
		$ldap_validator = new CLdapAuthValidator(['conf' => $userdirectory]);

		if (!$ldap_validator->validate($user)) {
			self::exception(
				$ldap_validator->isConnectionError() ? ZBX_API_ERROR_PARAMETERS : ZBX_API_ERROR_PERMISSIONS,
				$ldap_validator->getError()
			);
		}

		return true;
	}

	/**
	 * Validate user directory and test user credentials to be used for testing.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 */
	protected static function validateTest(array &$userdirectory): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'flags' => API_ALLOW_NULL, 'default' => null],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn'), 'default' => ''],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON, 'default' => ZBX_AUTH_START_TLS_OFF],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter'), 'default' => ''],
			'test_username' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'test_password' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectory, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($userdirectory['userdirectoryid'] !== null) {
			$db_userdirectory = DB::select('userdirectory', [
				'output' => ['host', 'port', 'base_dn', 'bind_dn', 'bind_password', 'search_attribute', 'start_tls',
					'search_filter'
				],
				'userdirectoryids' => $userdirectory['userdirectoryid']
			]);
			$db_userdirectory = reset($db_userdirectory);

			if (!$db_userdirectory) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$userdirectory += $db_userdirectory;
		}
	}

	/**
	 * @param array      $userdirectories
	 */
	private function updateProvisionMedia(array $userdirectories): void {
		$userdirectories = array_column($userdirectories, null, 'userdirectoryid');

		$db_provision_media = DB::select('provision_media', [
			'output' => ['provision_mediaid', 'userdirectoryid', 'mediatypeid', 'name', 'attribute'],
			'filter' => [
				'userdirectoryid' => array_column($userdirectories, 'userdirectoryid')
			]
		]);

		$provision_media_remove = [];
		foreach ($db_provision_media as $media) {
			$provision_media_remove[$media['userdirectoryid']][$media['provision_mediaid']] = [
				'mediatypeid' => $media['mediatypeid'],
				'name' => $media['name'],
				'attribute' => $media['attribute']
			];
		}

		foreach ($userdirectories as &$userdirectory) {
			if (!array_key_exists('provision_media', $userdirectory)) {
				continue;
			}

			$userdirectoryid = $userdirectory['userdirectoryid'];
			foreach ($provision_media_remove[$userdirectoryid] as $provision_mediaid => $provision_media_data) {
				foreach ($userdirectory['provision_media'] as $index => $new_provision_media) {
					if ($provision_media_data == $new_provision_media) {
						unset($provision_media_remove[$userdirectoryid][$provision_mediaid]);
						unset($userdirectories[$userdirectoryid]['provision_media'][$index]);
					}
				}
			}
		}

		// Collect IDs to remove.
		$provision_mediaids_remove = [];
		foreach ($provision_media_remove as $media_remove) {
			$provision_mediaids_remove = array_merge($provision_mediaids_remove, array_keys($media_remove));
		}

		if ($provision_mediaids_remove) {
			DB::delete('provision_media', ['provision_mediaid' => $provision_mediaids_remove]);
		}

		// Collect new provision media entries.
		$provision_media_insert = [];
		foreach ($userdirectories as $userdirectoryid => $userdirectory) {
			if (!array_key_exists('provision_media', $userdirectory)) {
				continue;
			}

			foreach ($userdirectory['provision_media'] as $media) {
				$provision_media_insert[] = ['userdirectoryid' => $userdirectoryid] + $media;
			}
		}

		if ($provision_media_insert) {
			DB::insertBatch('provision_media', $provision_media_insert);
		}
	}

	private function updateProvisionGroups(array $userdirectories): void {
		$userdirectories = array_column($userdirectories, null, 'userdirectoryid');

		$db_provision_groups = DB::select('provision_idpgroup', [
			'output' => ['provision_idpgroupid', 'userdirectoryid', 'roleid', 'is_fallback', 'fallback_status', 'name'],
			'filter' => [
				'userdirectoryid' => array_column($userdirectories, 'userdirectoryid')
			]
		]);

		$db_provision_usergroup = DB::select('provision_usergroup', [
			'output' => ['provision_idpgroupid', 'usrgrpid'],
			'filter' => [
				'provision_idpgroupid' => array_column($db_provision_groups, 'provision_idpgroupid')
			]
		]);

		$provision_usrgrps = [];
		foreach ($db_provision_usergroup as $usergroup) {
			$provision_usrgrps[$usergroup['provision_idpgroupid']][] = [
				'usrgrpid' => $usergroup['usrgrpid']
			];
		}

		$provision_groups_remove = [];
		foreach ($db_provision_groups as $group) {
			$user_groups = $provision_usrgrps[$group['provision_idpgroupid']];
			CArrayHelper::sort($user_groups, ['usrgrpid']);

			$provision_groups_remove[$group['userdirectoryid']][$group['provision_idpgroupid']] = [
				'roleid' => $group['roleid'],
				'is_fallback' => $group['is_fallback'],
				'fallback_status' => $group['fallback_status'],
				'name' => $group['name'],
				'user_groups' => array_values($user_groups)
			];
		}

		foreach ($userdirectories as $userdirectory) {
			if (!array_key_exists('provision_media', $userdirectory)) {
				continue;
			}

			$userdirectoryid = $userdirectory['userdirectoryid'];
			foreach ($provision_groups_remove[$userdirectoryid] as $provision_groupid => $provision_group_data) {
				foreach ($userdirectory['provision_groups'] as $index => $new_provision_group) {
					CArrayHelper::sort($new_provision_group['user_groups'], ['usrgrpid']);
					$new_provision_group['user_groups'] = array_values($new_provision_group['user_groups']);

					if ($provision_group_data == $new_provision_group) {
						unset($provision_groups_remove[$userdirectoryid][$provision_groupid]);
						unset($userdirectories[$userdirectoryid]['provision_groups'][$index]);
					}
				}
			}
		}

		// Collect IDs to remove.
		$provision_groupids_remove = [];
		foreach ($provision_groups_remove as $group_remove) {
			$provision_groupids_remove = array_merge($provision_groupids_remove, array_keys($group_remove));
		}

		if ($provision_groupids_remove) {
			DB::delete('provision_idpgroup', ['provision_idpgroupid' => $provision_groupids_remove]);
		}

		// Collect new provision media entries.
		foreach ($userdirectories as $userdirectoryid => $userdirectory) {
			if (array_key_exists('provision_groups', $userdirectory)) {
				foreach ($userdirectory['provision_groups'] as $groups) {
					$groups['userdirectoryid'] = $userdirectoryid;
					$idpgroupid = DB::insertBatch('provision_idpgroup', [$groups]);

					$user_groups = array_map(function ($usrgrp) use ($idpgroupid) {
						return ['provision_idpgroupid' => reset($idpgroupid)] + $usrgrp;
					}, $groups['user_groups']);

					if ($user_groups) {
						DB::insertBatch('provision_usergroup', $user_groups);
					}
				}
			}
		}
	}

	private static function getValidationRules(string $method = 'create'): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'idp_type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [IDP_TYPE_LDAP, IDP_TYPE_SAML])],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
			'host' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'host')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'port' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'base_dn' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'base_dn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'bind_dn' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_dn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'bind_password' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'bind_password')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'search_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'search_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'description' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'start_tls' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_INT32, 'in' => implode(',', [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'search_filter' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'search_filter')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_basedn' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'group_basedn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_name' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'group_name')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_member' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'group_member')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_filter' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'group_filter')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_membership' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'group_membership')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'idp_entityid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'idp_entityid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sso_url' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'sso_url')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'slo_url' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'slo_url')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sp_entityid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'sp_entityid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'nameid_format' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'nameid_format')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_attributes' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', self::SAML_SIGH_ATTRIBUTES)],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'encrypt_attributes' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', self::SAML_ENCRYPT_ATTRIBUTES)],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'username_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'username_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'lastname_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'lastname_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'user_provisioning' =>	['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])],
			'provision_media' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('provision_media', 'name')],
				'mediatypeid' =>		['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'attribute' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('provision_media', 'attribute')]
			]],
			'provision_groups' =>	['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
				'is_fallback' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GROUP_MAPPING_REGULAR, GROUP_MAPPING_FALLBACK])],
				'fallback_status' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [GROUP_MAPPING_FALLBACK_OFF, GROUP_MAPPING_FALLBACK_ON])],
				'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('provision_idpgroup', 'is_fallback')],
				'roleid' =>				['type' => API_ID, 'flags' => API_REQUIRED | API_NOT_EMPTY],
				'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
					'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
				]]
			]]
		]];

		if ($method === 'update') {
			$api_input_rules['fields']['userdirectoryid'] = ['type' => API_ID, 'flags' => API_REQUIRED];
			$api_input_rules['fields']['name']['flags'] &= ~API_REQUIRED;
			$api_input_rules['fields']['host']['rules'][0]['flags'] &= ~API_REQUIRED;
			$api_input_rules['fields']['port']['rules'][0]['flags'] &= ~API_REQUIRED;
			$api_input_rules['fields']['base_dn']['rules'][0]['flags'] &= ~API_REQUIRED;
			$api_input_rules['fields']['search_attribute']['rules'][0]['flags'] &= ~API_REQUIRED;
		}

		return $api_input_rules;
	}
}
