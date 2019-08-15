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
 * Class containing methods for operations with auto registration.
 */
class CAutoregistration extends CApiService {

	protected $tableName = 'config_autoreg_tls';
	protected $tableAlias = 'ca';

	/**
	 * Get auto registration configuration.
	 *
	 * @param array    $options
	 * @param array    $options['output']
	 *
	 * @return array
	 */
	public function get(array $options) {
		return (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) ? $this->getAutoreg($options) :[];
	}

	/**
	 * Internal get auto registration configuration.
	 *
	 * @param array    $options
	 * @param array    $options['output']
	 * @param boolean  $update_mode
	 *
	 * @return array
	 */
	protected function getAutoreg(array $options, $update_mode = false) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',',	['tls_accept', 'tls_psk_identity', 'tls_psk']), 'default' => API_OUTPUT_EXTEND]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$sql_parts = [
			'select'	=> ['config_autoreg_tls' => 'ca.autoreg_tlsid'],
			'from'		=> ['config_autoreg_tls' => 'config_autoreg_tls ca'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$def_options = [
			// output
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => $update_mode
		];
		$options = zbx_array_merge($def_options, $options);

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
	 * Update auto registration configuration.
	 *
	 * @param array   $autoreg
	 * @param int     $autoreg['tls_accept']
	 * @param string  $autoreg['tls_psk_identity']
	 * @param string  $autoreg['tls_psk']
	 *
	 * @throws APIException if incorrect encryption options.
	 *
	 * @return true if no errors
	 */
	public function update(array $autoreg) {
		$db_autoreg = $this->getAutoreg(['output' => API_OUTPUT_EXTEND], true);
		reset($db_autoreg);
		$autoreg['autoreg_tlsid'] = key($db_autoreg);

		$this->validateUpdate($autoreg, $db_autoreg);
		$update = [];

		if ($autoreg) {
			if ($autoreg['tls_accept'] == HOST_ENCRYPTION_NONE) {
				$autoreg['tls_psk_identity'] = '';
				$autoreg['tls_psk'] = '';
			}

			update_config(['autoreg_tls_accept' => $autoreg['tls_accept']]);
			unset($autoreg['tls_accept']);

			if ($autoreg) {
				$update[] = [
					'values' => $autoreg,
					'where' => ['autoreg_tlsid' => $autoreg['autoreg_tlsid']]
				];
			}
		}
		DB::update('config_autoreg_tls', $update);

		return true;
	}

	/**
	 * Validate auto registration connections and PSK fields.
	 *
	 * @param array   $autoreg
	 * @param int     $autoreg['tls_accept']
	 * @param string  $autoreg['tls_psk_identity']
	 * @param string  $autoreg['tls_psk']
	 * @param array   $db_autoreg                 (optional)
	 * @param int     $db_autoreg[<autoreg_tlsid>]['autoreg_tlsid']
	 * @param int     $db_autoreg[<autoreg_tlsid>]['tls_accept']
	 * @param string  $db_autoreg[<autoreg_tlsid>]['tls_psk_identity']
	 * @param string  $db_autoreg[<autoreg_tlsid>]['tls_psk']
	 * @param int     $autoreg_tlsid
	 *
	 * @throws APIException if incorrect encryption options.
	 */
	protected function validateUpdate(array $autoreg, array $db_autoreg = []) {
		// Check permissions.
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		if (!$autoreg) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		foreach (['tls_accept', 'tls_psk_identity', 'tls_psk'] as $field_name) {
			if (array_key_exists($field_name, $autoreg)) {
				$$field_name = $autoreg[$field_name];
			}
			elseif ($db_autoreg) {
				$$field_name = $db_autoreg[$autoreg['autoreg_tlsid']][$field_name];
			}
			elseif ($field_name === 'tls_accept') {
				$$field_name = HOST_ENCRYPTION_NONE;
			}
			else {
				$$field_name = '';
			}
		}

		$autoreg['tls_accept'] = $tls_accept;

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['autoreg_tlsid']], 'fields' => [
				'autoreg_tlsid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
				'tls_accept' =>			['type' => API_INT32, 'in' => implode(',', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK])],
				'tls_psk_identity' =>	['type' => API_STRING_UTF8],
				'tls_psk' =>			['type' => API_STRING_UTF8]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $autoreg, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

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

			if (!preg_match('/^([0-9a-f]{2})+$/i', $tls_psk)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_psk',
					_('an even number of hexadecimal characters is expected')
				));
			}

			if (strlen($tls_psk) < PSK_MIN_LEN) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_psk',
					_s('minimum length is %1$s characters', PSK_MIN_LEN)
				));
			}
		}
		else {
			$autoreg = reset($autoreg);

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
	}
}
