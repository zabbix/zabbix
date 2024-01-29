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
	protected $output_fields = ['mfaid', 'type', 'name', 'hash_function', 'code_length', 'api_hostname', 'clientid',
		'client_secret'
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
				$options['output'] = $this->output_fields;
			}

			$request_output = $options['output'];
			$db_mfas = [];

			$options['output'] = array_intersect($request_output, $this->output_fields);
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
			$db_mfas = $this->unsetExtraFields($db_mfas, ['mfaid'], $request_output);

			if (!$options['preservekeys']) {
				$db_mfas = array_values($db_mfas);
			}
		}

		return $db_mfas;
	}

	private function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'mfaids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['mfaid', 'type']],
			'search' =>						['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>				['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>				['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>				['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>		['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>						['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>				['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>				['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode']), 'default' => null],
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
			$output = ['usrgrpid', 'name', 'gui_access', 'users_status', 'debug_mode', 'mfaid'];
		}
		else {
			$output = array_unique(array_merge(['mfaid'], $options['selectUsrgrps']));
		}

		$default_mfaid = CAuthenticationHelper::get(CAuthenticationHelper::MFAID);
		if (array_key_exists($default_mfaid, $result)) {
			$db_usergroups_default_mfa = API::UserGroup()->get([
				'output' => $output,
				'mfaid' => null,
				'mfa_status' => GROUP_MFA_ENABLED
			]);

			$result[$default_mfaid]['usrgrps'][] = array_diff_key($db_usergroups_default_mfa, array_flip(['mfaid']));
		}

		$db_usergroups = API::UserGroup()->get([
			'output' => $output,
			'mfaids' => array_keys($result)
		]);

		foreach ($db_usergroups as $db_usergroup) {
			$result[$db_usergroup['mfaid']]['usrgrps'][] =
				array_diff_key($db_usergroup, array_flip(['mfaid']));
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

		$db_mfa_method_count = DB::select('mfa', ['countOutput' => true]);

		$mfaids = DB::insert('mfa', $mfas);

		foreach ($mfas as $index => &$mfa) {
			$mfa['mfaid'] = $mfaids[$index];
		}
		unset($mfa);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MFA, $mfas);

		if ($db_mfa_method_count == 0 && $mfaids) {
			$mfaid = reset($mfaids);

			API::Authentication()->update(['mfaid' => $mfaid]);
		}

		return ['mfaids' => $mfaids];
	}

	/**
	 * @param array $mfas
	 *
	 * @throws APIException
	 */
	private function validateCreate(array &$mfas): void {
		$api_input_rules = $this->getValidationRules();

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
			if (!array_key_exists('name', $mfa)) {
				continue;
			}

			if ($db_mfas === null
				|| $mfa['name'] !== $db_mfas[$mfa['mfaid']]['name']) {
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
			$upd_mfa = DB::getUpdatedValues('mfa', array_intersect_key($mfa, array_flip($this->output_fields)),
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

		$api_input_rules = $this->getValidationRules('update');
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

		DB::delete('mfa_totp_secret', ['mfaid' => $mfaids]);
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

	private function getValidationRules(string $method = 'create'): array {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [MFA_TYPE_TOTP, MFA_TYPE_DUO])],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('mfa', 'name')],
			'hash_function' =>		['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'in' => implode(',', [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512]), 'default' => DB::getDefault('mfa', 'hash_function')],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'code_length' =>		['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'in' => implode(',', [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8]), 'default' => DB::getDefault('mfa', 'code_length')],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'api_hostname' =>		['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'default' => DB::getDefault('mfa', 'api_hostname')],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'clientid' =>		['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'default' => DB::getDefault('mfa', 'clientid')],
				['else' => true, 'type' => API_UNEXPECTED]
			]],
			'client_secret' =>		['type' => API_MULTIPLE, 'rules' => [
				['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'default' => DB::getDefault('mfa', 'client_secret')],
				['else' => true, 'type' => API_UNEXPECTED]
			]]
		]];

		if ($method === 'update') {
			// Make all fields optional and remove default values.
			foreach ($api_input_rules['fields'] as &$field) {
				if (array_key_exists('rules', $field)) {
					foreach ($field['rules'] as &$rule) {
						if (array_key_exists('flags', $rule) && API_REQUIRED & $rule['flags']) {
							$rule['flags'] &= ~API_REQUIRED;
						}
						unset($rule['default']);
					}
					unset($rule);
				}
				else {
					if (array_key_exists('flags', $field) && API_REQUIRED & $field['flags']) {
						$field['flags'] &= ~API_REQUIRED;
					}
					unset($field['default']);
				}
			}
			unset($field);

			$api_input_rules['fields']['mfaid'] = ['type' => API_ID, 'flags' => API_REQUIRED];
		}

		return $api_input_rules;
	}
}
