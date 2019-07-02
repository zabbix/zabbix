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


class CXmlTagHost extends CXmlTagAbstract
{
	protected $tag = 'hosts';

	public function __construct(array $schema = [])
	{
		$schema += [
			'host' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'applications' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagApplication)->getSchema()
			],
			'description' => [
				'type' => CXmlDefine::STRING
			],
			'discovery_rules' => [
				'key' => 'discoveryRules',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagDiscoveryRule)->getSchema()
			],
			'groups' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagGroup)->getSchema()
			],
			'httptests' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagHttptest)->getSchema()
			],
			'interfaces' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagInterface)->getSchema()
			],
			'inventory_mode' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::INV_MODE_MANUAL,
				'range' => [
					CXmlDefine::INV_MODE_DISABLED => 'DISABLED',
					CXmlDefine::INV_MODE_MANUAL => 'MANUAL',
					CXmlDefine::INV_MODE_AUTOMATIC => 'AUTOMATIC'
				]
			],
			'ipmi_authtype' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::DEFAULT,
				'range' => [
					CXmlDefine::DEFAULT => 'DEFAULT',
					CXmlDefine::NONE => 'NONE',
					CXmlDefine::MD2 => 'MD2',
					CXmlDefine::MD5 => 'MD5',
					CXmlDefine::STRAIGHT => 'STRAIGHT',
					CXmlDefine::OEM => 'OEM',
					CXmlDefine::RMCP_PLUS => 'RMCP_PLUS'
				]
			],
			'ipmi_password' => [
				'type' => CXmlDefine::STRING
			],
			'ipmi_privilege' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::USER,
				'range' => [
					CXmlDefine::CALLBACK => 'CALLBACK',
					CXmlDefine::USER => 'USER',
					CXmlDefine::OPERATOR => 'OPERATOR',
					CXmlDefine::ADMIN => 'ADMIN',
					CXmlDefine::OEM => 'OEM'
				]
			],
			'ipmi_username' => [
				'type' => CXmlDefine::STRING
			],
			'items' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagHostItem)->getSchema()
			],
			'macros' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagMacro)->getSchema()
			],
			'name' => [
				'type' => CXmlDefine::STRING
			],
			'proxy' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagProxy)->getSchema()
			],
			'status' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ENABLED,
				'range' => [
					CXmlDefine::ENABLED => 'ENABLED',
					CXmlDefine::DISABLED => 'DISABLED'
				]
			],
			'tags' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagTag)->getSchema()
			],
			'templates' => [
				'key' => 'parentTemplates',
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagLinkedTemplate)->getSchema()
			],
			'tls_accept' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO_ENCRYPTION,
				'range' => [
					CXmlDefine::NO_ENCRYPTION => ['NO_ENCRYPTION'],
					CXmlDefine::TLS_PSK => ['TLS_PSK'],
					3 => ['NO_ENCRYPTION', 'TLS_PSK'],
					CXmlDefine::TLS_CERTIFICATE => ['TLS_CERTIFICATE'],
					5 => ['NO_ENCRYPTION', 'TLS_CERTIFICATE'],
					6 => ['TLS_PSK', 'TLS_CERTIFICATE'],
					7 => ['NO_ENCRYPTION', 'TLS_PSK', 'TLS_CERTIFICATE']
				]
			],
			'tls_connect' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO_ENCRYPTION,
				'range' => [
					CXmlDefine::NO_ENCRYPTION => 'NO_ENCRYPTION',
					CXmlDefine::TLS_PSK => 'TLS_PSK',
					CXmlDefine::TLS_CERTIFICATE => 'TLS_CERTIFICATE'
				]
			],
			'tls_issuer' => [
				'type' => CXmlDefine::STRING,
			],
			'tls_psk' => [
				'type' => CXmlDefine::STRING,
			],
			'tls_psk_identity' => [
				'type' => CXmlDefine::STRING,
			],
			'tls_subject' => [
				'type' => CXmlDefine::STRING,
			]
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data,$simple_triggers = null)
	{
		$references = [
			'num' => 1,
			'refs' => []
		];

		CArrayHelper::sort($data, ['host']);

		foreach ($data as &$host) {
			if (array_key_exists('inventory', $host)) {
				$host['inventory_mode'] = $host['inventory']['inventory_mode'];
				unset($host['inventory']);
			}

			/**
			 * For each host interface an unique reference must be created and then added for all items, discovery rules
			 * and item prototypes that use the interface.
			 */
			foreach ($host['interfaces'] as &$interface) {
				$refNum = $references['num']++;
				$referenceKey = 'if'.$refNum;
				$interface['interface_ref'] = $referenceKey;
				$references['refs'][$interface['interfaceid']] = $referenceKey;
			}
			unset($interface);

			foreach ($host['items'] as &$item) {
				if ($item['interfaceid']) {
					$item['interface_ref'] = $references['refs'][$item['interfaceid']];
				}
			}
			unset($item);

			if ($host['discoveryRules']) {
				$host['discoveryRules'] = (new CXmlTagDiscoveryRule)->prepareData($host['discoveryRules']);
			}

			foreach ($host['discoveryRules'] as &$discoveryRule) {
				if ($discoveryRule['interfaceid']) {
					$discoveryRule['interface_ref'] = $references['refs'][$discoveryRule['interfaceid']];
				}

				foreach ($discoveryRule['itemPrototypes'] as &$prototype) {
					if ($prototype['interfaceid']) {
						$prototype['interface_ref'] = $references['refs'][$prototype['interfaceid']];
					}
				}
				unset($prototype);
			}
			unset($discoveryRule);

			if ($host['items']) {
				$host['items'] = (new CXmlTagHostItem)->prepareData($host['items']);
			}
		}
		unset($host);

		return $data;
	}
}
