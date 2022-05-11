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
 * Converter for converting import data from 4.2 to 4.4.
 */
class C42ImportConverter extends CConverter {

	public function convert($data) {
		$data['zabbix_export']['version'] = '4.4';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertInventoryMode($data['zabbix_export']['hosts']);
			$data['zabbix_export']['hosts'] = $this->convertTlsAccept($data['zabbix_export']['hosts']);
		}

		$data['zabbix_export'] = $this->convertFormat($data['zabbix_export']);

		return $data;
	}

	/**
	 * Convert inventory mode.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertInventoryMode(array $hosts) {
		foreach ($hosts as &$host) {
			$host['inventory_mode'] = CXmlConstantValue::INV_MODE_MANUAL;

			if (array_key_exists('inventory', $host) && array_key_exists('inventory_mode', $host['inventory'])) {
				$host['inventory_mode'] = $host['inventory']['inventory_mode'];
				unset($host['inventory']['inventory_mode']);
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Convert tsl_accept tag.
	 *
	 * @param array $hosts
	 *
	 * @return array
	 */
	protected function convertTlsAccept(array $hosts) {
		$const = [
			CXmlConstantValue::NO_ENCRYPTION => [CXmlConstantValue::NO_ENCRYPTION],
			CXmlConstantValue::TLS_PSK => [CXmlConstantValue::TLS_PSK],
			3 => [CXmlConstantValue::NO_ENCRYPTION, CXmlConstantValue::TLS_PSK],
			CXmlConstantValue::TLS_CERTIFICATE => [CXmlConstantValue::TLS_CERTIFICATE],
			5 => [CXmlConstantValue::NO_ENCRYPTION, CXmlConstantValue::TLS_CERTIFICATE],
			6 => [CXmlConstantValue::TLS_PSK, CXmlConstantValue::TLS_CERTIFICATE],
			7 => [CXmlConstantValue::NO_ENCRYPTION, CXmlConstantValue::TLS_PSK, CXmlConstantValue::TLS_CERTIFICATE]
		];

		foreach ($hosts as &$host) {
			if (array_key_exists('tls_accept', $host)) {
				$host['tls_accept'] = ($host['tls_accept'] === '')
					? $const[CXmlConstantValue::NO_ENCRYPTION]
					: $const[$host['tls_accept']];
			}
		}
		unset($host);

		return $hosts;
	}

	/**
	 * Update imported data array to format used starting from Zabbix version 4.4.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function convertFormat(array $data) {
		$schema = (new C44XmlValidator('xml'))->getSchema();

		foreach ($schema['rules'] as $tag => $tag_rules) {
			if (!array_key_exists($tag, $data)) {
				continue;
			}

			$data[$tag] = $this->convertEmptyTags($data[$tag], $tag_rules);
		}

		return $this->convertValueToConstant($data, $schema);
	}

	/**
	 * Convert values to human readable constants.
	 *
	 * @param array|string $data
	 * @param array        $rules
	 *
	 * @return array|string
	 */
	protected function convertValueToConstant($data, array $rules) {
		if ($rules['type'] & XML_STRING) {
			/*
			 * Second condition may occur when, for example, item types are no longer supported, but previous validator
			 * only checked the syntax, not the data.
			 */
			if (!array_key_exists('in', $rules) || !array_key_exists($data, $rules['in'])) {
				return $data;
			}

			$data = $rules['in'][$data];
		}
		elseif ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				if (array_key_exists($tag, $data)) {
					if (array_key_exists('ex_rules', $tag_rules)) {
						$tag_rules = call_user_func($tag_rules['ex_rules'], $data);
					}
					$data[$tag] = $this->convertValueToConstant($data[$tag], $tag_rules);
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			if (is_array($data)) {
				foreach ($data as $tag => $value) {
					$data[$tag] = $this->convertValueToConstant($value, $rules['rules'][$prefix]);
				}
			}
		}

		return $data;
	}

	/**
	 * Delete empty non-required tags.
	 *
	 * @param array|string $data
	 * @param array        $rules
	 *
	 * @return array|string
	 */
	protected function convertEmptyTags($data, $rules) {
		if ($rules['type'] & XML_ARRAY) {
			foreach ($rules['rules'] as $tag => $tag_rules) {
				if (array_key_exists($tag, $data)) {
					if ($tag_rules['type'] & XML_STRING) {
						if ($data[$tag] === '') {
							if ($tag_rules['type'] & XML_REQUIRED) {
								continue;
							}
							unset($data[$tag]);
						}
						continue;
					}

					if ($data[$tag] === '' || (is_array($data[$tag]) && !$data[$tag])) {
						if ($tag_rules['type'] & XML_REQUIRED) {
							continue;
						}

						unset($data[$tag]);
						continue;
					}

					$data[$tag] = $this->convertEmptyTags($data[$tag], $tag_rules);

					if ($data[$tag] === '') {
						unset($data[$tag]);
					}
				}
			}
		}
		elseif ($rules['type'] & XML_INDEXED_ARRAY) {
			$prefix = $rules['prefix'];

			foreach ($data as $tag => $value) {
				$data[$tag] = $this->convertEmptyTags($value, $rules['rules'][$prefix]);
			}
		}

		if (is_array($data) && count($data) == 0) {
			return '';
		}

		return $data;
	}
}
