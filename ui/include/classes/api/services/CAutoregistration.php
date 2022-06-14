<?php
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


/**
 * Class containing methods for operations with autoregistration.
 */
class CAutoregistration extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'config';
	protected $tableAlias = 'c';

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
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

		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return [];
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = ['tls_accept'];
		}

		$options['output'] = preg_replace("/^(tls_accept)$/", "autoreg_$1", $options['output']);

		$db_autoreg = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options));
		while ($row = DBfetch($result)) {
			$db_autoreg[] = $row;
		}

		if ($this->outputIsRequested('autoreg_tls_accept', $options['output'])) {
			$db_autoreg = CArrayHelper::renameObjectsKeys($db_autoreg, ['autoreg_tls_accept' => 'tls_accept']);
		}

		$db_autoreg = $this->unsetExtraFields($db_autoreg, ['configid'], []);

		return $db_autoreg[0];
	}

	/**
	 * @param array $autoreg
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return bool
	 */
	public function update(array $autoreg) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'autoregistration', __FUNCTION__)
			);
		}

		$this->validateUpdate($autoreg, $db_autoreg);

		$upd_config = [];
		$upd_config_autoreg_tls = [];

		if (array_key_exists('tls_accept', $autoreg) && $autoreg['tls_accept'] != $db_autoreg['tls_accept']) {
			$upd_config['autoreg_tls_accept'] = $autoreg['tls_accept'];
		}

		// strings
		foreach (['tls_psk_identity', 'tls_psk'] as $field_name) {
			if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] !== $db_autoreg[$field_name]) {
				$upd_config_autoreg_tls[$field_name] = $autoreg[$field_name];
			}
		}

		if ($upd_config) {
			DB::update('config', [
				'values' => $upd_config,
				'where' => ['configid' => $db_autoreg['configid']]
			]);
		}

		if ($upd_config_autoreg_tls) {
			DB::update('config_autoreg_tls', [
				'values' => $upd_config_autoreg_tls,
				'where' => ['autoreg_tlsid' => $db_autoreg['autoreg_tlsid']]
			]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_AUTOREGISTRATION,
			[['configid' => $db_autoreg['configid']] + $autoreg], [$db_autoreg['configid'] => $db_autoreg]
		);

		return true;
	}

	/**
	 * @param array      $autoreg
	 * @param array|null $db_autoreg
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$autoreg, array &$db_autoreg = null): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'tls_accept' =>			['type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK)],
			'tls_psk_identity' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')],
			'tls_psk' =>			['type' => API_PSK, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $autoreg, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_autoreg = DBfetch(DBselect(
			"SELECT c.configid,c.autoreg_tls_accept AS tls_accept,ca.autoreg_tlsid,ca.tls_psk_identity,ca.tls_psk".
			" FROM config c,config_autoreg_tls ca"
		));

		$tls_accept = array_key_exists('tls_accept', $autoreg) ? $autoreg['tls_accept'] : $db_autoreg['tls_accept'];

		// PSK validation.
		foreach (['tls_psk_identity', 'tls_psk'] as $field_name) {
			if ($tls_accept & HOST_ENCRYPTION_PSK) {
				if (!array_key_exists($field_name, $autoreg) && $db_autoreg[$field_name] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/', _s('the parameter "%1$s" is missing', $field_name))
					);
				}

				if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.$field_name, _('cannot be empty'))
					);
				}
			}
			else {
				if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/'.$field_name, _('should be empty'))
					);
				}

				if (!array_key_exists($field_name, $autoreg) && $db_autoreg[$field_name] !== '') {
					$autoreg[$field_name] = '';
				}
			}
		}
	}
}
