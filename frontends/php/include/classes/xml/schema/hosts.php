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


return (new CXmlTagIndexedArray('hosts'))->setSchema(
	(new CXmlTagArray('host'))->setSchema(
		(new CXmlTagString('host'))->setRequired(),
		(new CXmlTagIndexedArray('applications'))->setSchema(
			(new CXmlTagArray('application'))->setSchema(
				(new CXmlTagString('name'))->setRequired()
			)
		),
		new CXmlTagString('description'),
		(new CXmlTagIndexedArray('discovery_rules'))->setKey('discoveryRules')->setSchema(
			(new CXmlTagArray('discovery_rule'))->setSchema(
				(new CXmlTagString('key'))->setRequired()->setKey('key_'),
				(new CXmlTagString('name'))->setRequired(),
				(new CXmlTagString('allow_traps'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagString('allowed_hosts'))->setKey('trapper_hosts'),
				(new CXmlTagString('authtype'))
					->setDefaultValue('0')
					->addConstant('NONE', CXmlDefine::NONE, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant('BASIC', CXmlDefine::BASIC, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant('NTLM', CXmlDefine::NTLM, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant('PASSWORD', CXmlDefine::PASSWORD, CXmlDefine::ITEM_TYPE_SSH)
					->addConstant('PUBLIC_KEY', CXmlDefine::PUBLIC_KEY, CXmlDefine::ITEM_TYPE_SSH)
					->setToXmlCallback(function (array $data, CXmlTag $class) {
						return $class->getConstantByValue($data['authtype'], $data['type']);
					})
					->setFromXmlCallback(function (array $data, CXmlTag $class) {
						if (!array_key_exists('authtype', $data)) {
							return '0';
						}

						$type = ($data['type'] == 'HTTP_AGENT' ? 19 : 13);
						return (string) $class->getConstantValueByName($data['authtype'], $type);
					}),
				(new CXmlTagString('delay'))
					->setDefaultValue('1m'),
				new CXmlTagString('description'),
				(new CXmlTagArray('filter'))->setSchema(
					(new CXmlTagIndexedArray('conditions'))->setSchema(
						(new CXmlTagArray('condition'))->setSchema(
							(new CXmlTagString('formulaid'))->setRequired(),
							(new CXmlTagString('macro'))->setRequired(),
							(new CXmlTagString('operator'))
								->setDefaultValue(CXmlDefine::CONDITION_MATCHES_REGEX)
								->addConstant('MATCHES_REGEX', CXmlDefine::CONDITION_MATCHES_REGEX)
								->addConstant('NOT_MATCHES_REGEX', CXmlDefine::CONDITION_NOT_MATCHES_REGEX),
							new CXmlTagString('value')
						)
					),
					(new CXmlTagString('evaltype'))
						->setDefaultValue(CXmlDefine::AND_OR)
						->addConstant('AND_OR', CXmlDefine::AND_OR)
						->addConstant('AND', CXmlDefine::AND)
						->addConstant('OR', CXmlDefine::OR)
						->addConstant('FORMULA', CXmlDefine::FORMULA),
					(new CXmlTagString('formula'))
				)->setFromXmlCallback(function (array $data, CXmlTag $class) {
					if (!array_key_exists('filter', $data)) {
						return [
							'conditions' => '',
							'evaltype' => '0',
							'formula' => ''
						];
					}

					return $data['filter'];
				}),
				(new CXmlTagString('follow_redirects'))
					->setDefaultValue(CXmlDefine::YES)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagIndexedArray('graph_prototypes'))->setKey('graphPrototypes')->setSchema(
					(new CXmlTagArray('graph_prototype'))->setSchema(
						(new CXmlTagString('name'))->setRequired(),
						(new CXmlTagIndexedArray('graph_items'))->setRequired()->setKey('gitems')->setSchema(
							(new CXmlTagArray('graph_item'))->setSchema(
								(new CXmlTagArray('item'))->setRequired()->setKey('itemid')->setSchema(
									(new CXmlTagString('host'))->setRequired(),
									(new CXmlTagString('key'))->setRequired()
								),
								(new CXmlTagString('calc_fnc'))
									->setDefaultValue(CXmlDefine::AVG)
									->addConstant('MIN', CXmlDefine::MIN)
									->addConstant('AVG', CXmlDefine::AVG)
									->addConstant('MAX', CXmlDefine::MAX)
									->addConstant('ALL', CXmlDefine::ALL)
									->addConstant('LAST', CXmlDefine::LAST),
								new CXmlTagString('color'),
								(new CXmlTagString('drawtype'))
									->setDefaultValue(CXmlDefine::SINGLE_LINE)
									->addConstant('SINGLE_LINE', CXmlDefine::SINGLE_LINE)
									->addConstant('FILLED_REGION', CXmlDefine::FILLED_REGION)
									->addConstant('BOLD_LINE', CXmlDefine::BOLD_LINE)
									->addConstant('DOTTED_LINE', CXmlDefine::DOTTED_LINE)
									->addConstant('DASHED_LINE', CXmlDefine::DASHED_LINE)
									->addConstant('GRADIENT_LINE', CXmlDefine::GRADIENT_LINE),
								(new CXmlTagString('sortorder'))
									->setDefaultValue('0'),
								(new CXmlTagString('type'))
									->setDefaultValue(CXmlDefine::SIMPLE)
									->addConstant('SIMPLE', CXmlDefine::SIMPLE)
									->addConstant('GRAPH_SUM', CXmlDefine::GRAPH_SUM),
								(new CXmlTagString('yaxisside'))
									->setDefaultValue(CXmlDefine::LEFT)
									->addConstant('LEFT', CXmlDefine::LEFT)
									->addConstant('RIGHT', CXmlDefine::RIGHT)
							)
						),
						(new CXmlTagString('height'))
							->setDefaultValue('200'),
						(new CXmlTagString('percent_left'))
							->setDefaultValue('0'),
						(new CXmlTagString('percent_right'))
							->setDefaultValue('0'),
						(new CXmlTagString('show_3d'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('show_legend'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('show_triggers'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('show_work_period'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('type'))->setKey('graphtype')
							->setDefaultValue(CXmlDefine::NORMAL)
							->addConstant('NORMAL', CXmlDefine::NORMAL)
							->addConstant('STACKED', CXmlDefine::STACKED)
							->addConstant('PIE', CXmlDefine::PIE)
							->addConstant('EXPLODED', CXmlDefine::EXPLODED),
						(new CXmlTagString('width'))
							->setDefaultValue('900'),
						(new CXmlTagString('yaxismax'))
							->setDefaultValue('100'),
						(new CXmlTagString('yaxismin'))
							->setDefaultValue('0'),
						(new CXmlTagString('ymax_item_1'))->setKey('ymax_itemid'),
						(new CXmlTagString('ymax_type_1'))->setKey('ymax_type')
							->setDefaultValue(CXmlDefine::CALCULATED)
							->addConstant('CALCULATED', CXmlDefine::CALCULATED)
							->addConstant('FIXED', CXmlDefine::FIXED)
							->addConstant('ITEM', CXmlDefine::ITEM),
						(new CXmlTagString('ymin_item_1'))->setKey('ymin_itemid'),
						(new CXmlTagString('ymin_type_1'))->setKey('ymin_type')
							->setDefaultValue(CXmlDefine::CALCULATED)
							->addConstant('CALCULATED', CXmlDefine::CALCULATED)
							->addConstant('FIXED', CXmlDefine::FIXED)
							->addConstant('ITEM', CXmlDefine::ITEM)
					)
				),
				(new CXmlTagIndexedArray('headers'))->setSchema(
					(new CXmlTagArray('header'))->setSchema(
						(new CXmlTagString('name'))->setRequired(),
						(new CXmlTagString('value'))->setRequired()
					)
				),
				(new CXmlTagIndexedArray('host_prototypes'))->setKey('hostPrototypes')->setSchema(
					(new CXmlTagArray('host_prototype'))->setSchema(
						(new CXmlTagIndexedArray('group_links'))->setKey('groupLinks')->setSchema(
							(new CXmlTagArray('group_link'))->setSchema(
								(new CXmlTagString('group'))->setRequired()->setKey('groupid')
							)
						),
						(new CXmlTagIndexedArray('group_prototypes'))->setKey('groupPrototypes')->setSchema(
							(new CXmlTagArray('group_prototype'))->setSchema(
								(new CXmlTagString('name'))->setRequired()
							)
						),
						(new CXmlTagString('host'))->setRequired(),
						new CXmlTagString('name'),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant('ENABLED', CXmlDefine::ENABLED)
							->addConstant('DISABLED', CXmlDefine::DISABLED),
						(new CXmlTagIndexedArray('templates'))->setSchema(
							(new CXmlTagArray('template'))->setSchema(
								(new CXmlTagString('name'))->setRequired()->setKey('host')
							)
						)
					)
				),
				new CXmlTagString('http_proxy'),
				new CXmlTagString('interface_ref'),
				new CXmlTagString('ipmi_sensor'),
				(new CXmlTagIndexedArray('item_prototypes'))->setKey('itemPrototypes')->setSchema(
					(new CXmlTagArray('item_prototype'))->setSchema(
						(new CXmlTagString('key'))->setRequired()->setKey('key_'),
						(new CXmlTagString('name'))->setRequired(),
						(new CXmlTagString('allow_traps'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('allowed_hosts'))->setKey('trapper_hosts'),
						(new CXmlTagIndexedArray('applications'))->setSchema(
							(new CXmlTagArray('application'))->setSchema(
								(new CXmlTagString('name'))->setRequired()
							)
						),
						(new CXmlTagString('authtype'))
							->setDefaultValue('0')
							->addConstant('NONE', CXmlDefine::NONE, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
							->addConstant('BASIC', CXmlDefine::BASIC, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
							->addConstant('NTLM', CXmlDefine::NTLM, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
							->addConstant('PASSWORD', CXmlDefine::PASSWORD, CXmlDefine::ITEM_TYPE_SSH)
							->addConstant('PUBLIC_KEY', CXmlDefine::PUBLIC_KEY, CXmlDefine::ITEM_TYPE_SSH)
							->setToXmlCallback(function (array $data, CXmlTag $class) {
								return $class->getConstantByValue($data['authtype'], $data['type']);
							})
							->setFromXmlCallback(function (array $data, CXmlTag $class) {
								if (!array_key_exists('authtype', $data)) {
									return '0';
								}

								$type = ($data['type'] == 'HTTP_AGENT' ? 19 : 13);
								return (string) $class->getConstantValueByName($data['authtype'], $type);
							}),
						(new CXmlTagString('delay'))
							->setDefaultValue('1m'),
						new CXmlTagString('description'),
						(new CXmlTagString('follow_redirects'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagIndexedArray('headers'))->setSchema(
							(new CXmlTagArray('header'))->setSchema(
								(new CXmlTagString('name'))->setRequired(),
								(new CXmlTagString('value'))->setRequired()
							)
						),
						(new CXmlTagString('history'))
							->setDefaultValue('90d'),
						new CXmlTagString('http_proxy'),
						new CXmlTagString('interface_ref'),
						(new CXmlTagString('inventory_link'))
							->setDefaultValue(CXmlDefine::NONE)
							->addConstant('NONE', CXmlDefine::NONE)
							->addConstant('ALIAS', CXmlDefine::ALIAS)
							->addConstant('ASSET_TAG', CXmlDefine::ASSET_TAG)
							->addConstant('CHASSIS', CXmlDefine::CHASSIS)
							->addConstant('CONTACT', CXmlDefine::CONTACT)
							->addConstant('CONTRACT_NUMBER', CXmlDefine::CONTRACT_NUMBER)
							->addConstant('DATE_HW_DECOMM', CXmlDefine::DATE_HW_DECOMM)
							->addConstant('DATE_HW_EXPIRY', CXmlDefine::DATE_HW_EXPIRY)
							->addConstant('DATE_HW_INSTALL', CXmlDefine::DATE_HW_INSTALL)
							->addConstant('DATE_HW_PURCHASE', CXmlDefine::DATE_HW_PURCHASE)
							->addConstant('DEPLOYMENT_STATUS', CXmlDefine::DEPLOYMENT_STATUS)
							->addConstant('HARDWARE', CXmlDefine::HARDWARE)
							->addConstant('HARDWARE_FULL', CXmlDefine::HARDWARE_FULL)
							->addConstant('HOST_NETMASK', CXmlDefine::HOST_NETMASK)
							->addConstant('HOST_NETWORKS', CXmlDefine::HOST_NETWORKS)
							->addConstant('HOST_ROUTER', CXmlDefine::HOST_ROUTER)
							->addConstant('HW_ARCH', CXmlDefine::HW_ARCH)
							->addConstant('INSTALLER_NAME', CXmlDefine::INSTALLER_NAME)
							->addConstant('LOCATION', CXmlDefine::LOCATION)
							->addConstant('LOCATION_LAT', CXmlDefine::LOCATION_LAT)
							->addConstant('LOCATION_LON', CXmlDefine::LOCATION_LON)
							->addConstant('MACADDRESS_A', CXmlDefine::MACADDRESS_A)
							->addConstant('MACADDRESS_B', CXmlDefine::MACADDRESS_B)
							->addConstant('MODEL', CXmlDefine::MODEL)
							->addConstant('NAME', CXmlDefine::NAME)
							->addConstant('NOTES', CXmlDefine::NOTES)
							->addConstant('OOB_IP', CXmlDefine::OOB_IP)
							->addConstant('OOB_NETMASK', CXmlDefine::OOB_NETMASK)
							->addConstant('OOB_ROUTER', CXmlDefine::OOB_ROUTER)
							->addConstant('OS', CXmlDefine::OS)
							->addConstant('OS_FULL', CXmlDefine::OS_FULL)
							->addConstant('OS_SHORT', CXmlDefine::OS_SHORT)
							->addConstant('POC_1_CELL', CXmlDefine::POC_1_CELL)
							->addConstant('POC_1_EMAIL', CXmlDefine::POC_1_EMAIL)
							->addConstant('POC_1_NAME', CXmlDefine::POC_1_NAME)
							->addConstant('POC_1_NOTES', CXmlDefine::POC_1_NOTES)
							->addConstant('POC_1_PHONE_A', CXmlDefine::POC_1_PHONE_A)
							->addConstant('POC_1_PHONE_B', CXmlDefine::POC_1_PHONE_B)
							->addConstant('POC_1_SCREEN', CXmlDefine::POC_1_SCREEN)
							->addConstant('POC_2_CELL', CXmlDefine::POC_2_CELL)
							->addConstant('POC_2_EMAIL', CXmlDefine::POC_2_EMAIL)
							->addConstant('POC_2_NAME', CXmlDefine::POC_2_NAME)
							->addConstant('POC_2_NOTES', CXmlDefine::POC_2_NOTES)
							->addConstant('POC_2_PHONE_A', CXmlDefine::POC_2_PHONE_A)
							->addConstant('POC_2_PHONE_B', CXmlDefine::POC_2_PHONE_B)
							->addConstant('POC_2_SCREEN', CXmlDefine::POC_2_SCREEN)
							->addConstant('SERIALNO_A', CXmlDefine::SERIALNO_A)
							->addConstant('SERIALNO_B', CXmlDefine::SERIALNO_B)
							->addConstant('SITE_ADDRESS_A', CXmlDefine::SITE_ADDRESS_A)
							->addConstant('SITE_ADDRESS_B', CXmlDefine::SITE_ADDRESS_B)
							->addConstant('SITE_ADDRESS_C', CXmlDefine::SITE_ADDRESS_C)
							->addConstant('SITE_CITY', CXmlDefine::SITE_CITY)
							->addConstant('SITE_COUNTRY', CXmlDefine::SITE_COUNTRY)
							->addConstant('SITE_NOTES', CXmlDefine::SITE_NOTES)
							->addConstant('SITE_RACK', CXmlDefine::SITE_RACK)
							->addConstant('SITE_STATE', CXmlDefine::SITE_STATE)
							->addConstant('SITE_ZIP', CXmlDefine::SITE_ZIP)
							->addConstant('SOFTWARE', CXmlDefine::SOFTWARE)
							->addConstant('SOFTWARE_APP_A', CXmlDefine::SOFTWARE_APP_A)
							->addConstant('SOFTWARE_APP_B', CXmlDefine::SOFTWARE_APP_B)
							->addConstant('SOFTWARE_APP_C', CXmlDefine::SOFTWARE_APP_C)
							->addConstant('SOFTWARE_APP_D', CXmlDefine::SOFTWARE_APP_D)
							->addConstant('SOFTWARE_APP_E', CXmlDefine::SOFTWARE_APP_E)
							->addConstant('SOFTWARE_FULL', CXmlDefine::SOFTWARE_FULL)
							->addConstant('TAG', CXmlDefine::TAG)
							->addConstant('TYPE', CXmlDefine::TYPE)
							->addConstant('TYPE_FULL', CXmlDefine::TYPE_FULL)
							->addConstant('URL_A', CXmlDefine::URL_A)
							->addConstant('URL_B', CXmlDefine::URL_B)
							->addConstant('URL_C', CXmlDefine::URL_C)
							->addConstant('VENDOR', CXmlDefine::VENDOR),
						new CXmlTagString('ipmi_sensor'),
						new CXmlTagString('jmx_endpoint'),
						new CXmlTagString('logtimefmt'),
						(new CXmlTagArray('master_item'))->setSchema(
							(new CXmlTagString('key'))->setRequired()
						),
						(new CXmlTagString('output_format'))
							->setDefaultValue(CXmlDefine::RAW)
							->addConstant('RAW', CXmlDefine::RAW)
							->addConstant('JSON', CXmlDefine::JSON),
						new CXmlTagString('params'),
						new CXmlTagString('password'),
						new CXmlTagString('port'),
						(new CXmlTagString('post_type'))
							->setDefaultValue(CXmlDefine::RAW)
							->addConstant('RAW', CXmlDefine::RAW)
							->addConstant('JSON', CXmlDefine::JSON)
							->addConstant('XML', CXmlDefine::XML),
						new CXmlTagString('posts'),
						(new CXmlTagIndexedArray('preprocessing'))->setSchema(
							(new CXmlTagArray('step'))->setSchema(
								(new CXmlTagString('params'))->setRequired(),
								(new CXmlTagString('type'))->setRequired()
									->addConstant('MULTIPLIER', CXmlDefine::MULTIPLIER)
									->addConstant('RTRIM', CXmlDefine::RTRIM)
									->addConstant('LTRIM', CXmlDefine::LTRIM)
									->addConstant('TRIM', CXmlDefine::TRIM)
									->addConstant('REGEX', CXmlDefine::REGEX)
									->addConstant('BOOL_TO_DECIMAL', CXmlDefine::BOOL_TO_DECIMAL)
									->addConstant('OCTAL_TO_DECIMAL', CXmlDefine::OCTAL_TO_DECIMAL)
									->addConstant('HEX_TO_DECIMAL', CXmlDefine::HEX_TO_DECIMAL)
									->addConstant('SIMPLE_CHANGE', CXmlDefine::SIMPLE_CHANGE)
									->addConstant('CHANGE_PER_SECOND', CXmlDefine::CHANGE_PER_SECOND)
									->addConstant('XMLPATH', CXmlDefine::XMLPATH)
									->addConstant('JSONPATH', CXmlDefine::JSONPATH)
									->addConstant('IN_RANGE', CXmlDefine::IN_RANGE)
									->addConstant('MATCHES_REGEX', CXmlDefine::MATCHES_REGEX)
									->addConstant('NOT_MATCHES_REGEX', CXmlDefine::NOT_MATCHES_REGEX)
									->addConstant('CHECK_JSON_ERROR', CXmlDefine::CHECK_JSON_ERROR)
									->addConstant('CHECK_XML_ERROR', CXmlDefine::CHECK_XML_ERROR)
									->addConstant('CHECK_REGEX_ERROR', CXmlDefine::CHECK_REGEX_ERROR)
									->addConstant('DISCARD_UNCHANGED', CXmlDefine::DISCARD_UNCHANGED)
									->addConstant('DISCARD_UNCHANGED_HEARTBEAT', CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT)
									->addConstant('JAVASCRIPT', CXmlDefine::JAVASCRIPT)
									->addConstant('PROMETHEUS_PATTERN', CXmlDefine::PROMETHEUS_PATTERN)
									->addConstant('PROMETHEUS_TO_JSON', CXmlDefine::PROMETHEUS_TO_JSON),
								(new CXmlTagString('error_handler'))
									->setDefaultValue(CXmlDefine::ORIGINAL_ERROR)
									->addConstant('ORIGINAL_ERROR', CXmlDefine::ORIGINAL_ERROR)
									->addConstant('DISCARD_VALUE', CXmlDefine::DISCARD_VALUE)
									->addConstant('CUSTOM_VALUE', CXmlDefine::CUSTOM_VALUE)
									->addConstant('CUSTOM_ERROR', CXmlDefine::CUSTOM_ERROR),
								new CXmlTagString('error_handler_params')
							)
						),
						new CXmlTagString('privatekey'),
						new CXmlTagString('publickey'),
						(new CXmlTagIndexedArray('query_fields'))->setSchema(
							(new CXmlTagArray('query_field'))->setSchema(
								(new CXmlTagString('name'))->setRequired(),
								new CXmlTagString('value')
							)
						),
						(new CXmlTagString('request_method'))
							->setDefaultValue(CXmlDefine::GET)
							->addConstant('GET', CXmlDefine::GET)
							->addConstant('POST', CXmlDefine::POST)
							->addConstant('PUT', CXmlDefine::PUT)
							->addConstant('HEAD', CXmlDefine::HEAD),
						(new CXmlTagString('retrieve_mode'))
							->setDefaultValue(CXmlDefine::BODY)
							->addConstant('BODY', CXmlDefine::BODY)
							->addConstant('HEADERS', CXmlDefine::HEADERS)
							->addConstant('BOTH', CXmlDefine::BOTH),
						new CXmlTagString('snmp_community'),
						new CXmlTagString('snmp_oid'),
						new CXmlTagString('snmpv3_authpassphrase'),
						(new CXmlTagString('snmpv3_authprotocol'))
							->setDefaultValue(CXmlDefine::SNMPV3_MD5)
							->addConstant('MD5', CXmlDefine::SNMPV3_MD5)
							->addConstant('SHA', CXmlDefine::SNMPV3_SHA),
						new CXmlTagString('snmpv3_contextname'),
						new CXmlTagString('snmpv3_privpassphrase'),
						(new CXmlTagString('snmpv3_privprotocol'))
							->setDefaultValue(CXmlDefine::DES)
							->addConstant('DES', CXmlDefine::DES)
							->addConstant('AES', CXmlDefine::AES),
						(new CXmlTagString('snmpv3_securitylevel'))
							->setDefaultValue(CXmlDefine::NOAUTHNOPRIV)
							->addConstant('NOAUTHNOPRIV', CXmlDefine::NOAUTHNOPRIV)
							->addConstant('AUTHNOPRIV', CXmlDefine::AUTHNOPRIV)
							->addConstant('AUTHPRIV', CXmlDefine::AUTHPRIV),
						new CXmlTagString('snmpv3_securityname'),
						new CXmlTagString('ssl_cert_file'),
						new CXmlTagString('ssl_key_file'),
						new CXmlTagString('ssl_key_password'),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant('ENABLED', CXmlDefine::ENABLED)
							->addConstant('DISABLED', CXmlDefine::DISABLED),
						new CXmlTagString('status_codes'),
						new CXmlTagString('timeout'),
						(new CXmlTagString('trends'))
							->setDefaultValue('365d'),
						(new CXmlTagString('type'))
							->setDefaultValue(CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
							->addConstant('ZABBIX_PASSIVE', CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
							->addConstant('SNMPV1', CXmlDefine::ITEM_TYPE_SNMPV1)
							->addConstant('TRAP', CXmlDefine::ITEM_TYPE_TRAP)
							->addConstant('SIMPLE', CXmlDefine::ITEM_TYPE_SIMPLE)
							->addConstant('SNMPV2', CXmlDefine::ITEM_TYPE_SNMPV2)
							->addConstant('INTERNAL', CXmlDefine::ITEM_TYPE_INTERNAL)
							->addConstant('SNMPV3', CXmlDefine::ITEM_TYPE_SNMPV3)
							->addConstant('ZABBIX_ACTIVE', CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE)
							->addConstant('AGGREGATE', CXmlDefine::ITEM_TYPE_AGGREGATE)
							->addConstant('EXTERNAL', CXmlDefine::ITEM_TYPE_EXTERNAL)
							->addConstant('ODBC', CXmlDefine::ITEM_TYPE_ODBC)
							->addConstant('IPMI', CXmlDefine::ITEM_TYPE_IPMI)
							->addConstant('SSH', CXmlDefine::ITEM_TYPE_SSH)
							->addConstant('TELNET', CXmlDefine::ITEM_TYPE_TELNET)
							->addConstant('CALCULATED', CXmlDefine::ITEM_TYPE_CALCULATED)
							->addConstant('JMX', CXmlDefine::ITEM_TYPE_JMX)
							->addConstant('SNMP_TRAP', CXmlDefine::ITEM_TYPE_SNMP_TRAP)
							->addConstant('DEPENDENT', CXmlDefine::ITEM_TYPE_DEPENDENT)
							->addConstant('HTTP_AGENT', CXmlDefine::ITEM_TYPE_HTTP_AGENT),
						new CXmlTagString('units'),
						new CXmlTagString('url'),
						new CXmlTagString('username'),
						(new CXmlTagString('value_type'))
							->setDefaultValue(CXmlDefine::UNSIGNED)
							->addConstant('FLOAT', CXmlDefine::FLOAT)
							->addConstant('CHAR', CXmlDefine::CHAR)
							->addConstant('LOG', CXmlDefine::LOG)
							->addConstant('UNSIGNED', CXmlDefine::UNSIGNED)
							->addConstant('TEXT', CXmlDefine::TEXT),
						(new CXmlTagArray('valuemap'))->setSchema(
							new CXmlTagString('name')
						),
						(new CXmlTagString('verify_host'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('verify_peer'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagIndexedArray('application_prototypes'))->setKey('applicationPrototypes')->setSchema(
							(new CXmlTagArray('application_prototype'))->setSchema(
								(new CXmlTagString('name'))->setRequired()
							)
						),
						(new CXmlTagIndexedArray('trigger_prototypes'))->setKey('triggerPrototypes')->setSchema(
							(new CXmlTagArray('trigger_prototype'))->setSchema(
								(new CXmlTagString('expression'))->setRequired(),
								(new CXmlTagString('name'))->setRequired()->setKey('description'),
								(new CXmlTagString('correlation_mode'))
									->setDefaultValue(CXmlDefine::TRIGGER_DISABLED)
									->addConstant('DISABLED', CXmlDefine::TRIGGER_DISABLED)
									->addConstant('TAG_VALUE', CXmlDefine::TRIGGER_TAG_VALUE),
								new CXmlTagString('correlation_tag'),
								(new CXmlTagIndexedArray('dependencies'))->setSchema(
									(new CXmlTagArray('dependency'))->setSchema(
										(new CXmlTagString('expression'))->setRequired(),
										(new CXmlTagString('name'))->setRequired()->setKey('description'),
										new CXmlTagString('recovery_expression')
									)
								),
								(new CXmlTagString('description'))->setKey('comments'),
								(new CXmlTagString('manual_close'))
									->setDefaultValue(CXmlDefine::NO)
									->addConstant('NO', CXmlDefine::NO)
									->addConstant('YES', CXmlDefine::YES),
								(new CXmlTagString('priority'))
									->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
									->addConstant('NOT_CLASSIFIED', CXmlDefine::NOT_CLASSIFIED)
									->addConstant('INFO', CXmlDefine::INFO)
									->addConstant('WARNING', CXmlDefine::WARNING)
									->addConstant('AVERAGE', CXmlDefine::AVERAGE)
									->addConstant('HIGH', CXmlDefine::HIGH)
									->addConstant('DISASTER', CXmlDefine::DISASTER),
								new CXmlTagString('recovery_expression'),
								(new CXmlTagString('recovery_mode'))
									->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
									->addConstant('EXPRESSION', CXmlDefine::TRIGGER_EXPRESSION)
									->addConstant('RECOVERY_EXPRESSION', CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
									->addConstant('NONE', CXmlDefine::TRIGGER_NONE),
								(new CXmlTagString('status'))
									->setDefaultValue(CXmlDefine::ENABLED)
									->addConstant('ENABLED', CXmlDefine::ENABLED)
									->addConstant('DISABLED', CXmlDefine::DISABLED),
								(new CXmlTagIndexedArray('tags'))->setSchema(
									(new CXmlTagArray('tag'))->setSchema(
										(new CXmlTagString('tag'))->setRequired(),
										new CXmlTagString('value')
									)
								),
								(new CXmlTagString('type'))
									->setDefaultValue(CXmlDefine::SINGLE)
									->addConstant('SINGLE', CXmlDefine::SINGLE)
									->addConstant('MULTIPLE', CXmlDefine::MULTIPLE),
								new CXmlTagString('url')
							)
						)
					)
				),
				new CXmlTagString('jmx_endpoint'),
				(new CXmlTagString('lifetime'))
					->setDefaultValue('30d'),
				(new CXmlTagIndexedArray('lld_macro_paths'))->setSchema(
					(new CXmlTagArray('lld_macro_path'))->setSchema(
						new CXmlTagString('lld_macro'),
						new CXmlTagString('path')
					)
				),
				(new CXmlTagArray('master_item'))->setSchema(
					(new CXmlTagString('key'))->setRequired()
				),
				new CXmlTagString('params'),
				new CXmlTagString('password'),
				new CXmlTagString('port'),
				(new CXmlTagString('post_type'))
					->setDefaultValue(CXmlDefine::RAW)
					->addConstant('RAW', CXmlDefine::RAW)
					->addConstant('JSON', CXmlDefine::JSON)
					->addConstant('XML', CXmlDefine::XML),
				new CXmlTagString('posts'),
				(new CXmlTagIndexedArray('preprocessing'))->setSchema(
					(new CXmlTagArray('step'))->setSchema(
						(new CXmlTagString('params'))->setRequired(),
						(new CXmlTagString('type'))->setRequired()
							->addConstant('MULTIPLIER', CXmlDefine::MULTIPLIER)
							->addConstant('RTRIM', CXmlDefine::RTRIM)
							->addConstant('LTRIM', CXmlDefine::LTRIM)
							->addConstant('TRIM', CXmlDefine::TRIM)
							->addConstant('REGEX', CXmlDefine::REGEX)
							->addConstant('BOOL_TO_DECIMAL', CXmlDefine::BOOL_TO_DECIMAL)
							->addConstant('OCTAL_TO_DECIMAL', CXmlDefine::OCTAL_TO_DECIMAL)
							->addConstant('HEX_TO_DECIMAL', CXmlDefine::HEX_TO_DECIMAL)
							->addConstant('SIMPLE_CHANGE', CXmlDefine::SIMPLE_CHANGE)
							->addConstant('CHANGE_PER_SECOND', CXmlDefine::CHANGE_PER_SECOND)
							->addConstant('XMLPATH', CXmlDefine::XMLPATH)
							->addConstant('JSONPATH', CXmlDefine::JSONPATH)
							->addConstant('IN_RANGE', CXmlDefine::IN_RANGE)
							->addConstant('MATCHES_REGEX', CXmlDefine::MATCHES_REGEX)
							->addConstant('NOT_MATCHES_REGEX', CXmlDefine::NOT_MATCHES_REGEX)
							->addConstant('CHECK_JSON_ERROR', CXmlDefine::CHECK_JSON_ERROR)
							->addConstant('CHECK_XML_ERROR', CXmlDefine::CHECK_XML_ERROR)
							->addConstant('CHECK_REGEX_ERROR', CXmlDefine::CHECK_REGEX_ERROR)
							->addConstant('DISCARD_UNCHANGED', CXmlDefine::DISCARD_UNCHANGED)
							->addConstant('DISCARD_UNCHANGED_HEARTBEAT', CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT)
							->addConstant('JAVASCRIPT', CXmlDefine::JAVASCRIPT)
							->addConstant('PROMETHEUS_PATTERN', CXmlDefine::PROMETHEUS_PATTERN)
							->addConstant('PROMETHEUS_TO_JSON', CXmlDefine::PROMETHEUS_TO_JSON),
						(new CXmlTagString('error_handler'))
							->setDefaultValue(CXmlDefine::ORIGINAL_ERROR)
							->addConstant('ORIGINAL_ERROR', CXmlDefine::ORIGINAL_ERROR)
							->addConstant('DISCARD_VALUE', CXmlDefine::DISCARD_VALUE)
							->addConstant('CUSTOM_VALUE', CXmlDefine::CUSTOM_VALUE)
							->addConstant('CUSTOM_ERROR', CXmlDefine::CUSTOM_ERROR),
						new CXmlTagString('error_handler_params')
					)
				),
				new CXmlTagString('privatekey'),
				new CXmlTagString('publickey'),
				(new CXmlTagIndexedArray('query_fields'))->setSchema(
					(new CXmlTagArray('query_field'))->setSchema(
						(new CXmlTagString('name'))->setRequired(),
						new CXmlTagString('value')
					)
				),
				(new CXmlTagString('request_method'))
					->setDefaultValue(CXmlDefine::GET)
					->addConstant('GET', CXmlDefine::GET)
					->addConstant('POST', CXmlDefine::POST)
					->addConstant('PUT', CXmlDefine::PUT)
					->addConstant('HEAD', CXmlDefine::HEAD),
				(new CXmlTagString('retrieve_mode'))
					->setDefaultValue(CXmlDefine::BODY)
					->addConstant('BODY', CXmlDefine::BODY)
					->addConstant('HEADERS', CXmlDefine::HEADERS)
					->addConstant('BOTH', CXmlDefine::BOTH),
				new CXmlTagString('snmp_community'),
				new CXmlTagString('snmp_oid'),
				new CXmlTagString('snmpv3_authpassphrase'),
				(new CXmlTagString('snmpv3_authprotocol'))
					->setDefaultValue(CXmlDefine::SNMPV3_MD5)
					->addConstant('MD5', CXmlDefine::SNMPV3_MD5)
					->addConstant('SHA', CXmlDefine::SNMPV3_SHA),
				new CXmlTagString('snmpv3_contextname'),
				new CXmlTagString('snmpv3_privpassphrase'),
				(new CXmlTagString('snmpv3_privprotocol'))
					->setDefaultValue(CXmlDefine::DES)
					->addConstant('DES', CXmlDefine::DES)
					->addConstant('AES', CXmlDefine::AES),
				(new CXmlTagString('snmpv3_securitylevel'))
					->setDefaultValue(CXmlDefine::NOAUTHNOPRIV)
					->addConstant('NOAUTHNOPRIV', CXmlDefine::NOAUTHNOPRIV)
					->addConstant('AUTHNOPRIV', CXmlDefine::AUTHNOPRIV)
					->addConstant('AUTHPRIV', CXmlDefine::AUTHPRIV),
				new CXmlTagString('snmpv3_securityname'),
				new CXmlTagString('ssl_cert_file'),
				new CXmlTagString('ssl_key_file'),
				new CXmlTagString('ssl_key_password'),
				(new CXmlTagString('status'))
					->setDefaultValue(CXmlDefine::ENABLED)
					->addConstant('ENABLED', CXmlDefine::ENABLED)
					->addConstant('DISABLED', CXmlDefine::DISABLED),
				new CXmlTagString('status_codes'),
				new CXmlTagString('timeout'),
				(new CXmlTagIndexedArray('trigger_prototypes'))->setKey('triggerPrototypes')->setSchema(
					(new CXmlTagArray('trigger_prototype'))->setSchema(
						(new CXmlTagString('expression'))->setRequired(),
						(new CXmlTagString('name'))->setRequired()->setKey('description'),
						(new CXmlTagString('correlation_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_DISABLED)
							->addConstant('DISABLED', CXmlDefine::TRIGGER_DISABLED)
							->addConstant('TAG_VALUE', CXmlDefine::TRIGGER_TAG_VALUE),
						new CXmlTagString('correlation_tag'),
						(new CXmlTagIndexedArray('dependencies'))->setSchema(
							(new CXmlTagArray('dependency'))->setSchema(
								(new CXmlTagString('expression'))->setRequired(),
								(new CXmlTagString('name'))->setRequired()->setKey('description'),
								new CXmlTagString('recovery_expression')
							)
						),
						(new CXmlTagString('description'))->setKey('comments'),
						(new CXmlTagString('manual_close'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('priority'))
							->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
							->addConstant('NOT_CLASSIFIED', CXmlDefine::NOT_CLASSIFIED)
							->addConstant('INFO', CXmlDefine::INFO)
							->addConstant('WARNING', CXmlDefine::WARNING)
							->addConstant('AVERAGE', CXmlDefine::AVERAGE)
							->addConstant('HIGH', CXmlDefine::HIGH)
							->addConstant('DISASTER', CXmlDefine::DISASTER),
						new CXmlTagString('recovery_expression'),
						(new CXmlTagString('recovery_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant('EXPRESSION', CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant('RECOVERY_EXPRESSION', CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
							->addConstant('NONE', CXmlDefine::TRIGGER_NONE),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant('ENABLED', CXmlDefine::ENABLED)
							->addConstant('DISABLED', CXmlDefine::DISABLED),
						(new CXmlTagIndexedArray('tags'))->setSchema(
							(new CXmlTagArray('tag'))->setSchema(
								(new CXmlTagString('tag'))->setRequired(),
								new CXmlTagString('value')
							)
						),
						(new CXmlTagString('type'))
							->setDefaultValue(CXmlDefine::SINGLE)
							->addConstant('SINGLE', CXmlDefine::SINGLE)
							->addConstant('MULTIPLE', CXmlDefine::MULTIPLE),
						new CXmlTagString('url')
					)
				),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant('ZABBIX_PASSIVE', CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant('SNMPV1', CXmlDefine::ITEM_TYPE_SNMPV1)
					->addConstant('TRAP', CXmlDefine::ITEM_TYPE_TRAP)
					->addConstant('SIMPLE', CXmlDefine::ITEM_TYPE_SIMPLE)
					->addConstant('SNMPV2', CXmlDefine::ITEM_TYPE_SNMPV2)
					->addConstant('INTERNAL', CXmlDefine::ITEM_TYPE_INTERNAL)
					->addConstant('SNMPV3', CXmlDefine::ITEM_TYPE_SNMPV3)
					->addConstant('ZABBIX_ACTIVE', CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE)
					->addConstant('AGGREGATE', CXmlDefine::ITEM_TYPE_AGGREGATE)
					->addConstant('EXTERNAL', CXmlDefine::ITEM_TYPE_EXTERNAL)
					->addConstant('ODBC', CXmlDefine::ITEM_TYPE_ODBC)
					->addConstant('IPMI', CXmlDefine::ITEM_TYPE_IPMI)
					->addConstant('SSH', CXmlDefine::ITEM_TYPE_SSH)
					->addConstant('TELNET', CXmlDefine::ITEM_TYPE_TELNET)
					->addConstant('CALCULATED', CXmlDefine::ITEM_TYPE_CALCULATED)
					->addConstant('JMX', CXmlDefine::ITEM_TYPE_JMX)
					->addConstant('SNMP_TRAP', CXmlDefine::ITEM_TYPE_SNMP_TRAP)
					->addConstant('DEPENDENT', CXmlDefine::ITEM_TYPE_DEPENDENT)
					->addConstant('HTTP_AGENT', CXmlDefine::ITEM_TYPE_HTTP_AGENT),
				new CXmlTagString('url'),
				new CXmlTagString('username'),
				(new CXmlTagString('verify_host'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagString('verify_peer'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES)
			)
		),
		(new CXmlTagIndexedArray('groups'))->setSchema(
			(new CXmlTagArray('group'))->setSchema(
				(new CXmlTagString('name'))->setRequired()
			)
		),
		(new CXmlTagIndexedArray('httptests'))->setSchema(
			(new CXmlTagArray('httptest'))->setSchema(
				(new CXmlTagString('name'))->setRequired(),
				(new CXmlTagIndexedArray('steps'))->setRequired()->setSchema(
					(new CXmlTagArray('step'))->setSchema(
						(new CXmlTagString('name'))->setRequired(),
						(new CXmlTagString('url'))->setRequired(),
						(new CXmlTagString('follow_redirects'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						new CXmlTagString('headers'),
						new CXmlTagString('posts'),
						new CXmlTagString('query_fields'),
						new CXmlTagString('required'),
						(new CXmlTagString('retrieve_mode'))
							->setDefaultValue(CXmlDefine::BODY)
							->addConstant('BODY', CXmlDefine::BODY)
							->addConstant('HEADERS', CXmlDefine::HEADERS)
							->addConstant('BOTH', CXmlDefine::BOTH),
						new CXmlTagString('status_codes'),
						(new CXmlTagString('timeout'))
							->setDefaultValue('15s'),
						(new CXmlTagString('variables'))
					)
				),
				(new CXmlTagString('agent'))
					->setDefaultValue('Zabbix'),
				(new CXmlTagArray('application'))->setSchema(
					(new CXmlTagString('name'))->setRequired()
				),
				(new CXmlTagString('attempts'))->setKey('retries')
					->setDefaultValue('1'),
				(new CXmlTagString('authentication'))
					->setDefaultValue(CXmlDefine::NONE)
					->addConstant('NONE', CXmlDefine::NONE)
					->addConstant('BASIC', CXmlDefine::BASIC)
					->addConstant('NTLM', CXmlDefine::NTLM),
				(new CXmlTagString('delay'))
					->setDefaultValue('1m'),
				new CXmlTagString('headers'),
				new CXmlTagString('http_password'),
				new CXmlTagString('http_proxy'),
				new CXmlTagString('http_user'),
				new CXmlTagString('ssl_cert_file'),
				new CXmlTagString('ssl_key_file'),
				new CXmlTagString('ssl_key_password'),
				(new CXmlTagString('status'))
					->setDefaultValue(CXmlDefine::ENABLED)
					->addConstant('ENABLED', CXmlDefine::ENABLED)
					->addConstant('DISABLED', CXmlDefine::DISABLED),
				new CXmlTagString('variables'),
				(new CXmlTagString('verify_host'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagString('verify_peer'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES)
			)
		),
		(new CXmlTagIndexedArray('interfaces'))->setSchema(
			(new CXmlTagArray('interface'))->setSchema(
				(new CXmlTagString('interface_ref'))->setRequired(),
				(new CXmlTagString('bulk'))
					->setDefaultValue(CXmlDefine::YES)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagString('default'))->setKey('main')
					->setDefaultValue(CXmlDefine::YES)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				new CXmlTagString('dns'),
				new CXmlTagString('ip'),
				new CXmlTagString('port'),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::ZABBIX)
					->addConstant('ZABBIX', CXmlDefine::ZABBIX)
					->addConstant('SNMP', CXmlDefine::SNMP)
					->addConstant('IPMI', CXmlDefine::IPMI)
					->addConstant('JMX', CXmlDefine::JMX),
				(new CXmlTagString('useip'))
					->setDefaultValue(CXmlDefine::YES)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES)
			)
		),
		(new CXmlTagString('inventory_mode'))
			->setDefaultValue(CXmlDefine::INV_MODE_MANUAL)
			->addConstant('DISABLED', CXmlDefine::INV_MODE_DISABLED)
			->addConstant('MANUAL', CXmlDefine::INV_MODE_MANUAL)
			->addConstant('AUTOMATIC', CXmlDefine::INV_MODE_AUTOMATIC),
		(new CXmlTagString('ipmi_authtype'))
			->setDefaultValue(CXmlDefine::DEFAULT)
			->addConstant('DEFAULT', CXmlDefine::DEFAULT)
			->addConstant('NONE', CXmlDefine::NONE)
			->addConstant('MD2', CXmlDefine::MD2)
			->addConstant('MD5', CXmlDefine::MD5)
			->addConstant('STRAIGHT', CXmlDefine::STRAIGHT)
			->addConstant('OEM', CXmlDefine::OEM)
			->addConstant('RMCP_PLUS', CXmlDefine::RMCP_PLUS),
		new CXmlTagString('ipmi_password'),
		(new CXmlTagString('ipmi_privilege'))
			->setDefaultValue(CXmlDefine::USER)
			->addConstant('CALLBACK', CXmlDefine::CALLBACK)
			->addConstant('USER', CXmlDefine::USER)
			->addConstant('OPERATOR', CXmlDefine::OPERATOR)
			->addConstant('ADMIN', CXmlDefine::ADMIN)
			->addConstant('OEM', CXmlDefine::OEM),
		new CXmlTagString('ipmi_username'),
		(new CXmlTagIndexedArray('items'))->setSchema(
			(new CXmlTagArray('item'))->setSchema(
				(new CXmlTagString('key'))->setRequired()->setKey('key_'),
				(new CXmlTagString('name'))->setRequired(),
				(new CXmlTagString('allow_traps'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagString('allowed_hosts'))->setKey('trapper_hosts'),
				(new CXmlTagIndexedArray('applications'))->setSchema(
					(new CXmlTagArray('application'))->setSchema(
						(new CXmlTagString('name'))->setRequired()
					)
				),
				(new CXmlTagString('authtype'))
					->setDefaultValue('0')
					->addConstant('NONE', CXmlDefine::NONE, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant('BASIC', CXmlDefine::BASIC, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant('NTLM', CXmlDefine::NTLM, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant('PASSWORD', CXmlDefine::PASSWORD, CXmlDefine::ITEM_TYPE_SSH)
					->addConstant('PUBLIC_KEY', CXmlDefine::PUBLIC_KEY, CXmlDefine::ITEM_TYPE_SSH)
					->setToXmlCallback(function (array $data, CXmlTag $class) {
						return $class->getConstantByValue($data['authtype'], $data['type']);
					})
					->setFromXmlCallback(function (array $data, CXmlTag $class) {
						if (!array_key_exists('authtype', $data)) {
							return '0';
						}

						$type = ($data['type'] == 'HTTP_AGENT' ? 19 : 13);
						return (string) $class->getConstantValueByName($data['authtype'], $type);
					}),
				(new CXmlTagString('delay'))
					->setDefaultValue('1m'),
				new CXmlTagString('description'),
				(new CXmlTagString('follow_redirects'))
					->setDefaultValue(CXmlDefine::YES)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagIndexedArray('headers'))->setSchema(
					(new CXmlTagArray('header'))->setSchema(
						(new CXmlTagString('name'))->setRequired(),
						(new CXmlTagString('value'))->setRequired()
					)
				),
				(new CXmlTagString('history'))
					->setDefaultValue('90d'),
				new CXmlTagString('http_proxy'),
				new CXmlTagString('interface_ref'),
				(new CXmlTagString('inventory_link'))
					->setDefaultValue(CXmlDefine::NONE)
					->addConstant('NONE', CXmlDefine::NONE)
					->addConstant('ALIAS', CXmlDefine::ALIAS)
					->addConstant('ASSET_TAG', CXmlDefine::ASSET_TAG)
					->addConstant('CHASSIS', CXmlDefine::CHASSIS)
					->addConstant('CONTACT', CXmlDefine::CONTACT)
					->addConstant('CONTRACT_NUMBER', CXmlDefine::CONTRACT_NUMBER)
					->addConstant('DATE_HW_DECOMM', CXmlDefine::DATE_HW_DECOMM)
					->addConstant('DATE_HW_EXPIRY', CXmlDefine::DATE_HW_EXPIRY)
					->addConstant('DATE_HW_INSTALL', CXmlDefine::DATE_HW_INSTALL)
					->addConstant('DATE_HW_PURCHASE', CXmlDefine::DATE_HW_PURCHASE)
					->addConstant('DEPLOYMENT_STATUS', CXmlDefine::DEPLOYMENT_STATUS)
					->addConstant('HARDWARE', CXmlDefine::HARDWARE)
					->addConstant('HARDWARE_FULL', CXmlDefine::HARDWARE_FULL)
					->addConstant('HOST_NETMASK', CXmlDefine::HOST_NETMASK)
					->addConstant('HOST_NETWORKS', CXmlDefine::HOST_NETWORKS)
					->addConstant('HOST_ROUTER', CXmlDefine::HOST_ROUTER)
					->addConstant('HW_ARCH', CXmlDefine::HW_ARCH)
					->addConstant('INSTALLER_NAME', CXmlDefine::INSTALLER_NAME)
					->addConstant('LOCATION', CXmlDefine::LOCATION)
					->addConstant('LOCATION_LAT', CXmlDefine::LOCATION_LAT)
					->addConstant('LOCATION_LON', CXmlDefine::LOCATION_LON)
					->addConstant('MACADDRESS_A', CXmlDefine::MACADDRESS_A)
					->addConstant('MACADDRESS_B', CXmlDefine::MACADDRESS_B)
					->addConstant('MODEL', CXmlDefine::MODEL)
					->addConstant('NAME', CXmlDefine::NAME)
					->addConstant('NOTES', CXmlDefine::NOTES)
					->addConstant('OOB_IP', CXmlDefine::OOB_IP)
					->addConstant('OOB_NETMASK', CXmlDefine::OOB_NETMASK)
					->addConstant('OOB_ROUTER', CXmlDefine::OOB_ROUTER)
					->addConstant('OS', CXmlDefine::OS)
					->addConstant('OS_FULL', CXmlDefine::OS_FULL)
					->addConstant('OS_SHORT', CXmlDefine::OS_SHORT)
					->addConstant('POC_1_CELL', CXmlDefine::POC_1_CELL)
					->addConstant('POC_1_EMAIL', CXmlDefine::POC_1_EMAIL)
					->addConstant('POC_1_NAME', CXmlDefine::POC_1_NAME)
					->addConstant('POC_1_NOTES', CXmlDefine::POC_1_NOTES)
					->addConstant('POC_1_PHONE_A', CXmlDefine::POC_1_PHONE_A)
					->addConstant('POC_1_PHONE_B', CXmlDefine::POC_1_PHONE_B)
					->addConstant('POC_1_SCREEN', CXmlDefine::POC_1_SCREEN)
					->addConstant('POC_2_CELL', CXmlDefine::POC_2_CELL)
					->addConstant('POC_2_EMAIL', CXmlDefine::POC_2_EMAIL)
					->addConstant('POC_2_NAME', CXmlDefine::POC_2_NAME)
					->addConstant('POC_2_NOTES', CXmlDefine::POC_2_NOTES)
					->addConstant('POC_2_PHONE_A', CXmlDefine::POC_2_PHONE_A)
					->addConstant('POC_2_PHONE_B', CXmlDefine::POC_2_PHONE_B)
					->addConstant('POC_2_SCREEN', CXmlDefine::POC_2_SCREEN)
					->addConstant('SERIALNO_A', CXmlDefine::SERIALNO_A)
					->addConstant('SERIALNO_B', CXmlDefine::SERIALNO_B)
					->addConstant('SITE_ADDRESS_A', CXmlDefine::SITE_ADDRESS_A)
					->addConstant('SITE_ADDRESS_B', CXmlDefine::SITE_ADDRESS_B)
					->addConstant('SITE_ADDRESS_C', CXmlDefine::SITE_ADDRESS_C)
					->addConstant('SITE_CITY', CXmlDefine::SITE_CITY)
					->addConstant('SITE_COUNTRY', CXmlDefine::SITE_COUNTRY)
					->addConstant('SITE_NOTES', CXmlDefine::SITE_NOTES)
					->addConstant('SITE_RACK', CXmlDefine::SITE_RACK)
					->addConstant('SITE_STATE', CXmlDefine::SITE_STATE)
					->addConstant('SITE_ZIP', CXmlDefine::SITE_ZIP)
					->addConstant('SOFTWARE', CXmlDefine::SOFTWARE)
					->addConstant('SOFTWARE_APP_A', CXmlDefine::SOFTWARE_APP_A)
					->addConstant('SOFTWARE_APP_B', CXmlDefine::SOFTWARE_APP_B)
					->addConstant('SOFTWARE_APP_C', CXmlDefine::SOFTWARE_APP_C)
					->addConstant('SOFTWARE_APP_D', CXmlDefine::SOFTWARE_APP_D)
					->addConstant('SOFTWARE_APP_E', CXmlDefine::SOFTWARE_APP_E)
					->addConstant('SOFTWARE_FULL', CXmlDefine::SOFTWARE_FULL)
					->addConstant('TAG', CXmlDefine::TAG)
					->addConstant('TYPE', CXmlDefine::TYPE)
					->addConstant('TYPE_FULL', CXmlDefine::TYPE_FULL)
					->addConstant('URL_A', CXmlDefine::URL_A)
					->addConstant('URL_B', CXmlDefine::URL_B)
					->addConstant('URL_C', CXmlDefine::URL_C)
					->addConstant('VENDOR', CXmlDefine::VENDOR),
				new CXmlTagString('ipmi_sensor'),
				new CXmlTagString('jmx_endpoint'),
				new CXmlTagString('logtimefmt'),
				(new CXmlTagArray('master_item'))->setSchema(
					(new CXmlTagString('key'))->setRequired()
				),
				(new CXmlTagString('output_format'))
					->setDefaultValue(CXmlDefine::RAW)
					->addConstant('RAW', CXmlDefine::RAW)
					->addConstant('JSON', CXmlDefine::JSON),
				new CXmlTagString('params'),
				new CXmlTagString('password'),
				new CXmlTagString('port'),
				(new CXmlTagString('post_type'))
					->setDefaultValue(CXmlDefine::RAW)
					->addConstant('RAW', CXmlDefine::RAW)
					->addConstant('JSON', CXmlDefine::JSON)
					->addConstant('XML', CXmlDefine::XML),
				new CXmlTagString('posts'),
				(new CXmlTagIndexedArray('preprocessing'))->setSchema(
					(new CXmlTagArray('step'))->setSchema(
						(new CXmlTagString('params'))->setRequired(),
						(new CXmlTagString('type'))->setRequired()
							->addConstant('MULTIPLIER', CXmlDefine::MULTIPLIER)
							->addConstant('RTRIM', CXmlDefine::RTRIM)
							->addConstant('LTRIM', CXmlDefine::LTRIM)
							->addConstant('TRIM', CXmlDefine::TRIM)
							->addConstant('REGEX', CXmlDefine::REGEX)
							->addConstant('BOOL_TO_DECIMAL', CXmlDefine::BOOL_TO_DECIMAL)
							->addConstant('OCTAL_TO_DECIMAL', CXmlDefine::OCTAL_TO_DECIMAL)
							->addConstant('HEX_TO_DECIMAL', CXmlDefine::HEX_TO_DECIMAL)
							->addConstant('SIMPLE_CHANGE', CXmlDefine::SIMPLE_CHANGE)
							->addConstant('CHANGE_PER_SECOND', CXmlDefine::CHANGE_PER_SECOND)
							->addConstant('XMLPATH', CXmlDefine::XMLPATH)
							->addConstant('JSONPATH', CXmlDefine::JSONPATH)
							->addConstant('IN_RANGE', CXmlDefine::IN_RANGE)
							->addConstant('MATCHES_REGEX', CXmlDefine::MATCHES_REGEX)
							->addConstant('NOT_MATCHES_REGEX', CXmlDefine::NOT_MATCHES_REGEX)
							->addConstant('CHECK_JSON_ERROR', CXmlDefine::CHECK_JSON_ERROR)
							->addConstant('CHECK_XML_ERROR', CXmlDefine::CHECK_XML_ERROR)
							->addConstant('CHECK_REGEX_ERROR', CXmlDefine::CHECK_REGEX_ERROR)
							->addConstant('DISCARD_UNCHANGED', CXmlDefine::DISCARD_UNCHANGED)
							->addConstant('DISCARD_UNCHANGED_HEARTBEAT', CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT)
							->addConstant('JAVASCRIPT', CXmlDefine::JAVASCRIPT)
							->addConstant('PROMETHEUS_PATTERN', CXmlDefine::PROMETHEUS_PATTERN)
							->addConstant('PROMETHEUS_TO_JSON', CXmlDefine::PROMETHEUS_TO_JSON),
						(new CXmlTagString('error_handler'))
							->setDefaultValue(CXmlDefine::ORIGINAL_ERROR)
							->addConstant('ORIGINAL_ERROR', CXmlDefine::ORIGINAL_ERROR)
							->addConstant('DISCARD_VALUE', CXmlDefine::DISCARD_VALUE)
							->addConstant('CUSTOM_VALUE', CXmlDefine::CUSTOM_VALUE)
							->addConstant('CUSTOM_ERROR', CXmlDefine::CUSTOM_ERROR),
						new CXmlTagString('error_handler_params')
					)
				),
				new CXmlTagString('privatekey'),
				new CXmlTagString('publickey'),
				(new CXmlTagIndexedArray('query_fields'))->setSchema(
					(new CXmlTagArray('query_field'))->setSchema(
						(new CXmlTagString('name'))->setRequired(),
						new CXmlTagString('value')
					)
				),
				(new CXmlTagString('request_method'))
					->setDefaultValue(CXmlDefine::GET)
					->addConstant('GET', CXmlDefine::GET)
					->addConstant('POST', CXmlDefine::POST)
					->addConstant('PUT', CXmlDefine::PUT)
					->addConstant('HEAD', CXmlDefine::HEAD),
				(new CXmlTagString('retrieve_mode'))
					->setDefaultValue(CXmlDefine::BODY)
					->addConstant('BODY', CXmlDefine::BODY)
					->addConstant('HEADERS', CXmlDefine::HEADERS)
					->addConstant('BOTH', CXmlDefine::BOTH),
				new CXmlTagString('snmp_community'),
				new CXmlTagString('snmp_oid'),
				new CXmlTagString('snmpv3_authpassphrase'),
				(new CXmlTagString('snmpv3_authprotocol'))
					->setDefaultValue(CXmlDefine::SNMPV3_MD5)
					->addConstant('MD5', CXmlDefine::SNMPV3_MD5)
					->addConstant('SHA', CXmlDefine::SNMPV3_SHA),
				new CXmlTagString('snmpv3_contextname'),
				new CXmlTagString('snmpv3_privpassphrase'),
				(new CXmlTagString('snmpv3_privprotocol'))
					->setDefaultValue(CXmlDefine::DES)
					->addConstant('DES', CXmlDefine::DES)
					->addConstant('AES', CXmlDefine::AES),
				(new CXmlTagString('snmpv3_securitylevel'))
					->setDefaultValue(CXmlDefine::NOAUTHNOPRIV)
					->addConstant('NOAUTHNOPRIV', CXmlDefine::NOAUTHNOPRIV)
					->addConstant('AUTHNOPRIV', CXmlDefine::AUTHNOPRIV)
					->addConstant('AUTHPRIV', CXmlDefine::AUTHPRIV),
				new CXmlTagString('snmpv3_securityname'),
				new CXmlTagString('ssl_cert_file'),
				new CXmlTagString('ssl_key_file'),
				new CXmlTagString('ssl_key_password'),
				(new CXmlTagString('status'))
					->setDefaultValue(CXmlDefine::ENABLED)
					->addConstant('ENABLED', CXmlDefine::ENABLED)
					->addConstant('DISABLED', CXmlDefine::DISABLED),
				new CXmlTagString('status_codes'),
				new CXmlTagString('timeout'),
				(new CXmlTagString('trends'))
					->setDefaultValue('365d'),
				(new CXmlTagIndexedArray('triggers'))->setSchema(
					(new CXmlTagArray('trigger'))->setSchema(
						(new CXmlTagString('expression'))->setRequired(),
						(new CXmlTagString('name'))->setRequired()->setKey('description'),
						(new CXmlTagString('correlation_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_DISABLED)
							->addConstant('DISABLED', CXmlDefine::TRIGGER_DISABLED)
							->addConstant('TAG_VALUE', CXmlDefine::TRIGGER_TAG_VALUE),
						new CXmlTagString('correlation_tag'),
						(new CXmlTagIndexedArray('dependencies'))->setSchema(
							(new CXmlTagArray('dependency'))->setSchema(
								(new CXmlTagString('expression'))->setRequired(),
								(new CXmlTagString('name'))->setRequired()->setKey('description'),
								new CXmlTagString('recovery_expression')
							)
						),
						(new CXmlTagString('description'))->setKey('comments'),
						(new CXmlTagString('manual_close'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant('NO', CXmlDefine::NO)
							->addConstant('YES', CXmlDefine::YES),
						(new CXmlTagString('priority'))
							->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
							->addConstant('NOT_CLASSIFIED', CXmlDefine::NOT_CLASSIFIED)
							->addConstant('INFO', CXmlDefine::INFO)
							->addConstant('WARNING', CXmlDefine::WARNING)
							->addConstant('AVERAGE', CXmlDefine::AVERAGE)
							->addConstant('HIGH', CXmlDefine::HIGH)
							->addConstant('DISASTER', CXmlDefine::DISASTER),
						new CXmlTagString('recovery_expression'),
						(new CXmlTagString('recovery_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant('EXPRESSION', CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant('RECOVERY_EXPRESSION', CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
							->addConstant('NONE', CXmlDefine::TRIGGER_NONE),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant('ENABLED', CXmlDefine::ENABLED)
							->addConstant('DISABLED', CXmlDefine::DISABLED),
						(new CXmlTagIndexedArray('tags'))->setSchema(
							(new CXmlTagArray('tag'))->setSchema(
								(new CXmlTagString('tag'))->setRequired(),
								new CXmlTagString('value')
							)
						),
						(new CXmlTagString('type'))
							->setDefaultValue(CXmlDefine::SINGLE)
							->addConstant('SINGLE', CXmlDefine::SINGLE)
							->addConstant('MULTIPLE', CXmlDefine::MULTIPLE),
						new CXmlTagString('url')
					)
				),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant('ZABBIX_PASSIVE', CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant('SNMPV1', CXmlDefine::ITEM_TYPE_SNMPV1)
					->addConstant('TRAP', CXmlDefine::ITEM_TYPE_TRAP)
					->addConstant('SIMPLE', CXmlDefine::ITEM_TYPE_SIMPLE)
					->addConstant('SNMPV2', CXmlDefine::ITEM_TYPE_SNMPV2)
					->addConstant('INTERNAL', CXmlDefine::ITEM_TYPE_INTERNAL)
					->addConstant('SNMPV3', CXmlDefine::ITEM_TYPE_SNMPV3)
					->addConstant('ZABBIX_ACTIVE', CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE)
					->addConstant('AGGREGATE', CXmlDefine::ITEM_TYPE_AGGREGATE)
					->addConstant('EXTERNAL', CXmlDefine::ITEM_TYPE_EXTERNAL)
					->addConstant('ODBC', CXmlDefine::ITEM_TYPE_ODBC)
					->addConstant('IPMI', CXmlDefine::ITEM_TYPE_IPMI)
					->addConstant('SSH', CXmlDefine::ITEM_TYPE_SSH)
					->addConstant('TELNET', CXmlDefine::ITEM_TYPE_TELNET)
					->addConstant('CALCULATED', CXmlDefine::ITEM_TYPE_CALCULATED)
					->addConstant('JMX', CXmlDefine::ITEM_TYPE_JMX)
					->addConstant('SNMP_TRAP', CXmlDefine::ITEM_TYPE_SNMP_TRAP)
					->addConstant('DEPENDENT', CXmlDefine::ITEM_TYPE_DEPENDENT)
					->addConstant('HTTP_AGENT', CXmlDefine::ITEM_TYPE_HTTP_AGENT),
				new CXmlTagString('units'),
				new CXmlTagString('url'),
				new CXmlTagString('username'),
				(new CXmlTagString('value_type'))
					->setDefaultValue(CXmlDefine::UNSIGNED)
					->addConstant('FLOAT', CXmlDefine::FLOAT)
					->addConstant('CHAR', CXmlDefine::CHAR)
					->addConstant('LOG', CXmlDefine::LOG)
					->addConstant('UNSIGNED', CXmlDefine::UNSIGNED)
					->addConstant('TEXT', CXmlDefine::TEXT),
				(new CXmlTagArray('valuemap'))->setSchema(
					(new CXmlTagString('name'))
				),
				(new CXmlTagString('verify_host'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES),
				(new CXmlTagString('verify_peer'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant('NO', CXmlDefine::NO)
					->addConstant('YES', CXmlDefine::YES)
			)
		),
		(new CXmlTagIndexedArray('macros'))->setSchema(
			(new CXmlTagArray('macro'))->setSchema(
				(new CXmlTagString('macro'))->setRequired(),
				new CXmlTagString('value')
			)
		),
		new CXmlTagString('name'),
		(new CXmlTagArray('proxy'))->setSchema(
			(new CXmlTagString('name'))->setRequired()
		),
		(new CXmlTagString('status'))
			->setDefaultValue(CXmlDefine::ENABLED)
			->addConstant('ENABLED', CXmlDefine::ENABLED)
			->addConstant('DISABLED', CXmlDefine::DISABLED),
		(new CXmlTagIndexedArray('tags'))->setSchema(
			(new CXmlTagArray('tag'))->setSchema(
				(new CXmlTagString('tag'))->setRequired(),
				new CXmlTagString('value')
			)
		),
		(new CXmlTagIndexedArray('templates'))->setKey('parentTemplates')->setSchema(
			(new CXmlTagArray('template'))->setSchema(
				(new CXmlTagString('name'))->setRequired()->setKey('host')
			)
		),
		(new CXmlTagString('tls_accept'))
			->setDefaultValue(CXmlDefine::NO_ENCRYPTION)
			->addConstant('NO_ENCRYPTION', CXmlDefine::NO_ENCRYPTION)
			->addConstant('TLS_PSK', CXmlDefine::TLS_PSK)
			->addConstant(['NO_ENCRYPTION', 'TLS_PSK'], 3)
			->addConstant('TLS_CERTIFICATE', CXmlDefine::TLS_CERTIFICATE)
			->addConstant(['NO_ENCRYPTION', 'TLS_CERTIFICATE'], 5)
			->addConstant(['TLS_PSK', 'TLS_CERTIFICATE'], 6)
			->addConstant(['NO_ENCRYPTION', 'TLS_PSK', 'TLS_CERTIFICATE'], 7)
			->setToXmlCallback(function (array $data, CXmlTag $class) {
				$const = $class->getConstantByValue($data['tls_accept']);
				return is_array($const) ? $const : [$const];
			})
			->setFromXmlCallback(function (array $data, CXmlTag $class) {
				if (!array_key_exists('tls_accept', $data)) {
					return (string)$class->getDefaultValue();
				}

				$result = 0;
				foreach ($data['tls_accept'] as $const) {
					$result += $class->getConstantValueByName($const);
				}
				return (string)$result;
			}),
		(new CXmlTagString('tls_connect'))
			->setDefaultValue(CXmlDefine::NO_ENCRYPTION)
			->addConstant('NO_ENCRYPTION', CXmlDefine::NO_ENCRYPTION)
			->addConstant('TLS_PSK', CXmlDefine::TLS_PSK)
			->addConstant('TLS_CERTIFICATE', CXmlDefine::TLS_CERTIFICATE),
		new CXmlTagString('tls_issuer'),
		new CXmlTagString('tls_psk'),
		new CXmlTagString('tls_psk_identity'),
		new CXmlTagString('tls_subject')
	)
);
