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
 * Converter for converting import data from 4.4 to 5.0.
 */
class C44ImportConverter extends CConverter {

	public function convert($data) {
		$data['zabbix_export']['version'] = '5.0';

		if (array_key_exists('hosts', $data['zabbix_export'])) {
			$data['zabbix_export']['hosts'] = $this->convertTlsAccept($data['zabbix_export']['hosts']);
		}

		return $data;
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
			CXmlConstantValue::NO_ENCRYPTION => [CXmlConstantName::NO_ENCRYPTION],
			CXmlConstantValue::TLS_PSK => [CXmlConstantName::TLS_PSK],
			3 => [CXmlConstantName::NO_ENCRYPTION, CXmlConstantName::TLS_PSK],
			CXmlConstantValue::TLS_CERTIFICATE => [CXmlConstantName::TLS_CERTIFICATE],
			5 => [CXmlConstantName::NO_ENCRYPTION, CXmlConstantName::TLS_CERTIFICATE],
			6 => [CXmlConstantName::TLS_PSK, CXmlConstantName::TLS_CERTIFICATE],
			7 => [
				CXmlConstantName::NO_ENCRYPTION,
				CXmlConstantName::TLS_PSK, CXmlConstantName::TLS_CERTIFICATE
			]
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
}
