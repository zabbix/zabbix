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


/**
 * Class containing methods for operations with autoregistration.
 */
class CAutoregistration extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function get(array $options) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => 'tls_accept', 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = ['tls_accept'];
		}

		$tls_accept_index = array_search('tls_accept', $options['output']);
		$db_output = $tls_accept_index !== false
			? array_replace($options['output'], [$tls_accept_index => 'autoreg_tls_accept'])
			: $options['output'];

		return CArrayHelper::renameKeys(CApiSettingsHelper::getParameters($db_output),
			['autoreg_tls_accept' => 'tls_accept']
		);
	}

	/**
	 * @param array $autoreg
	 *
	 * @throws APIException
	 *
	 * @return bool
	 */
	public function update(array $autoreg) {
		$this->validateUpdate($autoreg, $db_settings, $db_autoreg_tls);

		self::addFieldDefaultsByTls($autoreg, $db_settings);

		CApiSettingsHelper::updateParameters(['autoreg_tls_accept' => $autoreg['tls_accept']], $db_settings);

		$upd_autoreg_tls = DB::getUpdatedValues('config_autoreg_tls', $autoreg, $db_autoreg_tls);

		if ($upd_autoreg_tls) {
			DB::update('config_autoreg_tls', [
				'values' => $upd_autoreg_tls,
				'where' => ['autoreg_tlsid' => $db_autoreg_tls['autoreg_tlsid']]
			]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_AUTOREGISTRATION, [$autoreg],
			[CArrayHelper::renameKeys($db_settings, ['autoreg_tls_accept' => 'tls_accept']) + $db_autoreg_tls]
		);

		return true;
	}

	/**
	 * @param array      $autoreg
	 * @param array|null $db_settings
	 * @param array|null $db_autoreg_tls
	 *
	 * @throws APIException
	 */
	protected function validateUpdate(array &$autoreg, ?array &$db_settings = null,
			?array &$db_autoreg_tls = null): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'tls_accept' =>	['type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK)]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $autoreg, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_settings = CApiSettingsHelper::getParameters(['autoreg_tls_accept']);
		$db_autoreg_tls = current(DB::select('config_autoreg_tls', [
			'output' => ['autoreg_tlsid', 'tls_psk_identity', 'tls_psk'],
			'limit' => 1
		]));

		$autoreg += ['tls_accept' => $db_settings['autoreg_tls_accept']];

		self::addRequiredFieldsByTls($autoreg, $db_settings, $db_autoreg_tls);

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'tls_accept' =>			['type' => API_ANY],
			'tls_psk_identity' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0, 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('config_autoreg_tls', 'tls_psk_identity')]
			]],
			'tls_psk' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => ($data['tls_accept'] & HOST_ENCRYPTION_PSK) != 0, 'type' => API_PSK, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('config_autoreg_tls', 'tls_psk')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $autoreg, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkTlsPskPairs($autoreg, $db_autoreg_tls);
	}

	private static function addRequiredFieldsByTls(array &$autoreg, array $db_settings, array $db_autoreg_tls): void {
		if ($autoreg['tls_accept'] & HOST_ENCRYPTION_PSK
				&& ($db_settings['autoreg_tls_accept'] & HOST_ENCRYPTION_PSK) == 0) {
			$autoreg += array_intersect_key($db_autoreg_tls, array_flip(['tls_psk_identity', 'tls_psk']));
		}
	}

	private static function checkTlsPskPairs(array $autoreg, array $db_autoreg_tls): void {
		if ($autoreg['tls_accept'] & HOST_ENCRYPTION_PSK) {
			$tls_psk_fields = array_flip(['tls_psk_identity', 'tls_psk']);

			$psk_pair = array_intersect_key($autoreg, $tls_psk_fields);

			if ($psk_pair) {
				$psk_pair += array_intersect_key($db_autoreg_tls, $tls_psk_fields);

				CApiPskHelper::checkPskOfIdentityAmongHosts($psk_pair);
				CApiPskHelper::checkPskOfIdentityAmongProxies($psk_pair);
			}
		}
	}

	private static function addFieldDefaultsByTls(array &$autoreg, array $db_settings): void {
		if (($autoreg['tls_accept'] & HOST_ENCRYPTION_PSK) == 0
				&& $db_settings['autoreg_tls_accept'] & HOST_ENCRYPTION_PSK) {
			$autoreg += [
				'tls_psk_identity' => DB::getDefault('config_autoreg_tls', 'tls_psk_identity'),
				'tls_psk' => DB::getDefault('config_autoreg_tls', 'tls_psk')
			];
		}
	}
}
