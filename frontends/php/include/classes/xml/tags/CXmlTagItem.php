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


class CXmlTagItem extends CXmlTagAbstract
{
	protected $tag = 'items';

	public function __construct(array $schema = [])
	{
		$schema += [
			'key' => [
				'key' => 'key_',
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'name' => [
				'type' => CXmlDefine::STRING | CXmlDefine::REQUIRED
			],
			'allow_traps' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'allowed_hosts' => [
				'key' => 'trapper_hosts',
				'type' => CXmlDefine::STRING
			],
			'applications' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagApplication)->getSchema()
			],
			'authtype' => [
				'type' => CXmlDefine::STRING,
				'value' => 0,
				'range' => function ($row) {
					if ($row['type'] == CXmlDefine::ITEM_TYPE_HTTP_AGENT) {
						return [
							CXmlDefine::PASSWORD => 'PASSWORD',
							CXmlDefine::PUBLIC_KEY => 'PUBLIC_KEY'
						];
					} else {
						return [
							CXmlDefine::NONE => 'NONE',
							CXmlDefine::BASIC => 'BASIC',
							CXmlDefine::NTLM => 'NTLM'
						];
					}
				}
			],
			'delay' => [
				'type' => CXmlDefine::STRING,
				'value' => '1m'
			],
			'description' => [
				'type' => CXmlDefine::STRING
			],
			'follow_redirects' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::YES,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'headers' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagHeader)->getSchema()
			],
			'history' => [
				'type' => CXmlDefine::STRING,
				'value' => '90d'
			],
			'http_proxy' => [
				'type' => CXmlDefine::STRING
			],
			'inventory_link' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NONE,
				'range' => [
					CXmlDefine::NONE => 'NONE',
					CXmlDefine::ALIAS => 'ALIAS',
					CXmlDefine::ASSET_TAG => 'ASSET_TAG',
					CXmlDefine::CHASSIS => 'CHASSIS',
					CXmlDefine::CONTACT => 'CONTACT',
					CXmlDefine::CONTRACT_NUMBER => 'CONTRACT_NUMBER',
					CXmlDefine::DATE_HW_DECOMM => 'DATE_HW_DECOMM',
					CXmlDefine::DATE_HW_EXPIRY => 'DATE_HW_EXPIRY',
					CXmlDefine::DATE_HW_INSTALL => 'DATE_HW_INSTALL',
					CXmlDefine::DATE_HW_PURCHASE => 'DATE_HW_PURCHASE',
					CXmlDefine::DEPLOYMENT_STATUS => 'DEPLOYMENT_STATUS',
					CXmlDefine::HARDWARE => 'HARDWARE',
					CXmlDefine::HARDWARE_FULL => 'HARDWARE_FULL',
					CXmlDefine::HOST_NETMASK => 'HOST_NETMASK',
					CXmlDefine::HOST_NETWORKS => 'HOST_NETWORKS',
					CXmlDefine::HOST_ROUTER => 'HOST_ROUTER',
					CXmlDefine::HW_ARCH => 'HW_ARCH',
					CXmlDefine::INSTALLER_NAME => 'INSTALLER_NAME',
					CXmlDefine::LOCATION => 'LOCATION',
					CXmlDefine::LOCATION_LAT => 'LOCATION_LAT',
					CXmlDefine::LOCATION_LON => 'LOCATION_LON',
					CXmlDefine::MACADDRESS_A => 'MACADDRESS_A',
					CXmlDefine::MACADDRESS_B => 'MACADDRESS_B',
					CXmlDefine::MODEL => 'MODEL',
					CXmlDefine::NAME => 'NAME',
					CXmlDefine::NOTES => 'NOTES',
					CXmlDefine::OOB_IP => 'OOB_IP',
					CXmlDefine::OOB_NETMASK => 'OOB_NETMASK',
					CXmlDefine::OOB_ROUTER => 'OOB_ROUTER',
					CXmlDefine::OS => 'OS',
					CXmlDefine::OS_FULL => 'OS_FULL',
					CXmlDefine::OS_SHORT => 'OS_SHORT',
					CXmlDefine::POC_1_CELL => 'POC_1_CELL',
					CXmlDefine::POC_1_EMAIL => 'POC_1_EMAIL',
					CXmlDefine::POC_1_NAME => 'POC_1_NAME',
					CXmlDefine::POC_1_NOTES => 'POC_1_NOTES',
					CXmlDefine::POC_1_PHONE_A => 'POC_1_PHONE_A',
					CXmlDefine::POC_1_PHONE_B => 'POC_1_PHONE_B',
					CXmlDefine::POC_1_SCREEN => 'POC_1_SCREEN',
					CXmlDefine::POC_2_CELL => 'POC_2_CELL',
					CXmlDefine::POC_2_EMAIL => 'POC_2_EMAIL',
					CXmlDefine::POC_2_NAME => 'POC_2_NAME',
					CXmlDefine::POC_2_NOTES => 'POC_2_NOTES',
					CXmlDefine::POC_2_PHONE_A => 'POC_2_PHONE_A',
					CXmlDefine::POC_2_PHONE_B => 'POC_2_PHONE_B',
					CXmlDefine::POC_2_SCREEN => 'POC_2_SCREEN',
					CXmlDefine::SERIALNO_A => 'SERIALNO_A',
					CXmlDefine::SERIALNO_B => 'SERIALNO_B',
					CXmlDefine::SITE_ADDRESS_A => 'SITE_ADDRESS_A',
					CXmlDefine::SITE_ADDRESS_B => 'SITE_ADDRESS_B',
					CXmlDefine::SITE_ADDRESS_C => 'SITE_ADDRESS_C',
					CXmlDefine::SITE_CITY => 'SITE_CITY',
					CXmlDefine::SITE_COUNTRY => 'SITE_COUNTRY',
					CXmlDefine::SITE_NOTES => 'SITE_NOTES',
					CXmlDefine::SITE_RACK => 'SITE_RACK',
					CXmlDefine::SITE_STATE => 'SITE_STATE',
					CXmlDefine::SITE_ZIP => 'SITE_ZIP',
					CXmlDefine::SOFTWARE => 'SOFTWARE',
					CXmlDefine::SOFTWARE_APP_A => 'SOFTWARE_APP_A',
					CXmlDefine::SOFTWARE_APP_B => 'SOFTWARE_APP_B',
					CXmlDefine::SOFTWARE_APP_C => 'SOFTWARE_APP_C',
					CXmlDefine::SOFTWARE_APP_D => 'SOFTWARE_APP_D',
					CXmlDefine::SOFTWARE_APP_E => 'SOFTWARE_APP_E',
					CXmlDefine::SOFTWARE_FULL => 'SOFTWARE_FULL',
					CXmlDefine::TAG => 'TAG',
					CXmlDefine::TYPE => 'TYPE',
					CXmlDefine::TYPE_FULL => 'TYPE_FULL',
					CXmlDefine::URL_A => 'URL_A',
					CXmlDefine::URL_B => 'URL_B',
					CXmlDefine::URL_C => 'URL_C',
					CXmlDefine::VENDOR => 'VENDOR',
				]
			],
			'ipmi_sensor' => [
				'type' => CXmlDefine::STRING
			],
			'jmx_endpoint' => [
				'type' => CXmlDefine::STRING
			],
			'logtimefmt' => [
				'type' => CXmlDefine::STRING
			],
			'master_item' => [
				'type' => CXmlDefine::INDEXED_ARRAY,
				'schema' => (new CXmlTagMasterItem)->getSchema()
			],
			'output_format' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::RAW,
				'range' => [
					CXmlDefine::RAW => 'RAW',
					CXmlDefine::JSON => 'JSON'
				]
			],
			'params' => [
				'type' => CXmlDefine::STRING
			],
			'password' => [
				'type' => CXmlDefine::STRING
			],
			'port' => [
				'type' => CXmlDefine::STRING
			],
			'post_type' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::RAW,
				'range' => [
					CXmlDefine::RAW => 'RAW',
					CXmlDefine::JSON => 'JSON',
					CXmlDefine::XML => 'XML'
				]
			],
			'posts' => [
				'type' => CXmlDefine::STRING
			],
			'preprocessing' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagPreprocessing)->getSchema()
			],
			'privatekey' => [
				'type' => CXmlDefine::STRING
			],
			'publickey' => [
				'type' => CXmlDefine::STRING
			],
			'query_fields' => [
				'type' => CXmlDefine::ARRAY,
				'schema' => (new CXmlTagQueryField)->getSchema()
			],
			'request_method' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::GET,
				'range' => [
					CXmlDefine::GET => 'GET',
					CXmlDefine::POST => 'POST',
					CXmlDefine::PUT => 'PUT',
					CXmlDefine::HEAD => 'HEAD'
				]
			],
			'retrieve_mode' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::BODY,
				'range' => [
					CXmlDefine::BODY => 'BODY',
					CXmlDefine::HEADERS => 'HEADERS',
					CXmlDefine::BOTH => 'BOTH'
				]
			],
			'snmp_community' => [
				'type' => CXmlDefine::STRING
			],
			'snmp_oid' => [
				'type' => CXmlDefine::STRING
			],
			'snmpv3_authpassphrase' => [
				'type' => CXmlDefine::STRING
			],
			'snmpv3_authprotocol' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::SNMPV3_MD5,
				'range' => [
					CXmlDefine::SNMPV3_MD5 => 'MD5',
					CXmlDefine::SNMPV3_SHA => 'SHA'
				]
			],
			'snmpv3_contextname' => [
				'type' => CXmlDefine::STRING
			],
			'snmpv3_privpassphrase' => [
				'type' => CXmlDefine::STRING
			],
			'snmpv3_privprotocol' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::DES,
				'range' => [
					CXmlDefine::DES => 'DES',
					CXmlDefine::AES => 'AES'
				]
			],
			'snmpv3_securitylevel' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NOAUTHNOPRIV,
				'range' => [
					CXmlDefine::NOAUTHNOPRIV => 'NOAUTHNOPRIV',
					CXmlDefine::AUTHNOPRIV => 'AUTHNOPRIV',
					CXmlDefine::AUTHPRIV => 'AUTHPRIV'
				]
			],
			'snmpv3_securityname' => [
				'type' => CXmlDefine::STRING
			],
			'ssl_cert_file' => [
				'type' => CXmlDefine::STRING
			],
			'ssl_key_file' => [
				'type' => CXmlDefine::STRING
			],
			'ssl_key_password' => [
				'type' => CXmlDefine::STRING
			],
			'status' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ENABLED,
				'range' => [
					CXmlDefine::ENABLED => 'ENABLED',
					CXmlDefine::DISABLED => 'DISABLED'
				]
			],
			'status_codes' => [
				'type' => CXmlDefine::STRING
			],
			'timeout' => [
				'type' => CXmlDefine::STRING
			],
			'trends' => [
				'type' => CXmlDefine::STRING,
				'value' => '365d'
			],
			'type' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE,
				'range' => [
					CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE => 'ZABBIX_PASSIVE',
					CXmlDefine::ITEM_TYPE_SNMPV1 => 'SNMPV1',
					CXmlDefine::ITEM_TYPE_TRAP => 'TRAP',
					CXmlDefine::ITEM_TYPE_SIMPLE => 'SIMPLE',
					CXmlDefine::ITEM_TYPE_SNMPV2 => 'SNMPV2',
					CXmlDefine::ITEM_TYPE_INTERNAL => 'INTERNAL',
					CXmlDefine::ITEM_TYPE_SNMPV3 => 'SNMPV3',
					CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE => 'ZABBIX_ACTIVE',
					CXmlDefine::ITEM_TYPE_AGGREGATE => 'AGGREGATE',
					CXmlDefine::ITEM_TYPE_EXTERNAL => 'EXTERNAL',
					CXmlDefine::ITEM_TYPE_ODBC => 'ODBC',
					CXmlDefine::ITEM_TYPE_IPMI => 'IPMI',
					CXmlDefine::ITEM_TYPE_SSH => 'SSH',
					CXmlDefine::ITEM_TYPE_TELNET => 'TELNET',
					CXmlDefine::ITEM_TYPE_CALCULATED => 'CALCULATED',
					CXmlDefine::ITEM_TYPE_JMX => 'JMX',
					CXmlDefine::ITEM_TYPE_SNMP_TRAP => 'SNMP_TRAP',
					CXmlDefine::ITEM_TYPE_DEPENDENT => 'DEPENDENT',
					CXmlDefine::ITEM_TYPE_HTTP_AGENT => 'HTTP_AGENT'
				]
			],
			'units' => [
				'type' => CXmlDefine::STRING
			],
			'url' => [
				'type' => CXmlDefine::STRING
			],
			'username' => [
				'type' => CXmlDefine::STRING
			],
			'value_type' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::UNSIGNED,
				'range' => [
					CXmlDefine::FLOAT => 'FLOAT',
					CXmlDefine::CHAR => 'CHAR',
					CXmlDefine::LOG => 'LOG',
					CXmlDefine::UNSIGNED => 'UNSIGNED',
					CXmlDefine::TEXT => 'TEXT'
				]
			],
			'valuemap' => [
				'type' => CXmlDefine::STRING
			],
			'verify_host' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			],
			'verify_peer' => [
				'type' => CXmlDefine::STRING,
				'value' => CXmlDefine::NO,
				'range' => [
					CXmlDefine::NO => 'NO',
					CXmlDefine::YES => 'YES'
				]
			]
		];

		$this->schema = $schema;
	}

	public function prepareData(array $data, $simple_triggers = null)
	{
		$expression_data = $simple_triggers ? new CTriggerExpression() : null;

		CArrayHelper::sort($data, ['key_']);

		foreach ($data as &$item) {
			if (array_key_exists('master_item', $item)) {
				$item['master_item'] = ($item['type'] == CXmlDefine::ITEM_TYPE_DEPENDENT) ? ['key' => $item['master_item']['key_']] : [];
			}

			if ($item['applications']) {
				$item['applications'] = (new CXmlTagApplication)->prepareData($item['applications']);
			}

			if ($item['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$item['applicationPrototypes'] = (new CXmlTagApplication)->prepareData($item['applicationPrototypes'], $simple_triggers);
			}

			if ($item['query_fields']) {
				$item['query_fields'] = (new CXmlTagQueryField)->prepareData($item['query_fields']);
			}

			if ($item['headers']) {
				$item['headers'] = (new CXmlTagHeader)->prepareData($item['headers']);
			}
			if ($item['preprocessing']) {
				$item['preprocessing'] = (new CXmlTagPreprocessing)->prepareData($item['preprocessing']);
			}

			if ($simple_triggers) {
				$triggers = [];
				$prefix_length = strlen($item['host'].':'.$item['key'].'.');

				foreach ($simple_triggers as $simple_trigger) {
					if (bccomp($item['itemid'], $simple_trigger['items'][0]['itemid']) == 0) {
						if ($expression_data->parse($simple_trigger['expression'])) {
							foreach (array_reverse($expression_data->expressions) as $expression) {
								if ($expression['host'] === $item['host'] && $expression['item'] === $item['key_']) {
									$simple_trigger['expression'] = substr_replace($simple_trigger['expression'], '',
										$expression['pos'] + 1, $prefix_length
									);
								}
							}
						}

						if ($simple_trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION
								&& $expression_data->parse($simple_trigger['recovery_expression'])) {
							foreach (array_reverse($expression_data->expressions) as $expression) {
								if ($expression['host'] === $item['host'] && $expression['item'] === $item['key_']) {
									$simple_trigger['recovery_expression'] = substr_replace(
										$simple_trigger['recovery_expression'], '', $expression['pos'] + 1,
										$prefix_length
									);
								}
							}
						}

						$triggers[] = $simple_trigger;
					}
				}

				if ($triggers) {
					$key = array_key_exists('discoveryRule', $item) ? 'triggerPrototypes' : 'triggers';
					$item[$key] = (new CXmlTagTrigger)->prepareData($triggers);
				}
			}
		}

		return $data;
	}
}
