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
	 * @param array   $options
	 * @param array   $options['output']
	 *
	 * @return array
	 */
	public function get($options) {
		if (self::$userData['type'] < USER_TYPE_ZABBIX_ADMIN) {
			return [];
		}

		if ($options && count($options) == 1 && array_key_exists('output', $options)) {
			return $this->getAutoreg($options);
		}
		else {
			return [];
		}
	}

	/**
	 * Get auto registration configuration.
	 *
	 * @param array   $options
	 * @param array   $options['output']
	 * @param boolean $options['preservekeys'] (internal use only)
	 *
	 * @return array
	 */
	protected function getAutoreg($options) {
		$result = [];

		$sqlParts = [
			'select'	=> ['config_autoreg_tls' => 'ca.autoreg_tlsid'],
			'from'		=> ['config_autoreg_tls' => 'config_autoreg_tls ca'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			// output
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => false
		];
		$options = zbx_array_merge($defOptions, $options);

		$ini_autoreg = [];

		if ($options['output'] == API_OUTPUT_EXTEND || in_array('tls_accept', $options['output'])) {
			$config = select_config();
			$ini_autoreg['tls_accept'] = $config['autoreg_tls_accept'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($autoreg = DBfetch($res)) {
			$autoreg = $ini_autoreg + $autoreg;
			$result[$autoreg['autoreg_tlsid']] = $autoreg;
		}

		if ($options['preservekeys']) {
			return $result;
		}

		$result = reset($result);
		unset($result['autoreg_tlsid']);

		return $result;
	}

	/**
	 * Update auto registration configuration.
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
	public function update($autoreg) {
		$db_autoreg = $this->getAutoreg(['preservekeys' => true]);
		$autoreg_tlsid = $this->getAutoregTlsId($db_autoreg);

		$this->validateUpdate($autoreg, $db_autoreg, $autoreg_tlsid);
		$update = [];

		if (!empty($autoreg)) {
			update_config(['autoreg_tls_accept' => $autoreg['tls_accept']]);

			$update[] = [
				'values' => $autoreg,
				'where' => ['autoreg_tlsid' => $autoreg_tlsid]
			];
		}
		DB::update('config_autoreg_tls', $update);

		return true;
	}

	/**
	 * Get the ID of an existing configuration entry or create a new one.
	 *
	 * @param array $db_autoreg
	 * @param int    $db_autoreg[<autoreg_tlsid>]['autoreg_tlsid']
	 * @param int    $db_autoreg[<autoreg_tlsid>]['tls_accept']
	 * @param string $db_autoreg[<autoreg_tlsid>]['tls_psk_identity']
	 * @param string $db_autoreg[<autoreg_tlsid>]['tls_psk']
	 *
	 * @return int
	 */
	protected function getAutoregTlsId($db_autoreg) {
		if ($db_autoreg) {
			reset($db_autoreg);
			$autoreg_tlsid = key($db_autoreg);
		}
		else {
			$db_autoreg = [
				'tls_psk_identity' => '',
				'tls_psk' => ''
			];
			$autoreg_tlsids = DB::insert('config_autoreg_tls', zbx_toArray($db_autoreg));
			$autoreg_tlsid = reset($autoreg_tlsids);
		}

		return $autoreg_tlsid;
	}

	/**
	 * Validate auto registration connections and PSK fields.
	 *
	 * @param array  $autoreg
	 * @param int    $autoreg['tls_accept']
	 * @param string $autoreg['tls_psk_identity']
	 * @param string $autoreg['tls_psk']
	 * @param array  $db_autoreg                 (optional)
	 * @param int    $db_autoreg[<autoreg_tlsid>]['autoreg_tlsid']
	 * @param int    $db_autoreg[<autoreg_tlsid>]['tls_accept']
	 * @param string $db_autoreg[<autoreg_tlsid>]['tls_psk_identity']
	 * @param string $db_autoreg[<autoreg_tlsid>]['tls_psk']
	 * @param int $autoreg_tlsid
	 *
	 * @throws APIException if incorrect encryption options.
	 */
	protected function validateUpdate(array $autoreg, array $db_autoreg = [], $autoreg_tlsid = null) {
		$min_accept_type = HOST_ENCRYPTION_NONE;
		$max_accept_type = HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK;

		$tls_accept = array_key_exists('tls_accept', $autoreg)
			? $autoreg['tls_accept']
			: ($db_autoreg ? $db_autoreg[$autoreg_tlsid]['tls_accept'] : HOST_ENCRYPTION_NONE);

		if ($tls_accept < $min_accept_type || $tls_accept > $max_accept_type) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.', 'tls_accept',
				_s('unexpected value "%1$s"', $tls_accept)
			));
		}

		foreach (['tls_psk_identity', 'tls_psk'] as $field_name) {
			$$field_name = array_key_exists($field_name, $autoreg)
				? $autoreg[$field_name]
				: ($db_autoreg ? $db_autoreg[$autoreg_tlsid][$field_name] : '');
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
			if ($tls_psk_identity !== '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk_identity', _('should be empty'))
				);
			}

			if ($tls_psk !== '') {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', 'tls_psk', _('should be empty'))
				);
			}
		}

	}

}
