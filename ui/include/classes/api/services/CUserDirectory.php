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
	protected $sortColumns = ['name'];

	public const COMMON_OUTPUT_FIELDS = ['userdirectoryid', 'name', 'idp_type', 'provision_status', 'description'];
	public const LDAP_OUTPUT_FIELDS = [
		'host', 'port', 'base_dn', 'search_attribute', 'bind_dn', 'start_tls', 'search_filter', 'group_basedn',
		'group_name', 'group_member', 'group_filter', 'group_membership', 'user_username', 'user_lastname',
		'user_ref_attr'
	];
	public const SAML_OUTPUT_FIELDS = [
		'idp_entityid', 'sso_url', 'slo_url', 'username_attribute', 'sp_entityid', 'nameid_format', 'sign_messages',
		'sign_assertions', 'sign_authn_requests', 'sign_logout_requests', 'sign_logout_responses', 'encrypt_nameid',
		'encrypt_assertions', 'group_name', 'user_username', 'user_lastname', 'scim_status'
	];

	public const OUTPUT_FIELDS = [
		// Common output fields.
		'userdirectoryid', 'name', 'idp_type', 'provision_status', 'description',

		// LDAP and SAML main fields.
		'group_name', 'user_username', 'user_lastname',

		// LDAP output fields.
		'host', 'port', 'base_dn', 'search_attribute', 'bind_dn', 'start_tls', 'search_filter', 'group_basedn',
		'group_member', 'group_filter', 'group_membership', 'user_ref_attr',

		// SAML output fields.
		'idp_entityid', 'sso_url', 'slo_url', 'username_attribute', 'sp_entityid', 'nameid_format', 'sign_messages',
		'sign_assertions', 'sign_authn_requests', 'sign_logout_requests', 'sign_logout_responses', 'encrypt_nameid',
		'encrypt_assertions', 'scim_status'
	];

	public const MEDIA_OUTPUT_FIELDS = [
		'userdirectory_mediaid', 'mediatypeid', 'name', 'attribute', 'active', 'severity', 'period'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array|string
	 */
	public function get(array $options) {
		$this->validateGet($options);

		if (!$options['countOutput']) {
			if ($options['output'] === API_OUTPUT_EXTEND) {
				$options['output'] = self::OUTPUT_FIELDS;
			}

			$request_output = $options['output'];
			$db_userdirectories_by_type = [IDP_TYPE_LDAP => [], IDP_TYPE_SAML => []];
			$db_userdirectories = [];

			$options['output'] =
				array_merge(['idp_type'], array_intersect($request_output, self::COMMON_OUTPUT_FIELDS));

			$ldap_output = array_intersect($request_output, self::LDAP_OUTPUT_FIELDS);
			$saml_output = array_intersect($request_output, self::SAML_OUTPUT_FIELDS);
		}


		$result = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);
		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_userdirectories[$row['userdirectoryid']] = $row;
			$db_userdirectories_by_type[$row['idp_type']][] = $row['userdirectoryid'];
		}

		if ($db_userdirectories_by_type[IDP_TYPE_LDAP] && $ldap_output) {
			$sql_parts = [
				'select' => array_merge(['userdirectoryid'], $ldap_output),
				'from' => ['userdirectory_ldap'],
				'where' => [dbConditionInt('userdirectoryid', $db_userdirectories_by_type[IDP_TYPE_LDAP])]
			];

			$result = DBselect($this->createSelectQueryFromParts($sql_parts));
			while ($row = DBfetch($result)) {
				$db_userdirectories[$row['userdirectoryid']] += $row;
			}
		}

		if ($db_userdirectories_by_type[IDP_TYPE_SAML] && $saml_output) {
			$sql_parts = [
				'select' => array_merge(['userdirectoryid'], $saml_output),
				'from' => ['userdirectory_saml'],
				'where' => [dbConditionInt('userdirectoryid', $db_userdirectories_by_type[IDP_TYPE_SAML])]
			];

			$result = DBselect($this->createSelectQueryFromParts($sql_parts));
			while ($row = DBfetch($result)) {
				$db_userdirectories[$row['userdirectoryid']] += $row;
			}
		}

		if ($db_userdirectories) {
			$db_userdirectories = $this->addRelatedObjects($options, $db_userdirectories);
			$db_userdirectories = $this->unsetExtraFields($db_userdirectories, ['userdirectoryid', 'idp_type'],
				$request_output
			);

			if (!$options['preservekeys']) {
				$db_userdirectories = array_values($db_userdirectories);
			}
		}

		return $db_userdirectories;
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sql_parts);

		$selected_ldap_fields = [];
		foreach (self::LDAP_OUTPUT_FIELDS as $field) {
			if ($this->outputIsRequested($field, $options['output'])) {
				$selected_ldap_fields[] = 'ldap.'.$field;
			}
		}
		if ($selected_ldap_fields) {
			$sql_parts['left_join'][] = [
				'alias' => 'ldap',
				'table' => 'userdirectory_ldap',
				'using' => 'userdirectoryid'
			];
			$sql_parts['left_table'] = ['alias' => $tableAlias, 'table' => $tableName];

			if (!$options['countOutput']) {
				$sql_parts['select'] = array_merge($sql_parts['select'], $selected_ldap_fields);
			}
		}

		$selected_saml_fields = [];
		foreach (self::SAML_OUTPUT_FIELDS as $field) {
			if ($this->outputIsRequested($field, $options['output'])) {
				$selected_saml_fields[] = 'saml.'.$field;
			}
		}
		if ($selected_saml_fields) {
			$sql_parts['left_join'][] = [
				'alias' => 'saml',
				'table' => 'userdirectory_saml',
				'using' => 'userdirectoryid'
			];
			$sql_parts['left_table'] = ['alias' => $tableAlias, 'table' => $tableName];

			if (!$options['countOutput']) {
				$sql_parts['select'] = array_merge($sql_parts['select'], $selected_saml_fields);
			}
		}

		return $sql_parts;
	}

	private function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'userdirectoryids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['userdirectoryid', 'provision_status', 'idp_type']],
			'search' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'description']],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', CUserGroup::OUTPUT_FIELDS), 'default' => null],
			'selectProvisionMedia' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', self::MEDIA_OUTPUT_FIELDS), 'default' => null],
			'selectProvisionGroups' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['name', 'roleid', 'user_groups']), 'default' => null],
			// sort and limit
			'sortfield' =>					['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['name']), 'uniq' => true, 'default' => []],
			'sortorder' =>					['type' => API_SORTORDER, 'default' => []],
			'limit' =>						['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>				['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
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
			$output = ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode', 'userdirectoryid', 'mfa_status',
				'mfaid'
			];
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
			$options['selectProvisionMedia'] = self::MEDIA_OUTPUT_FIELDS;
		}

		$db_provisioning_media = DB::select('userdirectory_media', [
			'output' => array_merge($options['selectProvisionMedia'], ['userdirectoryid']),
			'filter' => [
				'userdirectoryid' => array_keys($result)
			]
		]);
		$requested_output = array_flip($options['selectProvisionMedia']);

		foreach ($db_provisioning_media as $db_provisioning_media) {
			$result[$db_provisioning_media['userdirectoryid']]['provision_media'][]
				= array_intersect_key($db_provisioning_media, $requested_output);
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
			$options['selectProvisionGroups'] = ['name', 'roleid', 'user_groups'];
		}

		$user_groups_index = array_search('user_groups', $options['selectProvisionGroups']);
		if ($user_groups_index !== false) {
			unset($options['selectProvisionGroups'][$user_groups_index]);
		}

		$db_provision_idpgroups = DB::select('userdirectory_idpgroup', [
			'output' => array_merge($options['selectProvisionGroups'],
				['userdirectoryid', 'userdirectory_idpgroupid']
			),
			'filter' => [
				'userdirectoryid' => array_keys($result)
			],
			'preservekeys' => true
		]);
		$provision_usergroups = [];

		if ($user_groups_index !== false && $db_provision_idpgroups) {
			$db_provision_usergroups = DB::select('userdirectory_usrgrp', [
				'output' => ['usrgrpid', 'userdirectory_idpgroupid'],
				'filter' => [
					'userdirectory_idpgroupid' => array_keys($db_provision_idpgroups)
				]
			]);

			foreach ($db_provision_usergroups as $usrgrp) {
				$provision_usergroups[$usrgrp['userdirectory_idpgroupid']][] = [
					'usrgrpid' => $usrgrp['usrgrpid']
				];
			}
		}

		foreach ($db_provision_idpgroups as $provision_idpgroupid => $db_provision_idpgroup) {
			$idpgroup = array_intersect_key($db_provision_idpgroup, array_flip($options['selectProvisionGroups']));

			if ($user_groups_index !== false && array_key_exists($provision_idpgroupid, $provision_usergroups)) {
				$idpgroup['user_groups'] = $provision_usergroups[$provision_idpgroupid];
			}

			$result[$db_provision_idpgroup['userdirectoryid']]['provision_groups'][] = $idpgroup;
		}
	}

	/**
	 * @param array $userdirectories
	 *
	 * @return array
	 */
	public function create(array $userdirectories): array {
		self::validateCreate($userdirectories);

		$userdirectoryids = DB::insert('userdirectory', $userdirectories);

		$ins_userdirectories_ldap = [];
		$ins_userdirectories_saml = [];
		$ldap_userdirectoryids = [];

		foreach ($userdirectories as $i => &$userdirectory) {
			$userdirectory['userdirectoryid'] = $userdirectoryids[$i];

			if ($userdirectory['idp_type'] == IDP_TYPE_LDAP) {
				$ins_userdirectories_ldap[] = array_intersect_key($userdirectory,
					array_flip(self::LDAP_OUTPUT_FIELDS) + array_flip(['userdirectoryid', 'bind_password'])
				);

				$ldap_userdirectoryids[] = $userdirectory['userdirectoryid'];
			}

			if ($userdirectory['idp_type'] == IDP_TYPE_SAML) {
				$ins_userdirectories_saml[] = array_intersect_key($userdirectory,
					array_flip(self::SAML_OUTPUT_FIELDS) + array_flip(['userdirectoryid'])
				);
			}
		}
		unset($userdirectory);

		if ($ins_userdirectories_ldap) {
			DB::insert('userdirectory_ldap', $ins_userdirectories_ldap, false);
		}

		if ($ins_userdirectories_saml) {
			DB::insert('userdirectory_saml', $ins_userdirectories_saml, false);
		}

		self::updateProvisionGroups($userdirectories);
		self::updateProvisionMedia($userdirectories);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_USERDIRECTORY, $userdirectories);

		if ($ldap_userdirectoryids) {
			self::setDefaultUserdirectory($ldap_userdirectoryids);
		}

		return ['userdirectoryids' => $userdirectoryids];
	}

	/**
	 * @param array $userdirectories
	 *
	 * @throws APIException
	 */
	private static function validateCreate(array &$userdirectories): void {
		$api_input_rules = self::getValidationRules();

		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($userdirectories);
		self::checkProvisionGroups($userdirectories);
		self::checkMediaTypes($userdirectories);
		self::checkSamlExists($userdirectories);
	}

	/**
	 * Validate if only one user directory of type IDP_TYPE_SAML exists.
	 *
	 * @throws APIException
	 */
	private static function checkSamlExists(array $userdirectories): void {
		$idps = array_column($userdirectories, 'idp_type');
		$idps_count = count(array_keys($idps, IDP_TYPE_SAML));

		if ($idps_count == 0) {
			return;
		}

		if ($idps_count == 1) {
			$idps_count += DB::select('userdirectory', [
				'countOutput' => true,
				'filter' => [
					'idp_type' => IDP_TYPE_SAML
				]
			]);
		}

		if ($idps_count > 1) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Only one user directory of type "%1$s" can exist.', IDP_TYPE_SAML)
			);
		}
	}

	private static function setDefaultUserdirectory(array $ldap_userdirectoryids): void {
		if (!self::checkOtherLdapUserdirectoryExists($ldap_userdirectoryids)) {
			API::Authentication()->update(['ldap_userdirectoryid' => reset($ldap_userdirectoryids)]);
		}
	}

	private static function checkOtherLdapUserdirectoryExists(array $userdirectoryids): bool {
		return (bool) DBfetch(DBselect(
			'SELECT u.userdirectoryid'.
			' FROM userdirectory u'.
			' WHERE '.dbConditionId('u.userdirectoryid', $userdirectoryids, true).
				' AND '.dbConditionInt('u.idp_type', [IDP_TYPE_LDAP]),
			1
		));
	}

	/**
	 * @param array $userdirectories
	 *
	 * @return array
	 */
	public function update(array $userdirectories): array {
		$this->validateUpdate($userdirectories, $db_userdirectories);

		self::addFieldDefaultsByType($userdirectories, $db_userdirectories);

		$upd_userdirectories = [];
		$upd_userdirectories_ldap = [];
		$upd_userdirectories_saml = [];

		foreach ($userdirectories as $userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

			$upd_userdirectory = DB::getUpdatedValues('userdirectory',
				array_intersect_key($userdirectory, array_flip(self::COMMON_OUTPUT_FIELDS)), $db_userdirectory
			);

			if ($upd_userdirectory) {
				$upd_userdirectories[] = [
					'values' => $upd_userdirectory,
					'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
				];
			}

			if ($userdirectory['idp_type'] == IDP_TYPE_LDAP) {
				$upd_userdirectory_ldap = DB::getUpdatedValues('userdirectory_ldap',
					array_intersect_key($userdirectory, array_flip(self::LDAP_OUTPUT_FIELDS) + ['bind_password' => '']),
					$db_userdirectory
				);

				if ($upd_userdirectory_ldap) {
					$upd_userdirectories_ldap[] = [
						'values' => $upd_userdirectory_ldap,
						'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
					];
				}
			}

			if ($userdirectory['idp_type'] == IDP_TYPE_SAML) {
				$upd_userdirectory_saml = DB::getUpdatedValues('userdirectory_saml',
					array_intersect_key($userdirectory, array_flip(self::SAML_OUTPUT_FIELDS)), $db_userdirectory
				);

				if ($upd_userdirectory_saml) {
					$upd_userdirectories_saml[] = [
						'values' => $upd_userdirectory_saml,
						'where' => ['userdirectoryid' => $userdirectory['userdirectoryid']]
					];
				}
			}
		}

		if ($upd_userdirectories) {
			DB::update('userdirectory', $upd_userdirectories);
		}

		if ($upd_userdirectories_ldap) {
			DB::update('userdirectory_ldap', $upd_userdirectories_ldap);
		}

		if ($upd_userdirectories_saml) {
			DB::update('userdirectory_saml', $upd_userdirectories_saml);
		}

		self::updateProvisionMedia($userdirectories, $db_userdirectories);
		self::updateProvisionGroups($userdirectories, $db_userdirectories);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_USERDIRECTORY, $userdirectories,
			$db_userdirectories
		);

		return ['userdirectoryids' => array_column($userdirectories, 'userdirectoryid')];
	}

	/**
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException
	 */
	private function validateUpdate(array &$userdirectories, ?array &$db_userdirectories): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['userdirectoryid']], 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'idp_type' =>			['type' => API_INT32, 'in' => implode(',', [IDP_TYPE_LDAP, IDP_TYPE_SAML])],
			'provision_status' =>	['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])]

		]];
		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_userdirectories = $this->get([
			'output' => self::OUTPUT_FIELDS,
			'userdirectoryids' => array_column($userdirectories, 'userdirectoryid'),
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectories)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($userdirectories as $i => &$userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];
			$userdirectory += [
				'idp_type' => $db_userdirectory['idp_type'],
				'provision_status' => $db_userdirectory['provision_status']
			];

			if ($userdirectory['idp_type'] != $db_userdirectory['idp_type']) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/'.($i + 1).'/idp_type', _('cannot be changed'))
				);
			}
		}
		unset($userdirectory);

		self::addRequiredFieldsByType($userdirectories, $db_userdirectories);

		$api_input_rules = self::getValidationRules(true);

		if (!CApiInputValidator::validate($api_input_rules, $userdirectories, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::addAffectedObjects($userdirectories, $db_userdirectories);

		self::validateProvisionMedias($userdirectories, $db_userdirectories);

		self::checkDuplicates($userdirectories, $db_userdirectories);
		self::checkProvisionGroups($userdirectories, $db_userdirectories);
		self::checkMediaTypes($userdirectories, $db_userdirectories);
	}

	private static function addRequiredFieldsByType(array &$userdirectories, array $db_userdirectories): void {
		foreach ($userdirectories as &$userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

			if ($userdirectory['provision_status'] != $db_userdirectory['provision_status']) {
				if ($userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED) {
					$userdirectory += ['provision_groups' => []];
				}
			}
		}
		unset($userdirectory);
	}

	private static function addAffectedObjects(array $userdirectories, array &$db_userdirectories): void {
		self::addAffectedProvisionGroups($userdirectories, $db_userdirectories);
		self::addAffectedProvisionMedia($userdirectories, $db_userdirectories);
	}

	private static function addAffectedProvisionGroups(array $userdirectories, array &$db_userdirectories): void {
		$userdirectoryids = [];

		foreach ($userdirectories as $userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

			if (array_key_exists('provision_groups', $userdirectory)
					|| ($userdirectory['provision_status'] != $db_userdirectory['provision_status']
						&& $db_userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED)) {
				$userdirectoryids[] = $userdirectory['userdirectoryid'];
				$db_userdirectories[$userdirectory['userdirectoryid']]['provision_groups'] = [];
			}
		}

		if (!$userdirectoryids) {
			return;
		}

		$options = [
			'output' => ['userdirectory_idpgroupid', 'userdirectoryid', 'roleid', 'name'],
			'filter' => ['userdirectoryid' => $userdirectoryids]
		];
		$result = DBselect(DB::makeSql('userdirectory_idpgroup', $options));

		$db_provision_groups = [];

		while ($row = DBfetch($result)) {
			$db_userdirectories[$row['userdirectoryid']]['provision_groups'][$row['userdirectory_idpgroupid']] =
				array_diff_key($row, array_flip(['userdirectoryid']));

			$db_provision_groups[$row['userdirectory_idpgroupid']] =
				&$db_userdirectories[$row['userdirectoryid']]['provision_groups'][$row['userdirectory_idpgroupid']];
		}

		if (!$db_provision_groups) {
			return;
		}

		$options = [
			'output' => ['userdirectory_usrgrpid', 'userdirectory_idpgroupid', 'usrgrpid'],
			'filter' => ['userdirectory_idpgroupid' => array_keys($db_provision_groups)]
		];
		$result = DBselect(DB::makeSql('userdirectory_usrgrp', $options));

		while ($row = DBfetch($result)) {
			$db_provision_groups[$row['userdirectory_idpgroupid']]['user_groups'][$row['userdirectory_usrgrpid']] =
				array_diff_key($row, array_flip(['userdirectory_idpgroupid']));
		}
	}

	private static function addAffectedProvisionMedia(array $userdirectories, array &$db_userdirectories): void {
		$userdirectoryids = [];

		foreach ($userdirectories as $userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

			if (array_key_exists('provision_media', $userdirectory)
					|| ($userdirectory['provision_status'] != $db_userdirectory['provision_status']
						&& $db_userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED)) {
				$userdirectoryids[] = $userdirectory['userdirectoryid'];
				$db_userdirectories[$userdirectory['userdirectoryid']]['provision_media'] = [];
			}
		}

		if (!$userdirectoryids) {
			return;
		}

		$options = [
			'output' => array_merge(self::MEDIA_OUTPUT_FIELDS, ['userdirectoryid']),
			'filter' => ['userdirectoryid' => $userdirectoryids]
		];
		$result = DBselect(DB::makeSql('userdirectory_media', $options));

		while ($row = DBfetch($result)) {
			$db_userdirectories[$row['userdirectoryid']]['provision_media'][$row['userdirectory_mediaid']] =
				array_diff_key($row, array_flip(['userdirectoryid']));
		}
	}

	/**
	 * @return array
	 */
	private static function getProvisionMediaValidationFields(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$specific_rules = $is_update
			? [
				'userdirectory_mediaid' =>	['type' => API_ANY]
			]
			: [];

		return $specific_rules + [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_media', 'name')],
			'mediatypeid' =>	['type' => API_ID, 'flags' => $api_required],
			'attribute' =>		['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_media', 'attribute')],
			'active' =>			['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])],
			'severity' =>		['type' => API_INT32, 'in' => '0:63'],
			'period' =>			['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('userdirectory_media', 'period')]
		];
	}

	private static function validateProvisionMedias(array &$userdirectories, array $db_userdirectories): void {
		foreach ($userdirectories as $i1 => &$userdirectory) {
			if (!array_key_exists('provision_media', $userdirectory)) {
				return;
			}

			$path = '/'.($i1 + 1).'/provision_media';

			$db_provision_medias =  $db_userdirectories[$userdirectory['userdirectoryid']]['provision_media'];

			foreach ($userdirectory['provision_media'] as $i2 => &$provision_media) {
				$is_update = array_key_exists('userdirectory_mediaid', $provision_media);

				if ($is_update) {
					if (!array_key_exists($provision_media['userdirectory_mediaid'], $db_provision_medias)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/'.($i2 + 1).'/userdirectory_mediaid',
							_('object does not exist or belongs to another object')
						));
					}

					$db_provision_media = $db_provision_medias[$provision_media['userdirectory_mediaid']];
					$provision_media += array_intersect_key($db_provision_media, array_flip(['mediatypeid', 'attribute']));
				}

				$api_input_rules = ['type' => API_OBJECT, 'fields' => self::getProvisionMediaValidationFields($is_update)];

				if (!CApiInputValidator::validate($api_input_rules, $provision_media, $path.'/'.($i2 + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
			unset($provision_media);

			$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['mediatypeid', 'attribute']], 'fields' => [
				'mediatypeid' =>	['type' => API_ANY],
				'attribute' =>		['type' => API_ANY]
			]];
			$data = $userdirectory['provision_media'];

			if (!CApiInputValidator::validateUniqueness($api_input_rules, $data, $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($userdirectory);
	}

	/**
	 * Check for unique names.
	 *
	 * @param array      $userdirectories
	 * @param array|null $db_userdirectories
	 *
	 * @throws APIException if userdirectory name is not unique.
	 */
	private static function checkDuplicates(array $userdirectories, ?array $db_userdirectories = null): void {
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
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('User directory "%1$s" already exists.', $duplicates[0]['name'])
			);
		}
	}

	private static function checkProvisionGroups(array $userdirectories, ?array $db_userdirectories = null): void {
		$role_indexes = [];
		$user_group_indexes = [];

		foreach ($userdirectories as $i1 => $userdirectory) {
			if ($userdirectory['provision_status'] != JIT_PROVISIONING_ENABLED
					|| !array_key_exists('provision_groups', $userdirectory)) {
				continue;
			}

			$db_provision_groups = $db_userdirectories !== null
				? array_column($db_userdirectories[$userdirectory['userdirectoryid']]['provision_groups'], null, 'name')
				: [];

			foreach ($userdirectory['provision_groups'] as $i2 => $provision_group) {
				$db_provision_group = array_key_exists($provision_group['name'], $db_provision_groups)
					? $db_provision_groups[$provision_group['name']]
					: null;

				if ($db_provision_group === null
						|| bccomp($provision_group['roleid'], $db_provision_group['roleid']) != 0) {
					$role_indexes[$provision_group['roleid']][$i1][] = $i2;
				}

				$db_usrgrpids = $db_provision_group !== null
					? array_column($db_provision_group['user_groups'], 'usrgrpid')
					: [];

				foreach ($provision_group['user_groups'] as $i3 => $user_group) {
					if (!in_array($user_group['usrgrpid'], $db_usrgrpids)) {
						$user_group_indexes[$user_group['usrgrpid']][$i1][$i2] = $i3;
					}
				}
			}
		}

		if ($role_indexes) {
			$db_roles = API::Role()->get([
				'output' => [],
				'roleids' => array_keys($role_indexes),
				'preservekeys' => true
			]);

			foreach ($role_indexes as $roleid => $indexes) {
				if (!array_key_exists($roleid, $db_roles)) {
					$i1 = key($indexes);
					$i2 = reset($indexes[$i1]);

					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i1 + 1).'/provision_groups/'.($i2 + 1).'/roleid', _('object does not exist')
					));
				}
			}
		}

		if ($user_group_indexes) {
			$db_user_groups = API::UserGroup()->get([
				'output' => [],
				'usrgrpids' => array_keys($user_group_indexes),
				'preservekeys' => true
			]);

			foreach ($user_group_indexes as $usrgrpid => $indexes) {
				if (!array_key_exists($usrgrpid, $db_user_groups)) {
					$i1 = key($indexes);
					$i2 = key($indexes[$i1]);
					$i3 = $indexes[$i1][$i2];

					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						'/'.($i1 + 1).'/provision_groups/'.($i2 + 1).'/user_groups/'.($i3 + 1),
						_('object does not exist')
					));
				}
			}
		}
	}

	private static function checkMediaTypes(array $userdirectories, ?array $db_userdirectories = null): void {
		$media_indexes = [];

		foreach ($userdirectories as $i1 => $userdirectory) {
			if ($userdirectory['provision_status'] != JIT_PROVISIONING_ENABLED
					|| !array_key_exists('provision_media', $userdirectory) || !$userdirectory['provision_media']) {
				continue;
			}

			$db_mediatypeids = $db_userdirectories !== null
				? array_column($db_userdirectories[$userdirectory['userdirectoryid']], 'mediatypeid', 'mediatypeid')
				: [];

			foreach ($userdirectory['provision_media'] as $i2 => $media) {
				if (!in_array($media['mediatypeid'], $db_mediatypeids)) {
					$media_indexes[$media['mediatypeid']][$i1][] = $i2;
				}
			}
		}

		if (!$media_indexes) {
			return;
		}

		$db_mediatypes = API::MediaType()->get([
			'output' => [],
			'mediatypeids' => array_keys($media_indexes),
			'preservekeys' => true
		]);

		foreach ($media_indexes as $mediatypeid => $indexes) {
			if (!array_key_exists($mediatypeid, $db_mediatypes)) {
				$i1 = key($indexes);
				$i2 = reset($indexes[$i1]);

				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					'/'.($i1 + 1).'/provision_media/'.($i2 + 1).'/mediatypeid', _('object does not exist')
				));
			}
		}
	}

	private static function addFieldDefaultsByType(array &$userdirectories, array $db_userdirectories): void {
		foreach ($userdirectories as &$userdirectory) {
			$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

			if ($userdirectory['provision_status'] != $db_userdirectory['provision_status']
					&& $db_userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED) {
				if ($userdirectory['idp_type'] == IDP_TYPE_LDAP) {
					$userdirectory += [
						'group_basedn' => DB::getDefault('userdirectory_ldap', 'group_basedn'),
						'user_ref_attr' => DB::getDefault('userdirectory_ldap', 'user_ref_attr'),
						'group_name' => DB::getDefault('userdirectory_ldap', 'group_name'),
						'user_username' => DB::getDefault('userdirectory_ldap', 'user_username'),
						'user_lastname' => DB::getDefault('userdirectory_ldap', 'user_lastname'),
						'group_member' => DB::getDefault('userdirectory_ldap', 'group_member'),
						'group_membership' => DB::getDefault('userdirectory_ldap', 'group_membership'),
						'provision_groups' => [],
						'provision_media' => []
					];
				}

				if ($userdirectory['idp_type'] == IDP_TYPE_SAML) {
					$userdirectory += [
						'group_name' => DB::getDefault('userdirectory_saml', 'group_name'),
						'user_username' => DB::getDefault('userdirectory_saml', 'user_username'),
						'user_lastname' => DB::getDefault('userdirectory_saml', 'user_lastname'),
						'provision_groups' => [],
						'provision_media' => []
					];
				}
			}
		}
		unset($userdirectory);
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

		DB::update('users', [[
			'values' => ['userdirectoryid' => 0],
			'where' => ['userdirectoryid' => $userdirectoryids]
		]]);

		self::deleteAffectedProvisionGroups($userdirectoryids);

		DB::delete('userdirectory_media', ['userdirectoryid' => $userdirectoryids]);
		DB::delete('userdirectory_ldap', ['userdirectoryid' => $userdirectoryids]);
		DB::delete('userdirectory_saml', ['userdirectoryid' => $userdirectoryids]);
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
			'output' => ['userdirectoryid', 'idp_type', 'name'],
			'userdirectoryids' => $userdirectoryids,
			'preservekeys' => true
		]);

		if (count($db_userdirectories) != count($userdirectoryids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$userdirectoryid_idptype = array_column($db_userdirectories, 'idp_type', 'userdirectoryid');
		$auth = API::Authentication()->get([
			'output' => ['ldap_userdirectoryid', 'authentication_type', 'ldap_auth_enabled', 'saml_auth_enabled']
		]);

		if ($auth['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED && in_array(IDP_TYPE_SAML, $userdirectoryid_idptype)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default user directory.'));
		}

		$ldap_userdirectories_delete = array_keys($userdirectoryid_idptype, IDP_TYPE_LDAP);
		$ldap_userdirectories_left = API::UserDirectory()->get([
			'countOutput' => true,
			'filter' => ['idp_type' => IDP_TYPE_LDAP]
		]);
		$ldap_userdirectories_left -= count($ldap_userdirectories_delete);

		// Default LDAP server cannot be removed if there are remaining LDAP servers.
		if (in_array($auth['ldap_userdirectoryid'], $userdirectoryids)
				&& ($auth['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED || $ldap_userdirectories_left > 0)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default user directory.'));
		}

		// Cannot remove the last remaining LDAP server if LDAP authentication is on.
		if ($auth['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED && $ldap_userdirectories_left == 0) {
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

	private static function deleteAffectedProvisionGroups(array $userdirectoryids): void {
		$del_provision_groupids = array_keys(DB::select('userdirectory_idpgroup', [
			'filter' => ['userdirectoryid' => $userdirectoryids],
			'preservekeys' => true
		]));

		if ($del_provision_groupids) {
			self::deleteProvisionGroups($del_provision_groupids);
		}
	}

	/**
	 * Test user against specific userdirectory connection.
	 * Return user data in LDAP
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function test(array $userdirectory): array {
		$this->validateTest($userdirectory);

		$user = [
			'username' => $userdirectory['test_username'],
			'password' => $userdirectory['test_password']
		];
		$ldap = new CLdap($userdirectory);
		$ldap_validator = new CLdapAuthValidator(['ldap' => $ldap]);

		if (!$ldap_validator->validate($user)) {
			self::exception(
				$ldap_validator->isConnectionError() ? ZBX_API_ERROR_PARAMETERS : ZBX_API_ERROR_PERMISSIONS,
				$ldap_validator->getError()
			);
		}

		if ($userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED) {
			$mapping_roles = [];

			if ($userdirectory['provision_groups']) {
				$mapping_roles = DB::select('role', [
					'output' => ['roleid', 'name', 'type'],
					'roleids' => array_column($userdirectory['provision_groups'], 'roleid', 'roleid'),
					'preservekeys' => true
				]);
			}

			$provisioning = new CProvisioning($userdirectory, $mapping_roles);
			$user = array_merge(
				$user,
				$ldap->getProvisionedData($provisioning, $user['username'])
			);

			if (array_key_exists('userdirectoryid', $userdirectory)) {
				$user['userdirectoryid'] = $userdirectory['userdirectoryid'];
			}
		}

		unset($user['password']);

		return $user;
	}

	/**
	 * Validate user directory and test user credentials to be used for testing.
	 *
	 * @param array $userdirectory
	 *
	 * @throws APIException
	 */
	protected function validateTest(array &$userdirectory): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'default' => 0]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectory, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($userdirectory['userdirectoryid'] != 0) {
			$db_userdirectory = $this->get([
				'output' => ['host', 'port', 'base_dn', 'bind_dn', 'search_attribute', 'start_tls', 'search_filter',
					'provision_status', 'idp_type'
				],
				'userdirectoryids' => [$userdirectory['userdirectoryid']],
				'filter' => ['idp_type' => IDP_TYPE_LDAP]
			]);

			if (!$db_userdirectory) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$userdirectory += reset($db_userdirectory);
			$userdirectory += DB::select('userdirectory_ldap', [
				'output' => ['bind_password'],
				'userdirectoryids' => [$userdirectory['userdirectoryid']]
			])[0];

			if ($userdirectory['provision_status'] == JIT_PROVISIONING_ENABLED) {
				$userdirectory += $this->get([
					'output' => ['group_basedn', 'group_name', 'group_member', 'group_filter', 'group_membership',
						'user_ref_attr', 'user_username', 'user_lastname'
					],
					'userdirectoryids' => $userdirectory['userdirectoryid'],
					'selectProvisionMedia' => self::MEDIA_OUTPUT_FIELDS,
					'selectProvisionGroups' => ['name', 'roleid', 'user_groups']
				])[0];
			}
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'userdirectoryid' =>	['type' => API_ID, 'default' => 0],
			'host' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'host')],
			'port' =>				['type' => API_PORT, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'base_dn' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'base_dn')],
			'bind_dn' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_dn'), 'default' => ''],
			'bind_password' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_password')],
			'search_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_attribute')],
			'start_tls' =>			['type' => API_INT32, 'in' => ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON, 'default' => ZBX_AUTH_START_TLS_OFF],
			'search_filter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_filter'), 'default' => ''],
			'provision_status' =>	['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]), 'default' => JIT_PROVISIONING_DISABLED],
			'group_basedn' =>		['type' => API_STRING_UTF8],
			'group_name' =>			['type' => API_STRING_UTF8],
			'group_member' =>		['type' => API_STRING_UTF8],
			'user_ref_attr' =>		['type' => API_STRING_UTF8],
			'group_filter' =>		['type' => API_STRING_UTF8],
			'group_membership' =>	['type' => API_STRING_UTF8],
			'user_username' =>		['type' => API_STRING_UTF8],
			'user_lastname' =>		['type' => API_STRING_UTF8],
			'idp_type' =>			['type' => API_INT32, 'in' => implode(',', [IDP_TYPE_LDAP]), 'default' => IDP_TYPE_LDAP],
			'provision_media' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'provision_status', 'in' => implode(',', [JIT_PROVISIONING_ENABLED])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['mediatypeid', 'attribute']], 'fields' => [
											'userdirectory_mediaid' =>	['type' => API_ID, 'default' => 0],
											'name' =>					['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_media', 'name')],
											'mediatypeid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
											'attribute' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_media', 'attribute')],
											'active' =>					['type' => API_INT32, 'in' => implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]), 'default' => DB::getDefault('userdirectory_media', 'active')],
											'severity' =>				['type' => API_INT32, 'in' => '0:63', 'default' => DB::getDefault('userdirectory_media', 'severity')],
											'period' =>					['type' => API_TIME_PERIOD, 'flags' => API_ALLOW_USER_MACRO, 'length' => DB::getFieldLength('userdirectory_media', 'period'), 'default' => DB::getDefault('userdirectory_media', 'period')]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'provision_groups' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'provision_status', 'in' => JIT_PROVISIONING_ENABLED],
											'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['name']], 'fields' => [
												'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
												'roleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
												'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
													'usrgrpid' =>			['type' => API_ID, 'flags' => API_REQUIRED]
												]]
											]
										],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'test_username' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
			'test_password' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $userdirectory, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function updateProvisionMedia(array &$userdirectories, ?array $db_userdirectories = null): void {
		$ins_provision_medias = [];
		$upd_provision_medias = [];
		$del_provision_mediaids = [];

		foreach ($userdirectories as $userdirectory) {
			if (!array_key_exists('provision_media', $userdirectory)) {
				continue;
			}

			$db_provision_medias = $db_userdirectories !== null
				? $db_userdirectories[$userdirectory['userdirectoryid']]['provision_media']
				: [];

			foreach ($userdirectory['provision_media'] as $provision_media) {
				if (array_key_exists('userdirectory_mediaid', $provision_media)) {
					$upd_provision_media = DB::getUpdatedValues('userdirectory_media', $provision_media,
						$db_provision_medias[$provision_media['userdirectory_mediaid']]
					);

					if ($upd_provision_media) {
						$upd_provision_medias[] = [
							'values' => $upd_provision_media,
							'where' => ['userdirectory_mediaid' => $provision_media['userdirectory_mediaid']]
						];
					}

					unset($db_provision_medias[$provision_media['userdirectory_mediaid']]);
				}
				else {
					$ins_provision_medias[] =
						['userdirectoryid' => $userdirectory['userdirectoryid']] + $provision_media;
				}
			}

			$del_provision_mediaids = array_merge($del_provision_mediaids, array_keys($db_provision_medias));
		}

		if ($del_provision_mediaids) {
			DB::delete('userdirectory_media', ['userdirectory_mediaid' => $del_provision_mediaids]);
		}

		if ($upd_provision_medias) {
			DB::update('userdirectory_media', $upd_provision_medias);
		}

		if ($ins_provision_medias) {
			$userdirectory_mediaids = DB::insert('userdirectory_media', $ins_provision_medias);
		}

		foreach ($userdirectories as &$userdirectory) {
			if (!array_key_exists('provision_media', $userdirectory)) {
				continue;
			}

			foreach ($userdirectory['provision_media'] as &$provision_media) {
				if (!array_key_exists('userdirectory_mediaid', $provision_media)) {
					$provision_media['userdirectory_mediaid'] = array_shift($userdirectory_mediaids);
				}
			}
			unset($provision_media);
		}
		unset($userdirectory);
	}

	private static function updateProvisionGroups(array &$userdirectories, ?array $db_userdirectories = null): void {
		$ins_provision_groups = [];
		$upd_provision_groups = [];
		$del_provision_groupids = [];

		foreach ($userdirectories as &$userdirectory) {
			if (!array_key_exists('provision_groups', $userdirectory)) {
				continue;
			}

			$db_provision_groups = $db_userdirectories !== null
				? array_column($db_userdirectories[$userdirectory['userdirectoryid']]['provision_groups'], null, 'name')
				: [];

			foreach ($userdirectory['provision_groups'] as &$provision_group) {
				if (array_key_exists($provision_group['name'], $db_provision_groups)) {
					$db_provision_group = $db_provision_groups[$provision_group['name']];
					$provision_group['userdirectory_idpgroupid'] = $db_provision_group['userdirectory_idpgroupid'];

					$upd_provision_group =
						DB::getUpdatedValues('userdirectory_idpgroup', $provision_group, $db_provision_group);

					if ($upd_provision_group) {
						$upd_provision_groups[] = [
							'values' => $upd_provision_group,
							'where' => ['userdirectory_idpgroupid' => $db_provision_group['userdirectory_idpgroupid']]
						];
					}

					unset($db_provision_groups[$provision_group['name']]);
				}
				else {
					$ins_provision_groups[] =
						['userdirectoryid' => $userdirectory['userdirectoryid']] + $provision_group;
				}
			}
			unset($provision_group);

			$del_provision_groupids =
				array_merge($del_provision_groupids, array_column($db_provision_groups, 'userdirectory_idpgroupid'));
		}
		unset($userdirectory);

		if ($del_provision_groupids) {
			self::deleteProvisionGroups($del_provision_groupids);
		}

		if ($upd_provision_groups) {
			DB::update('userdirectory_idpgroup', $upd_provision_groups);
		}

		if ($ins_provision_groups) {
			$userdirectory_idpgroupids = DB::insert('userdirectory_idpgroup', $ins_provision_groups);
		}

		$provision_groups = [];
		$db_provision_groups = $db_userdirectories !== null ? [] : null;

		foreach ($userdirectories as &$userdirectory) {
			if (!array_key_exists('provision_groups', $userdirectory)) {
				continue;
			}

			foreach ($userdirectory['provision_groups'] as &$provision_group) {
				if (!array_key_exists('userdirectory_idpgroupid', $provision_group)) {
					$provision_group['userdirectory_idpgroupid'] = array_shift($userdirectory_idpgroupids);

					if ($db_userdirectories !== null) {
						$db_provision_groups[$provision_group['userdirectory_idpgroupid']] = [
							'userdirectory_idpgroupid' => $provision_group['userdirectory_idpgroupid'],
							'user_groups' => []
						];
					}
				}
				else {
					$db_userdirectory = $db_userdirectories[$userdirectory['userdirectoryid']];

					$db_provision_groups[$provision_group['userdirectory_idpgroupid']] =
						$db_userdirectory['provision_groups'][$provision_group['userdirectory_idpgroupid']];
				}

				$provision_groups[] = &$provision_group;
			}
			unset($provision_group);
		}
		unset($userdirectory);

		if ($provision_groups) {
			self::updateProvisionGroupUserGroups($provision_groups, $db_provision_groups);
		}
	}

	private static function deleteProvisionGroups(array $del_provision_groupids): void {
		DB::delete('userdirectory_usrgrp', ['userdirectory_idpgroupid' => $del_provision_groupids]);
		DB::delete('userdirectory_idpgroup', ['userdirectory_idpgroupid' => $del_provision_groupids]);
	}

	private static function updateProvisionGroupUserGroups(array &$provision_groups,
			?array $db_provision_groups): void {
		$ins_user_groups = [];
		$del_user_groupids = [];

		foreach ($provision_groups as &$provision_group) {
			$idpgroupid = $provision_group['userdirectory_idpgroupid'];
			$db_user_groups = $db_provision_groups !== null && array_key_exists($idpgroupid, $db_provision_groups)
				? array_column($db_provision_groups[$idpgroupid]['user_groups'], null, 'usrgrpid')
				: [];

			foreach ($provision_group['user_groups'] as &$user_group) {
				if (array_key_exists($user_group['usrgrpid'], $db_user_groups)) {
					$user_group['userdirectory_usrgrpid'] =
						$db_user_groups[$user_group['usrgrpid']]['userdirectory_usrgrpid'];

					unset($db_user_groups[$user_group['usrgrpid']]);
				}
				else {
					$ins_user_groups[] =
						['userdirectory_idpgroupid' => $provision_group['userdirectory_idpgroupid']] + $user_group;
				}
			}
			unset($user_group);

			$del_user_groupids =
				array_merge($del_user_groupids, array_column($db_user_groups, 'userdirectory_usrgrpid'));
		}
		unset($provision_group);

		if ($del_user_groupids) {
			DB::delete('userdirectory_usrgrp', ['userdirectory_usrgrpid' => $del_user_groupids]);
		}

		if ($ins_user_groups) {
			$userdirectory_usrgrpids = DB::insert('userdirectory_usrgrp', $ins_user_groups);
		}

		foreach ($provision_groups as &$provision_group) {
			foreach ($provision_group['user_groups'] as &$user_group) {
				if (!array_key_exists('userdirectory_usrgrpid', $user_group)) {
					$user_group['userdirectory_usrgrpid'] = array_shift($userdirectory_usrgrpids);
				}
			}
			unset($user_group);
		}
		unset($provision_group);
	}

	private static function getValidationRules(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$specific_fields = $is_update
			? [
				'userdirectoryid' =>	['type' => API_ANY],
				'idp_type' =>			['type' => API_ANY],
				'provision_status' =>	['type' => API_ANY]
			]
			: [];

		$provision_media_rule = $is_update
			? ['type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['userdirectory_mediaid']], 'fields' => [
				'userdirectory_mediaid' =>	['type' => API_ANY]
			]]
			: ['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['mediatypeid', 'attribute']], 'fields' => self::getProvisionMediaValidationFields()];

		return ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => $specific_fields + [
			'idp_type' =>			['type' => API_INT32, 'flags' => $api_required, 'in' => implode(',', [IDP_TYPE_LDAP, IDP_TYPE_SAML])],
			'name' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory', 'name')],
										['else' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'name')] + ($is_update ? [] : ['default' => DB::getDefault('userdirectory', 'name')])
			]],
			'provision_status' =>	['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED]), 'default' => DB::getDefault('userdirectory', 'provision_status')],
			'description' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory', 'description')],
			'host' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'host')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'port' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_PORT, 'flags' => $api_required | API_NOT_EMPTY],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'base_dn' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'base_dn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'bind_dn' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_dn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'bind_password' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'bind_password')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'search_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'start_tls' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_INT32, 'in' => implode(',', [ZBX_AUTH_START_TLS_OFF, ZBX_AUTH_START_TLS_ON])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'search_filter' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'search_filter')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_basedn' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_basedn')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'user_ref_attr' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'user_ref_attr')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_name' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_name')],
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'group_name')]
			]],
			'user_username' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'user_username')],
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'user_username')]
			]],
			'user_lastname' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'user_lastname')],
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'user_lastname')]
			]],
			'group_member' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_member')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_filter' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_filter')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'group_membership' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_LDAP])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_ldap', 'group_membership')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'idp_entityid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'idp_entityid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sso_url' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'sso_url')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'slo_url' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'slo_url')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sp_entityid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'sp_entityid')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'nameid_format' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'nameid_format')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'username_attribute' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('userdirectory_saml', 'username_attribute')],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_messages' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_assertions' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_authn_requests' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_logout_requests' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'sign_logout_responses' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'encrypt_nameid' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'encrypt_assertions' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'scim_status' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'idp_type', 'in' => implode(',', [IDP_TYPE_SAML])], 'type' => API_INT32, 'in' => implode(',', ['0', '1'])],
										['else' => true, 'type' => API_UNEXPECTED]
			]],
			'provision_groups' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'provision_status', 'in' => implode(',', [JIT_PROVISIONING_ENABLED])], 'type' => API_OBJECTS, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
											'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('userdirectory_idpgroup', 'name')],
											'roleid' =>				['type' => API_ID, 'flags' => API_REQUIRED],
											'user_groups' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
												'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
											]]
										]],
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]],
			'provision_media' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'provision_status', 'in' => implode(',', [JIT_PROVISIONING_ENABLED])]] + $provision_media_rule,
										['else' => true, 'type' => API_OBJECTS, 'length' => 0]
			]]
		]];
	}
}
