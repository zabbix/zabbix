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

	public function get(array $options) {
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
		}

		return $options['preservekeys'] ? $db_mfas : array_values($db_mfas);
	}

	private static function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'mfaids' =>					['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['mfaid', 'type']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			'selectUsrgrps' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_ALLOW_COUNT, 'in' => implode(',', array_diff(CUserGroup::OUTPUT_FIELDS, ['mfaid'])), 'default' => null],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', ['name']), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
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
			$row['usrgrps'] = $options['selectUsrgrps'] === API_OUTPUT_COUNT ? '0' : [];
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

		foreach ($output as &$field_name) {
			$field_name = 'ug.'.$field_name;
		}
		unset($field_name);

		$default_mfaid = CAuthenticationHelper::get(CAuthenticationHelper::MFAID);
		$default_mfaid_condition = array_key_exists($default_mfaid, $result)
			? ' OR (ug.mfaid IS NULL AND '.dbConditionInt('ug.mfa_status', [GROUP_MFA_ENABLED]).')'
			: '';

		$db_user_groups = DBselect(
			'SELECT '.implode(',', $output).
			' FROM usrgrp ug'.
			' WHERE '.dbConditionId('ug.mfaid', array_keys($result)).
				$default_mfaid_condition
		);

		while ($db_user_group = DBfetch($db_user_groups)) {
			$mfaid = $db_user_group['mfaid'] == 0 ? $default_mfaid : $db_user_group['mfaid'];

			if ($options['selectUsrgrps'] === API_OUTPUT_COUNT) {
				$result[$mfaid]['usrgrps'] = (string) ($result[$mfaid]['usrgrps'] + 1);
			}
			else {
				$result[$mfaid]['usrgrps'][] = array_diff_key($db_user_group, array_flip(['mfaid']));
			}
		}
	}

	public function create(array $mfas): array {
		$this->validateCreate($mfas);

		$mfaids = DB::insert('mfa', $mfas);

		foreach ($mfas as $i => &$mfa) {
			$mfa['mfaid'] = $mfaids[$i];
		}
		unset($mfa);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_MFA, $mfas);

		self::setDefaultMfaid($mfaids);

		return ['mfaids' => $mfaids];
	}

	private function validateCreate(array &$mfas): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [MFA_TYPE_TOTP, MFA_TYPE_DUO])],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('mfa', 'name')],
			'hash_function' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512])],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('mfa', 'hash_function')]
			]],
			'code_length' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_TOTP], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8])],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('mfa', 'code_length')]
			]],
			'api_hostname' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'api_hostname')]
			]],
			'clientid' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('mfa', 'clientid')]
			]],
			'client_secret' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => MFA_TYPE_DUO], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY],
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
	private static function checkDuplicates(array $mfas, ?array $db_mfas = null): void {
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

	private static function setDefaultMfaid(array $mfaids): void {
		if (!self::checkOtherMfaExists($mfaids)) {
			API::Authentication()->update(['mfaid' => reset($mfaids)]);
		}
	}

	private static function checkOtherMfaExists(array $mfaids): bool {
		return (bool) DBfetch(DBselect(
			'SELECT m.mfaid'.
			' FROM mfa m'.
			' WHERE '.dbConditionId('m.mfaid', $mfaids, true),
			1
		));
	}

	public function update(array $mfas): array {
		$this->validateUpdate($mfas, $db_mfas);

		self::addFieldDefaultsByType($mfas);

		$upd_mfas = [];

		foreach ($mfas as $mfa) {
			$upd_mfa = DB::getUpdatedValues('mfa', $mfa, $db_mfas[$mfa['mfaid']]);

			if ($upd_mfa) {
				$upd_mfas[] = [
					'values' => $upd_mfa,
					'where' => ['mfaid' => $mfa['mfaid']]
				];
			}
		}

		DB::update('mfa', $upd_mfas);

		self::deleteOutdatedTotpSecrets($upd_mfas);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_MFA, $mfas, $db_mfas);

		return ['mfaids' => array_column($mfas, 'mfaid')];
	}

	private function validateUpdate(array &$mfas, ?array &$db_mfas): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['mfaid']], 'fields' => [
			'mfaid' =>	['type' => API_ID, 'flags' => API_REQUIRED],
			'type' =>	['type' => API_INT32, 'flags' =>  API_NOT_EMPTY, 'in' => implode(',', [MFA_TYPE_TOTP, MFA_TYPE_DUO])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $mfas, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mfas = DB::select('mfa', [
			'output' => array_merge(self::OUTPUT_FIELDS, ['client_secret']),
			'mfaids' => array_column($mfas, 'mfaid'),
			'preservekeys' => true
		]);

		if (count($db_mfas) != count($mfas)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$mfas = $this->extendObjectsByKey($mfas, $db_mfas, 'mfaid', ['type']);

		self::addRequiredFieldsByType($mfas, $db_mfas);

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'mfaid' =>			['type' => API_ANY],
			'type' =>			['type' => API_ANY],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('mfa', 'name')],
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

		self::checkDuplicates($mfas, $db_mfas);
	}

	private static function addRequiredFieldsByType(array &$mfas, array $db_mfas): void {
		foreach ($mfas as &$mfa) {
			if ($mfa['type'] != $db_mfas[$mfa['mfaid']]['type']) {
				if ($mfa['type'] == MFA_TYPE_TOTP) {
					$mfa += array_intersect_key($db_mfas[$mfa['mfaid']], array_flip(['hash_function', 'code_length']));
				}

				if ($mfa['type'] == MFA_TYPE_DUO) {
					$mfa += array_intersect_key($db_mfas[$mfa['mfaid']],
						array_flip(['api_hostname', 'clientid', 'client_secret'])
					);
				}
			}
		}
		unset($mfa);
	}

	private static function addFieldDefaultsByType(array &$mfas): void {
		$db_defaults = DB::getDefaults('mfa');

		foreach ($mfas as &$mfa) {
			if ($mfa['type'] != MFA_TYPE_TOTP) {
				$mfa += [
					'hash_function' => $db_defaults['hash_function'],
					'code_length' => $db_defaults['code_length']
				];
			}

			if ($mfa['type'] != MFA_TYPE_DUO) {
				$mfa += [
					'api_hostname' => $db_defaults['api_hostname'],
					'clientid' => $db_defaults['clientid'],
					'client_secret' => $db_defaults['client_secret']
				];
			}
		}
		unset($mfa);
	}

	private static function deleteOutdatedTotpSecrets(array $upd_mfas): void {
		$mfaids = [];
		$sensitive_fields = array_flip(['type', 'hash_function', 'code_length']);

		foreach ($upd_mfas as $upd_mfa) {
			$sensitive_changes = array_intersect_key($upd_mfa['values'], $sensitive_fields);

			if (!$sensitive_changes || (array_key_exists('type', $sensitive_changes)
					&& $upd_mfa['values']['type'] == MFA_TYPE_TOTP)) {
				continue;
			}

			$mfaids[] = $upd_mfa['where']['mfaid'];
		}

		if ($mfaids) {
			DB::delete('mfa_totp_secret', ['mfaid' => $mfaids]);
		}
	}

	public function delete(array $mfaids): array {
		self::validateDelete($mfaids, $db_mfas);

		if (array_key_exists(CAuthenticationHelper::get(CAuthenticationHelper::MFAID), $db_mfas)) {
			API::Authentication()->update(['mfaid' => 0]);
		}

		DB::delete('mfa_totp_secret', ['mfaid' => array_keys($db_mfas)]);
		DB::delete('mfa', ['mfaid' => array_keys($db_mfas)]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_MFA, $db_mfas);

		return ['mfaids' => array_keys($db_mfas)];
	}

	private static function validateDelete(array $mfaids, ?array &$db_mfas): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $mfaids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_mfas = DB::select('mfa', [
			'output' => ['mfaid', 'name'],
			'mfaids' => $mfaids,
			'preservekeys' => true
		]);

		if (count($db_mfas) != count($mfaids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDeleteDefaultMfa($db_mfas);
		self::cehckMfaUsedByUserGroup($db_mfas);
	}

	private static function checkDeleteDefaultMfa(array $db_mfas): void {
		if (in_array(CAuthenticationHelper::get(CAuthenticationHelper::MFAID), array_keys($db_mfas))
				&& CAuthenticationHelper::get(CAuthenticationHelper::MFA_STATUS) == MFA_ENABLED
				&& !self::checkOtherMfaExists(array_keys($db_mfas))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete default MFA method.'));
		}
	}

	private static function cehckMfaUsedByUserGroup(array $db_mfas) {
		$db_groups = API::UserGroup()->get([
			'output' => ['name', 'mfaid'],
			'mfaids' => array_keys($db_mfas),
			'limit' => 1
		]);

		if ($db_groups) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Cannot delete MFA method "%1$s", because it is used by user group "%2$s".',
					$db_mfas[$db_groups[0]['mfaid']]['name'],
					$db_groups[0]['name']
				)
			);
		}
	}
}
