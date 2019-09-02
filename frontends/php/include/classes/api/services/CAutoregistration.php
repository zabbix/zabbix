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
	 * Update autoregistration configuration.
	 *
	 * @param array  $autoreg
	 * @param int    $autoreg['tls_accept']
	 * @param string $autoreg['tls_psk_identity']
	 * @param string $autoreg['tls_psk']
	 *
	 * @throws APIException if incorrect encryption options.
	 *
	 * @return true if no errors
	 */
	public function update(array $autoreg) {
		$db_autoreg = [];
		$this->validateUpdate($autoreg, $db_autoreg);

		reset($db_autoreg);
		$autoreg['autoreg_tlsid'] = key($db_autoreg);
		$update = [];

		if ($autoreg['tls_accept'] == HOST_ENCRYPTION_NONE) {
			$autoreg['tls_psk_identity'] = '';
			$autoreg['tls_psk'] = '';
		}
		$audit = $autoreg;

		update_config(['autoreg_tls_accept' => $autoreg['tls_accept']]);
		unset($autoreg['tls_accept']);

		if ($autoreg) {
			$update[] = [
				'values' => $autoreg,
				'where' => ['autoreg_tlsid' => $autoreg['autoreg_tlsid']]
			];
		}

		DB::update('config_autoreg_tls', $update);

		$config = select_config();
		$audit['configid'] = $config['configid'];
		$old_audit = reset($db_autoreg);

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_AUTOREGISTRATION, [$audit],
				[$config['configid'] => $old_audit]
		);

		return true;
	}

	/**
	 * Validate autoregistration connections and PSK fields.
	 *
	 * @param array  $autoreg
	 * @param int    $autoreg['tls_accept']
	 * @param string $autoreg['tls_psk_identity']
	 * @param string $autoreg['tls_psk']
	 * @param array  $db_autoreg                                       (optional)
	 * @param int    $db_autoreg[<autoreg_tlsid>]['autoreg_tlsid']
	 * @param int    $db_autoreg[<autoreg_tlsid>]['tls_accept']
	 * @param string $db_autoreg[<autoreg_tlsid>]['tls_psk_identity']
	 * @param string $db_autoreg[<autoreg_tlsid>]['tls_psk']
	 * @param int    $autoreg_tlsid
	 *
	 * @throws APIException if incorrect encryption options.
	 */
	protected function validateUpdate(array &$autoreg, array &$db_autoreg) {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_NOT_EMPTY, 'fields' => [
			'tls_accept' =>			['type' => API_INT32, 'in' => HOST_ENCRYPTION_NONE.':'.(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK)],
			'tls_psk_identity' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')],
			'tls_psk' =>			['type' => API_PSK, 'length' => DB::getFieldLength('config_autoreg_tls', 'tls_psk')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $autoreg, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_autoreg = $this->getAutoreg(['output' => API_OUTPUT_EXTEND], true);

		$tls_accept = array_key_exists('tls_accept', $autoreg) ? $autoreg['tls_accept'] : HOST_ENCRYPTION_NONE;
		$tls_psk_identity = array_key_exists('tls_psk_identity', $autoreg) ? $autoreg['tls_psk_identity'] : '';
		$tls_psk = array_key_exists('tls_psk', $autoreg) ? $autoreg['tls_psk'] : '';

		$autoreg['tls_accept'] = $tls_accept;

		// PSK validation.
		if ($tls_accept & HOST_ENCRYPTION_PSK) {
			if ($tls_psk_identity === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('cannot be empty'))
				);
			}

			if ($tls_psk === '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('cannot be empty'))
				);
			}
		}
		else {
			if (array_key_exists('tls_psk_identity', $autoreg) && $autoreg['tls_psk_identity'] !== '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('should be empty'))
				);
			}

			if (array_key_exists('tls_psk', $autoreg) && $autoreg['tls_psk'] !== '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('should be empty'))
				);
			}
		}
		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
