<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

	protected $tableName = 'config_autoreg_tls';
	protected $tableAlias = 'ca';

	/**
	 * Get autoregistration configuration.
	 *
	 * @param array $options
	 * @param array $options['output']
	 *
	 * @return array
	 */
	public function get(array $options) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',', ['tls_accept']), 'default' => API_OUTPUT_EXTEND]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$result = (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) ? $this->getAutoreg($options) : [];

		return array_diff_key($result, ['tls_psk_identity' => true, 'tls_psk' => true]);
	}

	/**
	 * Internal get autoregistration configuration.
	 *
	 * @param array $options
	 * @param array $options['output']
	 * @param bool  $update_mode
	 *
	 * @return array
	 */
	protected function getAutoreg(array $options, $update_mode = false) {
		$sql_parts = [
			'select'	=> ['config_autoreg_tls' => 'ca.autoreg_tlsid'],
			'from'		=> ['config_autoreg_tls' => 'config_autoreg_tls ca'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$def_options = [
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => $update_mode
		];
		$options = array_merge($def_options, $options);

		$ini_autoreg = [];

		if ($options['output'] == API_OUTPUT_EXTEND || in_array('tls_accept', $options['output'])) {
			$config = select_config();
			$ini_autoreg['tls_accept'] = $config['autoreg_tls_accept'];
		}

		$result = [];
		$sql_parts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sql_parts);

		$res = DBselect($this->createSelectQueryFromParts($sql_parts), $sql_parts['limit']);
		while ($autoreg = DBfetch($res)) {
			$autoreg = $ini_autoreg + $autoreg;
			$result[$autoreg['autoreg_tlsid']] = $autoreg;
		}

		if (!$result || $update_mode) {
			return $result;
		}

		$result = reset($result);
		unset($result['autoreg_tlsid']);

		return $result;
	}

	/**
	 * @param array  $autoreg
	 *
	 * @return bool
	 */
	public function update(array $autoreg) {
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

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_AUTOREGISTRATION,
			[['configid' => $db_autoreg['configid']] + $autoreg], [$db_autoreg['configid'] => $db_autoreg]
		);

		return true;
	}

	/**
	 * @param array  $autoreg
	 * @param array  $db_autoreg
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array &$autoreg, array &$db_autoreg = null) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'tls_accept' =>			['type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK)],
			'tls_psk_identity' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')],
			'tls_psk' =>			['type' => API_PSK, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk')]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $autoreg, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', '/',
						_s('the parameter "%1$s" is missing', $field_name)
					));
				}

				if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', '/'.$field_name, _('cannot be empty'))
					);
				}
			}
			else {
				if (array_key_exists($field_name, $autoreg) && $autoreg[$field_name] !== '') {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', '/'.$field_name, _('should be empty'))
					);
				}

				if (!array_key_exists($field_name, $autoreg) && $db_autoreg[$field_name] !== '') {
					$autoreg[$field_name] = '';
				}
			}
		}
	}
}
