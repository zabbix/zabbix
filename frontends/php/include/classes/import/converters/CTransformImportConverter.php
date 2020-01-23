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
 * Converters for convert XML values to values that API can accept.
 */
class CTransformImportConverter extends CConverter {

	protected $rules;

	public function __construct(array $schema) {
		$this->rules = $schema;
	}

	public function convert($data) {
		$data['zabbix_export'] = $this->convertTlsAccept($data['zabbix_export']);

		return $data;
	}

	/**
	 * Converter for tls_accept tag.
	 * Problem that in XML schema we store tag value as array, but API may work only with string for this tag.
	 * And it is why preproccesor option in XmlValidator does not suit us.
	 *
	 * @param array $data
	 * @param array $rules
	 *
	 * @return array
	 */
	protected function convertTlsAccept(array $data) {
		// Exit if import does not have hosts.
		if (!array_key_exists('hosts', $data)) {
			return $data;
		}

		foreach ($data['hosts'] as &$host) {
			// If tls_accept not default value.
			if (is_array($host['tls_accept'])) {
				$result = 0;
				// Convert tls_accept values for API.
				foreach ($host['tls_accept'] as $value) {
					$result += $value;
				}

				$host['tls_accept'] = (string) $result;
			}
		}

		return $data;
	}
}
