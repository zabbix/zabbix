<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CMfa extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'mfa';
	protected $tableAlias = 'm';
	protected $sortColumns = ['name'];

	/**
	 * Common MFA properties.
	 *
	 * @var array
	 */
	public const OUTPUT_FIELDS = ['mfaid', 'type', 'name', 'hash_function', 'code_length', 'api_hostname', 'clientid'];

	public function get(array $options): array|string {
		self::validateGet($options);

		if (!$options['countOutput']) {
			if ($options['output'] === API_OUTPUT_EXTEND) {
				$options['output'] = self::OUTPUT_FIELDS;
			}

			$db_mfas = [];
		}

		$result = DBselect($this->createSelectQuery($this->tableName, $options), $options['limit']);

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_mfas[$row['mfaid']] = $row;
		}

		if ($db_mfas) {
			$db_mfas = $this->addRelatedObjects($options, $db_mfas);
			$db_mfas = $this->unsetExtraFields($db_mfas, ['mfaid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_mfas = array_values($db_mfas);
			}
		}

		return $db_mfas;
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'mfaids' =>						['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['mfaid', 'type']],
			'search' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', array_diff(CUserGroup::OUTPUT_FIELDS, ['mfaid'])), 'default' => null],
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
		self::addRelatedUserGroups($options, $result);

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
			$output = ['mfaid'];
		}
		elseif ($options['selectUsrgrps'] === API_OUTPUT_EXTEND) {
			$output = CUserGroup::OUTPUT_FIELDS;
		}
		else {
			$output = array_merge(['mfaid'], $options['selectUsrgrps']);
		}

		$default_mfaid = CAuthenticationHelper::get(CAuthenticationHelper::MFAID);

		if (array_key_exists($default_mfaid, $result)) {
			$db_usergroup_options = [
				'output' => $output,
				'filter' => ['mfaid' => 0, 'mfa_status' => GROUP_MFA_ENABLED]
			];
			$db_usergroups = DBselect(DB::makeSql('usrgrp', $db_usergroup_options));

			while ($db_usergroup = DBfetch($db_usergroups)) {
				if (!in_array('usrgrpid', $output)) {
					unset($db_usergroup['usrgrpid']);
				}

				$result[$default_mfaid]['usrgrps'][] = array_diff_key($db_usergroup, array_flip(['mfaid']));
			}
		}

		$db_usergroup_options = [
			'output' => $output,
			'filter' => ['mfaid' => array_keys($result)]
		];
		$db_usergroups = DBselect(DB::makeSql('usrgrp', $db_usergroup_options));

		while ($db_usergroup = DBfetch($db_usergroups)) {
			if (!in_array('usrgrpid', $output)) {
				unset($db_usergroup['usrgrpid']);
			}

			$result[$db_usergroup['mfaid']]['usrgrps'][] = array_diff_key($db_usergroup, array_flip(['mfaid']));
		}

		if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
			foreach ($result as &$row) {
				$row['usrgrps'] = (string) count($row['usrgrps']);
			}
			unset($row);
		}
	}

	/**
	 * @param array $mfas
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $mfas): array {
		$this->validateCreate($mfas);

		$no_mfa_methods = DB::select('mfa', ['countOutput' => true]) == 0;

		$mfaids = DB::insert('mfa', $mfas);

		foreach ($mfas as $i => &$mfa) {
			$mfa['mfaid'] = $mfaids[$i];
		}
		unset($mfa);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MFA, $mfas);

		if ($no_mfa_methods) {
			API::Authentication()->update(['mfaid' => reset($mfaids)]);
		}

		return ['mfaids' => $mfaids];
	}

	/**
	 * @param array $mfas
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$mfas): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [MFA_TYPE_TOTP, MFA_TYPE_DUO])],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('mfa', 'name')],
			'hash_function' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'in' => implode(',', [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512])],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('mfa', 'hash_function')]
			]],
			'code_length' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'in' => implode(',', [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8])],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('mfa', 'code_length')]
			]],
			'api_hostname' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'api_hostname')]
			]],
			'clientid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'clientid')]
			]],
			'client_secret' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'client_secret')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mfas, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($mfas);
	}

	/**
	 * Check for unique names.
	 *
	 * @param array      $mfas
	 * @param array|null $db_mfas
	 *
	 * @throws APIException if MFA method name is not unique.
	 */
	private static function checkDuplicates(array $mfas, array $db_mfas = null): void {
		$names = [];

		foreach ($mfas as $mfa) {
			if (array_key_exists('name', $mfa)
					&& ($db_mfas === null || $mfa['name'] !== $db_mfas[$mfa['mfaid']]['name'])) {
				$names[] = $mfa['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('mfa', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('MFA method "%1$s" already exists.', $duplicates[0]['name'])
			);
		}
	}

	/**
	 * @param array $mfas
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function update(array $mfas): array {
		$this->validateUpdate($mfas, $db_mfas);

		$upd_mfas = [];

		foreach ($mfas as $mfaid => $mfa) {
			$upd_mfa = DB::getUpdatedValues('mfa',
				array_intersect_key($mfa, array_flip(self::OUTPUT_FIELDS) + ['client_secret' => '']),
				$db_mfas[$mfaid]
			);

			if ($upd_mfa) {
				$upd_mfas[] = [
					'values' => $upd_mfa,
					'where' => ['mfaid' => $mfaid]
				];
			}
		}

		DB::update('mfa', $upd_mfas);
		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MFA, $mfas, $db_mfas);

		return ['mfaids' => array_column($mfas, 'mfaid')];
	}

	/**
	 * @param array $mfas
	 * @param array|null $db_mfas
	 * @return void
	 * @throws APIException
	 */
	private function validateUpdate(array &$mfas, ?array &$db_mfas): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['mfaid']], 'fields' => [
			'mfaid' => ['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mfas, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mfas = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'mfaids' => array_column($mfas, 'mfaid'),
			'preservekeys' => true
		]);

		if (count($db_mfas) != count($mfas)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		foreach ($mfas as &$mfa) {
			if (!array_key_exists('type', $mfa)) {
				$db_mfa = $db_mfas[$mfa['mfaid']];
				$mfa += ['type' => $db_mfa['type']];
			}
		}
		unset($mfa);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'mfaid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'type' =>			['type' => API_INT32, 'flags' =>  API_NOT_EMPTY, 'in' => implode(',', [MFA_TYPE_TOTP, MFA_TYPE_DUO])],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('mfa', 'name')],
			'hash_function' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'in' => implode(',', [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512]), 'default' => DB::getDefault('mfa', 'hash_function')],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('mfa', 'hash_function')]
			]],
			'code_length' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'in' => implode(',', [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8]), 'default' => DB::getDefault('mfa', 'code_length')],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('mfa', 'code_length')]
			]],
			'api_hostname' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'api_hostname')]
			]],
			'clientid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'clientid')]
			]],
			'client_secret' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'client_secret')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mfas, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$mfas = array_column($mfas, null, 'mfaid');

		self::checkDuplicates($mfas, $db_mfas);
	}

	/**
	 * @param array $mfaids
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function delete(array $mfaids): array {
		self::validateDelete($mfaids, $db_mfaids);

		DB::delete('mfa', ['mfaid' => $mfaids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MFA, $db_mfaids);

		return ['mfaids' => $mfaids];
	}

	/**
	 * @param array      $mfaids
	 * @param array|null $db_mfaids
	 *
	 * @throws APIException
	 */
	private static function validateDelete(array $mfaids, ?array &$db_mfas): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $mfaids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mfas = API::Mfa()->get([
			'output' => ['mfaid', 'type', 'name'],
			'mfaids' => $mfaids,
			'preservekeys' => true
		]);

		if (count($db_mfas) != count($mfaids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$mfas_left = API::Mfa()->get(['countOutput' => true]);
		$mfas_left -= count($db_mfas);

		$auth = API::Authentication()->get([
			'output' => ['mfa_status', 'mfaid']
		]);

		// Default MFA method cannot be removed if there are remaining MFA methods.
		if (in_array($auth['mfaid'], $mfaids)
			&& ($auth['mfa_status'] == MFA_ENABLED || $mfas_left > 0)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default MFA method.'));
		}

		// Cannot remove the last remaining MFA method if MFA authentication is enabled.
		if ($auth['mfa_status'] == MFA_ENABLED && $mfas_left == 0) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default MFA method.'));
		}

		$db_groups = API::UserGroup()->get([
			'output' => ['mfaid'],
			'mfaids' => $mfaids,
			'limit' => 1
		]);

		if ($db_groups) {
			$db_group = reset($db_groups);

			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot delete MFA method "%1$s".',
					$db_mfas[$db_group['mfaid']]['name'])
			);
		}

		if (in_array($auth['mfaid'], $mfaids)) {
			// If last (default) is removed, reset default mfaid to prevent from foreign key constraint.
			API::Authentication()->update(['mfaid' => 0]);
		}
	}
}
