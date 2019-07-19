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


return (new CXmlTagIndexedArray('templates'))->setSchema(
	(new CXmlTagArray('template'))->setSchema(
		(new CXmlTagString('template'))->setRequired()->setKey('host'),
		new CXmlTagString('description'),
		new CXmlTagString('name'),
		(new CXmlTagIndexedArray('applications'))->setSchema(
			(new CXmlTagArray('application'))->setSchema(
				(new CXmlTagString('name'))->setRequired()
			)
		),
		(new CXmlTagIndexedArray('discovery_rules'))->setKey('discoveryRules')->setSchema(
			(new CXmlTagArray('discovery_rule'))->setSchema(
				(new CXmlTagString('key'))->setRequired()->setKey('key_'),
				(new CXmlTagString('name'))->setRequired(),
				(new CXmlTagString('allow_traps'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
				(new CXmlTagString('allowed_hosts'))->setKey('trapper_hosts'),
				(new CXmlTagString('authtype'))
					->setDefaultValue('0')
					->addConstant(CXmlConstant::NONE, CXmlDefine::NONE, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant(CXmlConstant::BASIC, CXmlDefine::BASIC, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant(CXmlConstant::NTLM, CXmlDefine::NTLM, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant(CXmlConstant::PASSWORD, CXmlDefine::PASSWORD, CXmlDefine::ITEM_TYPE_SSH)
					->addConstant(CXmlConstant::PUBLIC_KEY, CXmlDefine::PUBLIC_KEY, CXmlDefine::ITEM_TYPE_SSH)
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
								->addConstant(CXmlConstant::MATCHES_REGEX, CXmlDefine::CONDITION_MATCHES_REGEX)
								->addConstant(CXmlConstant::NOT_MATCHES_REGEX, CXmlDefine::CONDITION_NOT_MATCHES_REGEX),
							new CXmlTagString('value')
						)
					),
					(new CXmlTagString('evaltype'))
						->setDefaultValue(CXmlDefine::AND_OR)
						->addConstant(CXmlConstant::AND_OR, CXmlDefine::AND_OR)
						->addConstant(CXmlConstant::XML_AND, CXmlDefine::XML_AND)
						->addConstant(CXmlConstant::XML_OR, CXmlDefine::XML_OR)
						->addConstant(CXmlConstant::FORMULA, CXmlDefine::FORMULA),
					new CXmlTagString('formula')
				)->setFromXmlCallback(function (array $data, CXmlTag $class) {
					if (!array_key_exists('filter', $data)) {
						return [];
					}

					return $data['filter'];
				}),
				(new CXmlTagString('follow_redirects'))
					->setDefaultValue(CXmlDefine::YES)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
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
									->addConstant(CXmlConstant::MIN, CXmlDefine::MIN)
									->addConstant(CXmlConstant::AVG, CXmlDefine::AVG)
									->addConstant(CXmlConstant::MAX, CXmlDefine::MAX)
									->addConstant(CXmlConstant::ALL, CXmlDefine::ALL)
									->addConstant(CXmlConstant::LAST, CXmlDefine::LAST),
								new CXmlTagString('color'),
								(new CXmlTagString('drawtype'))
									->setDefaultValue(CXmlDefine::SINGLE_LINE)
									->addConstant(CXmlConstant::SINGLE_LINE, CXmlDefine::SINGLE_LINE)
									->addConstant(CXmlConstant::FILLED_REGION, CXmlDefine::FILLED_REGION)
									->addConstant(CXmlConstant::BOLD_LINE, CXmlDefine::BOLD_LINE)
									->addConstant(CXmlConstant::DOTTED_LINE, CXmlDefine::DOTTED_LINE)
									->addConstant(CXmlConstant::DASHED_LINE, CXmlDefine::DASHED_LINE)
									->addConstant(CXmlConstant::GRADIENT_LINE, CXmlDefine::GRADIENT_LINE),
								(new CXmlTagString('sortorder'))
									->setDefaultValue('0'),
								(new CXmlTagString('type'))
									->setDefaultValue(CXmlDefine::SIMPLE)
									->addConstant(CXmlConstant::SIMPLE, CXmlDefine::SIMPLE)
									->addConstant(CXmlConstant::GRAPH_SUM, CXmlDefine::GRAPH_SUM),
								(new CXmlTagString('yaxisside'))
									->setDefaultValue(CXmlDefine::LEFT)
									->addConstant(CXmlConstant::LEFT, CXmlDefine::LEFT)
									->addConstant(CXmlConstant::RIGHT, CXmlDefine::RIGHT)
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
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('show_legend'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('show_triggers'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('show_work_period'))
							->setDefaultValue(CXmlDefine::YES)
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('type'))->setKey('graphtype')
							->setDefaultValue(CXmlDefine::NORMAL)
							->addConstant(CXmlConstant::NORMAL, CXmlDefine::NORMAL)
							->addConstant(CXmlConstant::STACKED, CXmlDefine::STACKED)
							->addConstant(CXmlConstant::PIE, CXmlDefine::PIE)
							->addConstant(CXmlConstant::EXPLODED, CXmlDefine::EXPLODED),
						(new CXmlTagString('width'))
							->setDefaultValue('900'),
						(new CXmlTagString('yaxismax'))
							->setDefaultValue('100'),
						(new CXmlTagString('yaxismin'))
							->setDefaultValue('0'),
						(new CXmlTagString('ymax_item_1'))->setKey('ymax_itemid'),
						(new CXmlTagString('ymax_type_1'))->setKey('ymax_type')
							->setDefaultValue(CXmlDefine::CALCULATED)
							->addConstant(CXmlConstant::CALCULATED, CXmlDefine::CALCULATED)
							->addConstant(CXmlConstant::FIXED, CXmlDefine::FIXED)
							->addConstant(CXmlConstant::ITEM, CXmlDefine::ITEM),
						(new CXmlTagString('ymin_item_1'))->setKey('ymin_itemid'),
						(new CXmlTagString('ymin_type_1'))->setKey('ymin_type')
							->setDefaultValue(CXmlDefine::CALCULATED)
							->addConstant(CXmlConstant::CALCULATED, CXmlDefine::CALCULATED)
							->addConstant(CXmlConstant::FIXED, CXmlDefine::FIXED)
							->addConstant(CXmlConstant::ITEM, CXmlDefine::ITEM)
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
							->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
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
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('allowed_hosts'))->setKey('trapper_hosts'),
						(new CXmlTagIndexedArray('applications'))->setSchema(
							(new CXmlTagArray('application'))->setSchema(
								(new CXmlTagString('name'))->setRequired()
							)
						),
						(new CXmlTagString('authtype'))
							->setDefaultValue('0')
							->addConstant(CXmlConstant::NONE, CXmlDefine::NONE, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
							->addConstant(CXmlConstant::BASIC, CXmlDefine::BASIC, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
							->addConstant(CXmlConstant::NTLM, CXmlDefine::NTLM, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
							->addConstant(CXmlConstant::PASSWORD, CXmlDefine::PASSWORD, CXmlDefine::ITEM_TYPE_SSH)
							->addConstant(CXmlConstant::PUBLIC_KEY, CXmlDefine::PUBLIC_KEY, CXmlDefine::ITEM_TYPE_SSH)
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
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
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
							->addConstant(CXmlConstant::NONE, CXmlDefine::NONE)
							->addConstant(CXmlConstant::ALIAS, CXmlDefine::ALIAS)
							->addConstant(CXmlConstant::ASSET_TAG, CXmlDefine::ASSET_TAG)
							->addConstant(CXmlConstant::CHASSIS, CXmlDefine::CHASSIS)
							->addConstant(CXmlConstant::CONTACT, CXmlDefine::CONTACT)
							->addConstant(CXmlConstant::CONTRACT_NUMBER, CXmlDefine::CONTRACT_NUMBER)
							->addConstant(CXmlConstant::DATE_HW_DECOMM, CXmlDefine::DATE_HW_DECOMM)
							->addConstant(CXmlConstant::DATE_HW_EXPIRY, CXmlDefine::DATE_HW_EXPIRY)
							->addConstant(CXmlConstant::DATE_HW_INSTALL, CXmlDefine::DATE_HW_INSTALL)
							->addConstant(CXmlConstant::DATE_HW_PURCHASE, CXmlDefine::DATE_HW_PURCHASE)
							->addConstant(CXmlConstant::DEPLOYMENT_STATUS, CXmlDefine::DEPLOYMENT_STATUS)
							->addConstant(CXmlConstant::HARDWARE, CXmlDefine::HARDWARE)
							->addConstant(CXmlConstant::HARDWARE_FULL, CXmlDefine::HARDWARE_FULL)
							->addConstant(CXmlConstant::HOST_NETMASK, CXmlDefine::HOST_NETMASK)
							->addConstant(CXmlConstant::HOST_NETWORKS, CXmlDefine::HOST_NETWORKS)
							->addConstant(CXmlConstant::HOST_ROUTER, CXmlDefine::HOST_ROUTER)
							->addConstant(CXmlConstant::HW_ARCH, CXmlDefine::HW_ARCH)
							->addConstant(CXmlConstant::INSTALLER_NAME, CXmlDefine::INSTALLER_NAME)
							->addConstant(CXmlConstant::LOCATION, CXmlDefine::LOCATION)
							->addConstant(CXmlConstant::LOCATION_LAT, CXmlDefine::LOCATION_LAT)
							->addConstant(CXmlConstant::LOCATION_LON, CXmlDefine::LOCATION_LON)
							->addConstant(CXmlConstant::MACADDRESS_A, CXmlDefine::MACADDRESS_A)
							->addConstant(CXmlConstant::MACADDRESS_B, CXmlDefine::MACADDRESS_B)
							->addConstant(CXmlConstant::MODEL, CXmlDefine::MODEL)
							->addConstant(CXmlConstant::NAME, CXmlDefine::NAME)
							->addConstant(CXmlConstant::NOTES, CXmlDefine::NOTES)
							->addConstant(CXmlConstant::OOB_IP, CXmlDefine::OOB_IP)
							->addConstant(CXmlConstant::OOB_NETMASK, CXmlDefine::OOB_NETMASK)
							->addConstant(CXmlConstant::OOB_ROUTER, CXmlDefine::OOB_ROUTER)
							->addConstant(CXmlConstant::OS, CXmlDefine::OS)
							->addConstant(CXmlConstant::OS_FULL, CXmlDefine::OS_FULL)
							->addConstant(CXmlConstant::OS_SHORT, CXmlDefine::OS_SHORT)
							->addConstant(CXmlConstant::POC_1_CELL, CXmlDefine::POC_1_CELL)
							->addConstant(CXmlConstant::POC_1_EMAIL, CXmlDefine::POC_1_EMAIL)
							->addConstant(CXmlConstant::POC_1_NAME, CXmlDefine::POC_1_NAME)
							->addConstant(CXmlConstant::POC_1_NOTES, CXmlDefine::POC_1_NOTES)
							->addConstant(CXmlConstant::POC_1_PHONE_A, CXmlDefine::POC_1_PHONE_A)
							->addConstant(CXmlConstant::POC_1_PHONE_B, CXmlDefine::POC_1_PHONE_B)
							->addConstant(CXmlConstant::POC_1_SCREEN, CXmlDefine::POC_1_SCREEN)
							->addConstant(CXmlConstant::POC_2_CELL, CXmlDefine::POC_2_CELL)
							->addConstant(CXmlConstant::POC_2_EMAIL, CXmlDefine::POC_2_EMAIL)
							->addConstant(CXmlConstant::POC_2_NAME, CXmlDefine::POC_2_NAME)
							->addConstant(CXmlConstant::POC_2_NOTES, CXmlDefine::POC_2_NOTES)
							->addConstant(CXmlConstant::POC_2_PHONE_A, CXmlDefine::POC_2_PHONE_A)
							->addConstant(CXmlConstant::POC_2_PHONE_B, CXmlDefine::POC_2_PHONE_B)
							->addConstant(CXmlConstant::POC_2_SCREEN, CXmlDefine::POC_2_SCREEN)
							->addConstant(CXmlConstant::SERIALNO_A, CXmlDefine::SERIALNO_A)
							->addConstant(CXmlConstant::SERIALNO_B, CXmlDefine::SERIALNO_B)
							->addConstant(CXmlConstant::SITE_ADDRESS_A, CXmlDefine::SITE_ADDRESS_A)
							->addConstant(CXmlConstant::SITE_ADDRESS_B, CXmlDefine::SITE_ADDRESS_B)
							->addConstant(CXmlConstant::SITE_ADDRESS_C, CXmlDefine::SITE_ADDRESS_C)
							->addConstant(CXmlConstant::SITE_CITY, CXmlDefine::SITE_CITY)
							->addConstant(CXmlConstant::SITE_COUNTRY, CXmlDefine::SITE_COUNTRY)
							->addConstant(CXmlConstant::SITE_NOTES, CXmlDefine::SITE_NOTES)
							->addConstant(CXmlConstant::SITE_RACK, CXmlDefine::SITE_RACK)
							->addConstant(CXmlConstant::SITE_STATE, CXmlDefine::SITE_STATE)
							->addConstant(CXmlConstant::SITE_ZIP, CXmlDefine::SITE_ZIP)
							->addConstant(CXmlConstant::SOFTWARE, CXmlDefine::SOFTWARE)
							->addConstant(CXmlConstant::SOFTWARE_APP_A, CXmlDefine::SOFTWARE_APP_A)
							->addConstant(CXmlConstant::SOFTWARE_APP_B, CXmlDefine::SOFTWARE_APP_B)
							->addConstant(CXmlConstant::SOFTWARE_APP_C, CXmlDefine::SOFTWARE_APP_C)
							->addConstant(CXmlConstant::SOFTWARE_APP_D, CXmlDefine::SOFTWARE_APP_D)
							->addConstant(CXmlConstant::SOFTWARE_APP_E, CXmlDefine::SOFTWARE_APP_E)
							->addConstant(CXmlConstant::SOFTWARE_FULL, CXmlDefine::SOFTWARE_FULL)
							->addConstant(CXmlConstant::TAG, CXmlDefine::TAG)
							->addConstant(CXmlConstant::TYPE, CXmlDefine::TYPE)
							->addConstant(CXmlConstant::TYPE_FULL, CXmlDefine::TYPE_FULL)
							->addConstant(CXmlConstant::URL_A, CXmlDefine::URL_A)
							->addConstant(CXmlConstant::URL_B, CXmlDefine::URL_B)
							->addConstant(CXmlConstant::URL_C, CXmlDefine::URL_C)
							->addConstant(CXmlConstant::VENDOR, CXmlDefine::VENDOR),
						new CXmlTagString('ipmi_sensor'),
						new CXmlTagString('jmx_endpoint'),
						new CXmlTagString('logtimefmt'),
						(new CXmlTagArray('master_item'))->setSchema(
							(new CXmlTagString('key'))->setRequired()
						),
						(new CXmlTagString('output_format'))
							->setDefaultValue(CXmlDefine::RAW)
							->addConstant(CXmlConstant::RAW, CXmlDefine::RAW)
							->addConstant(CXmlConstant::JSON, CXmlDefine::JSON),
						new CXmlTagString('params'),
						new CXmlTagString('password'),
						new CXmlTagString('port'),
						(new CXmlTagString('post_type'))
							->setDefaultValue(CXmlDefine::RAW)
							->addConstant(CXmlConstant::RAW, CXmlDefine::RAW)
							->addConstant(CXmlConstant::JSON, CXmlDefine::JSON)
							->addConstant(CXmlConstant::XML, CXmlDefine::XML),
						new CXmlTagString('posts'),
						(new CXmlTagIndexedArray('preprocessing'))->setSchema(
							(new CXmlTagArray('step'))->setSchema(
								(new CXmlTagString('params'))->setRequired(),
								(new CXmlTagString('type'))->setRequired()
									->addConstant(CXmlConstant::MULTIPLIER, CXmlDefine::MULTIPLIER)
									->addConstant(CXmlConstant::RTRIM, CXmlDefine::RTRIM)
									->addConstant(CXmlConstant::LTRIM, CXmlDefine::LTRIM)
									->addConstant(CXmlConstant::TRIM, CXmlDefine::TRIM)
									->addConstant(CXmlConstant::REGEX, CXmlDefine::REGEX)
									->addConstant(CXmlConstant::BOOL_TO_DECIMAL, CXmlDefine::BOOL_TO_DECIMAL)
									->addConstant(CXmlConstant::OCTAL_TO_DECIMAL, CXmlDefine::OCTAL_TO_DECIMAL)
									->addConstant(CXmlConstant::HEX_TO_DECIMAL, CXmlDefine::HEX_TO_DECIMAL)
									->addConstant(CXmlConstant::SIMPLE_CHANGE, CXmlDefine::SIMPLE_CHANGE)
									->addConstant(CXmlConstant::CHANGE_PER_SECOND, CXmlDefine::CHANGE_PER_SECOND)
									->addConstant(CXmlConstant::XMLPATH, CXmlDefine::XMLPATH)
									->addConstant(CXmlConstant::JSONPATH, CXmlDefine::JSONPATH)
									->addConstant(CXmlConstant::IN_RANGE, CXmlDefine::IN_RANGE)
									->addConstant(CXmlConstant::MATCHES_REGEX, CXmlDefine::MATCHES_REGEX)
									->addConstant(CXmlConstant::NOT_MATCHES_REGEX, CXmlDefine::NOT_MATCHES_REGEX)
									->addConstant(CXmlConstant::CHECK_JSON_ERROR, CXmlDefine::CHECK_JSON_ERROR)
									->addConstant(CXmlConstant::CHECK_XML_ERROR, CXmlDefine::CHECK_XML_ERROR)
									->addConstant(CXmlConstant::CHECK_REGEX_ERROR, CXmlDefine::CHECK_REGEX_ERROR)
									->addConstant(CXmlConstant::DISCARD_UNCHANGED, CXmlDefine::DISCARD_UNCHANGED)
									->addConstant(CXmlConstant::DISCARD_UNCHANGED_HEARTBEAT, CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT)
									->addConstant(CXmlConstant::JAVASCRIPT, CXmlDefine::JAVASCRIPT)
									->addConstant(CXmlConstant::PROMETHEUS_PATTERN, CXmlDefine::PROMETHEUS_PATTERN)
									->addConstant(CXmlConstant::PROMETHEUS_TO_JSON, CXmlDefine::PROMETHEUS_TO_JSON),
								(new CXmlTagString('error_handler'))
									->setDefaultValue(CXmlDefine::ORIGINAL_ERROR)
									->addConstant(CXmlConstant::ORIGINAL_ERROR, CXmlDefine::ORIGINAL_ERROR)
									->addConstant(CXmlConstant::DISCARD_VALUE, CXmlDefine::DISCARD_VALUE)
									->addConstant(CXmlConstant::CUSTOM_VALUE, CXmlDefine::CUSTOM_VALUE)
									->addConstant(CXmlConstant::CUSTOM_ERROR, CXmlDefine::CUSTOM_ERROR),
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
							->addConstant(CXmlConstant::GET, CXmlDefine::GET)
							->addConstant(CXmlConstant::POST, CXmlDefine::POST)
							->addConstant(CXmlConstant::PUT, CXmlDefine::PUT)
							->addConstant(CXmlConstant::HEAD, CXmlDefine::HEAD),
						(new CXmlTagString('retrieve_mode'))
							->setDefaultValue(CXmlDefine::BODY)
							->addConstant(CXmlConstant::BODY, CXmlDefine::BODY)
							->addConstant(CXmlConstant::HEADERS, CXmlDefine::HEADERS)
							->addConstant(CXmlConstant::BOTH, CXmlDefine::BOTH),
						new CXmlTagString('snmp_community'),
						new CXmlTagString('snmp_oid'),
						new CXmlTagString('snmpv3_authpassphrase'),
						(new CXmlTagString('snmpv3_authprotocol'))
							->setDefaultValue(CXmlDefine::SNMPV3_MD5)
							->addConstant(CXmlConstant::MD5, CXmlDefine::SNMPV3_MD5)
							->addConstant(CXmlConstant::SHA, CXmlDefine::SNMPV3_SHA),
						new CXmlTagString('snmpv3_contextname'),
						new CXmlTagString('snmpv3_privpassphrase'),
						(new CXmlTagString('snmpv3_privprotocol'))
							->setDefaultValue(CXmlDefine::DES)
							->addConstant(CXmlConstant::DES, CXmlDefine::DES)
							->addConstant(CXmlConstant::AES, CXmlDefine::AES),
						(new CXmlTagString('snmpv3_securitylevel'))
							->setDefaultValue(CXmlDefine::NOAUTHNOPRIV)
							->addConstant(CXmlConstant::NOAUTHNOPRIV, CXmlDefine::NOAUTHNOPRIV)
							->addConstant(CXmlConstant::AUTHNOPRIV, CXmlDefine::AUTHNOPRIV)
							->addConstant(CXmlConstant::AUTHPRIV, CXmlDefine::AUTHPRIV),
						new CXmlTagString('snmpv3_securityname'),
						new CXmlTagString('ssl_cert_file'),
						new CXmlTagString('ssl_key_file'),
						new CXmlTagString('ssl_key_password'),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
						new CXmlTagString('status_codes'),
						new CXmlTagString('timeout'),
						(new CXmlTagString('trends'))
							->setDefaultValue('365d'),
						(new CXmlTagString('type'))
							->setDefaultValue(CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
							->addConstant(CXmlConstant::ZABBIX_PASSIVE, CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
							->addConstant(CXmlConstant::SNMPV1, CXmlDefine::ITEM_TYPE_SNMPV1)
							->addConstant(CXmlConstant::TRAP, CXmlDefine::ITEM_TYPE_TRAP)
							->addConstant(CXmlConstant::SIMPLE, CXmlDefine::ITEM_TYPE_SIMPLE)
							->addConstant(CXmlConstant::SNMPV2, CXmlDefine::ITEM_TYPE_SNMPV2)
							->addConstant(CXmlConstant::INTERNAL, CXmlDefine::ITEM_TYPE_INTERNAL)
							->addConstant(CXmlConstant::SNMPV3, CXmlDefine::ITEM_TYPE_SNMPV3)
							->addConstant(CXmlConstant::ZABBIX_ACTIVE, CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE)
							->addConstant(CXmlConstant::AGGREGATE, CXmlDefine::ITEM_TYPE_AGGREGATE)
							->addConstant(CXmlConstant::EXTERNAL, CXmlDefine::ITEM_TYPE_EXTERNAL)
							->addConstant(CXmlConstant::ODBC, CXmlDefine::ITEM_TYPE_ODBC)
							->addConstant(CXmlConstant::IPMI, CXmlDefine::ITEM_TYPE_IPMI)
							->addConstant(CXmlConstant::SSH, CXmlDefine::ITEM_TYPE_SSH)
							->addConstant(CXmlConstant::TELNET, CXmlDefine::ITEM_TYPE_TELNET)
							->addConstant(CXmlConstant::CALCULATED, CXmlDefine::ITEM_TYPE_CALCULATED)
							->addConstant(CXmlConstant::JMX, CXmlDefine::ITEM_TYPE_JMX)
							->addConstant(CXmlConstant::SNMP_TRAP, CXmlDefine::ITEM_TYPE_SNMP_TRAP)
							->addConstant(CXmlConstant::DEPENDENT, CXmlDefine::ITEM_TYPE_DEPENDENT)
							->addConstant(CXmlConstant::HTTP_AGENT, CXmlDefine::ITEM_TYPE_HTTP_AGENT),
						new CXmlTagString('units'),
						new CXmlTagString('url'),
						new CXmlTagString('username'),
						(new CXmlTagString('value_type'))
							->setDefaultValue(CXmlDefine::UNSIGNED)
							->addConstant(CXmlConstant::FLOAT, CXmlDefine::FLOAT)
							->addConstant(CXmlConstant::CHAR, CXmlDefine::CHAR)
							->addConstant(CXmlConstant::LOG, CXmlDefine::LOG)
							->addConstant(CXmlConstant::UNSIGNED, CXmlDefine::UNSIGNED)
							->addConstant(CXmlConstant::TEXT, CXmlDefine::TEXT),
						(new CXmlTagArray('valuemap'))->setSchema(
							new CXmlTagString('name')
						),
						(new CXmlTagString('verify_host'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('verify_peer'))
							->setDefaultValue(CXmlDefine::NO)
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
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
									->addConstant(CXmlConstant::DISABLED, CXmlDefine::TRIGGER_DISABLED)
									->addConstant(CXmlConstant::TAG_VALUE, CXmlDefine::TRIGGER_TAG_VALUE),
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
									->addConstant(CXmlConstant::NO, CXmlDefine::NO)
									->addConstant(CXmlConstant::YES, CXmlDefine::YES),
								(new CXmlTagString('priority'))
									->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
									->addConstant(CXmlConstant::NOT_CLASSIFIED, CXmlDefine::NOT_CLASSIFIED)
									->addConstant(CXmlConstant::INFO, CXmlDefine::INFO)
									->addConstant(CXmlConstant::WARNING, CXmlDefine::WARNING)
									->addConstant(CXmlConstant::AVERAGE, CXmlDefine::AVERAGE)
									->addConstant(CXmlConstant::HIGH, CXmlDefine::HIGH)
									->addConstant(CXmlConstant::DISASTER, CXmlDefine::DISASTER),
								new CXmlTagString('recovery_expression'),
								(new CXmlTagString('recovery_mode'))
									->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
									->addConstant(CXmlConstant::EXPRESSION, CXmlDefine::TRIGGER_EXPRESSION)
									->addConstant(CXmlConstant::RECOVERY_EXPRESSION, CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
									->addConstant(CXmlConstant::NONE, CXmlDefine::TRIGGER_NONE),
								(new CXmlTagString('status'))
									->setDefaultValue(CXmlDefine::ENABLED)
									->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
									->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
								(new CXmlTagIndexedArray('tags'))->setSchema(
									(new CXmlTagArray('tag'))->setSchema(
										(new CXmlTagString('tag'))->setRequired(),
										new CXmlTagString('value')
									)
								),
								(new CXmlTagString('type'))
									->setDefaultValue(CXmlDefine::SINGLE)
									->addConstant(CXmlConstant::SINGLE, CXmlDefine::SINGLE)
									->addConstant(CXmlConstant::MULTIPLE, CXmlDefine::MULTIPLE),
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
					->addConstant(CXmlConstant::RAW, CXmlDefine::RAW)
					->addConstant(CXmlConstant::JSON, CXmlDefine::JSON)
					->addConstant(CXmlConstant::XML, CXmlDefine::XML),
				new CXmlTagString('posts'),
				(new CXmlTagIndexedArray('preprocessing'))->setSchema(
					(new CXmlTagArray('step'))->setSchema(
						(new CXmlTagString('params'))->setRequired(),
						(new CXmlTagString('type'))->setRequired()
							->addConstant(CXmlConstant::MULTIPLIER, CXmlDefine::MULTIPLIER)
							->addConstant(CXmlConstant::RTRIM, CXmlDefine::RTRIM)
							->addConstant(CXmlConstant::LTRIM, CXmlDefine::LTRIM)
							->addConstant(CXmlConstant::TRIM, CXmlDefine::TRIM)
							->addConstant(CXmlConstant::REGEX, CXmlDefine::REGEX)
							->addConstant(CXmlConstant::BOOL_TO_DECIMAL, CXmlDefine::BOOL_TO_DECIMAL)
							->addConstant(CXmlConstant::OCTAL_TO_DECIMAL, CXmlDefine::OCTAL_TO_DECIMAL)
							->addConstant(CXmlConstant::HEX_TO_DECIMAL, CXmlDefine::HEX_TO_DECIMAL)
							->addConstant(CXmlConstant::SIMPLE_CHANGE, CXmlDefine::SIMPLE_CHANGE)
							->addConstant(CXmlConstant::CHANGE_PER_SECOND, CXmlDefine::CHANGE_PER_SECOND)
							->addConstant(CXmlConstant::XMLPATH, CXmlDefine::XMLPATH)
							->addConstant(CXmlConstant::JSONPATH, CXmlDefine::JSONPATH)
							->addConstant(CXmlConstant::IN_RANGE, CXmlDefine::IN_RANGE)
							->addConstant(CXmlConstant::MATCHES_REGEX, CXmlDefine::MATCHES_REGEX)
							->addConstant(CXmlConstant::NOT_MATCHES_REGEX, CXmlDefine::NOT_MATCHES_REGEX)
							->addConstant(CXmlConstant::CHECK_JSON_ERROR, CXmlDefine::CHECK_JSON_ERROR)
							->addConstant(CXmlConstant::CHECK_XML_ERROR, CXmlDefine::CHECK_XML_ERROR)
							->addConstant(CXmlConstant::CHECK_REGEX_ERROR, CXmlDefine::CHECK_REGEX_ERROR)
							->addConstant(CXmlConstant::DISCARD_UNCHANGED, CXmlDefine::DISCARD_UNCHANGED)
							->addConstant(CXmlConstant::DISCARD_UNCHANGED_HEARTBEAT, CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT)
							->addConstant(CXmlConstant::JAVASCRIPT, CXmlDefine::JAVASCRIPT)
							->addConstant(CXmlConstant::PROMETHEUS_PATTERN, CXmlDefine::PROMETHEUS_PATTERN)
							->addConstant(CXmlConstant::PROMETHEUS_TO_JSON, CXmlDefine::PROMETHEUS_TO_JSON),
						(new CXmlTagString('error_handler'))
							->setDefaultValue(CXmlDefine::ORIGINAL_ERROR)
							->addConstant(CXmlConstant::ORIGINAL_ERROR, CXmlDefine::ORIGINAL_ERROR)
							->addConstant(CXmlConstant::DISCARD_VALUE, CXmlDefine::DISCARD_VALUE)
							->addConstant(CXmlConstant::CUSTOM_VALUE, CXmlDefine::CUSTOM_VALUE)
							->addConstant(CXmlConstant::CUSTOM_ERROR, CXmlDefine::CUSTOM_ERROR),
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
					->addConstant(CXmlConstant::GET, CXmlDefine::GET)
					->addConstant(CXmlConstant::POST, CXmlDefine::POST)
					->addConstant(CXmlConstant::PUT, CXmlDefine::PUT)
					->addConstant(CXmlConstant::HEAD, CXmlDefine::HEAD),
				(new CXmlTagString('retrieve_mode'))
					->setDefaultValue(CXmlDefine::BODY)
					->addConstant(CXmlConstant::BODY, CXmlDefine::BODY)
					->addConstant(CXmlConstant::HEADERS, CXmlDefine::HEADERS)
					->addConstant(CXmlConstant::BOTH, CXmlDefine::BOTH),
				new CXmlTagString('snmp_community'),
				new CXmlTagString('snmp_oid'),
				new CXmlTagString('snmpv3_authpassphrase'),
				(new CXmlTagString('snmpv3_authprotocol'))
					->setDefaultValue(CXmlDefine::SNMPV3_MD5)
					->addConstant(CXmlConstant::MD5, CXmlDefine::SNMPV3_MD5)
					->addConstant(CXmlConstant::SHA, CXmlDefine::SNMPV3_SHA),
				new CXmlTagString('snmpv3_contextname'),
				new CXmlTagString('snmpv3_privpassphrase'),
				(new CXmlTagString('snmpv3_privprotocol'))
					->setDefaultValue(CXmlDefine::DES)
					->addConstant(CXmlConstant::DES, CXmlDefine::DES)
					->addConstant(CXmlConstant::AES, CXmlDefine::AES),
				(new CXmlTagString('snmpv3_securitylevel'))
					->setDefaultValue(CXmlDefine::NOAUTHNOPRIV)
					->addConstant(CXmlConstant::NOAUTHNOPRIV, CXmlDefine::NOAUTHNOPRIV)
					->addConstant(CXmlConstant::AUTHNOPRIV, CXmlDefine::AUTHNOPRIV)
					->addConstant(CXmlConstant::AUTHPRIV, CXmlDefine::AUTHPRIV),
				new CXmlTagString('snmpv3_securityname'),
				new CXmlTagString('ssl_cert_file'),
				new CXmlTagString('ssl_key_file'),
				new CXmlTagString('ssl_key_password'),
				(new CXmlTagString('status'))
					->setDefaultValue(CXmlDefine::ENABLED)
					->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
					->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
				new CXmlTagString('status_codes'),
				new CXmlTagString('timeout'),
				(new CXmlTagIndexedArray('trigger_prototypes'))->setKey('triggerPrototypes')->setSchema(
					(new CXmlTagArray('trigger_prototype'))->setSchema(
						(new CXmlTagString('expression'))->setRequired(),
						(new CXmlTagString('name'))->setRequired()->setKey('description'),
						(new CXmlTagString('correlation_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_DISABLED)
							->addConstant(CXmlConstant::DISABLED, CXmlDefine::TRIGGER_DISABLED)
							->addConstant(CXmlConstant::TAG_VALUE, CXmlDefine::TRIGGER_TAG_VALUE),
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
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('priority'))
							->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
							->addConstant(CXmlConstant::NOT_CLASSIFIED, CXmlDefine::NOT_CLASSIFIED)
							->addConstant(CXmlConstant::INFO, CXmlDefine::INFO)
							->addConstant(CXmlConstant::WARNING, CXmlDefine::WARNING)
							->addConstant(CXmlConstant::AVERAGE, CXmlDefine::AVERAGE)
							->addConstant(CXmlConstant::HIGH, CXmlDefine::HIGH)
							->addConstant(CXmlConstant::DISASTER, CXmlDefine::DISASTER),
						new CXmlTagString('recovery_expression'),
						(new CXmlTagString('recovery_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant(CXmlConstant::EXPRESSION, CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant(CXmlConstant::RECOVERY_EXPRESSION, CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
							->addConstant(CXmlConstant::NONE, CXmlDefine::TRIGGER_NONE),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
						(new CXmlTagIndexedArray('tags'))->setSchema(
							(new CXmlTagArray('tag'))->setSchema(
								(new CXmlTagString('tag'))->setRequired(),
								new CXmlTagString('value')
							)
						),
						(new CXmlTagString('type'))
							->setDefaultValue(CXmlDefine::SINGLE)
							->addConstant(CXmlConstant::SINGLE, CXmlDefine::SINGLE)
							->addConstant(CXmlConstant::MULTIPLE, CXmlDefine::MULTIPLE),
						new CXmlTagString('url')
					)
				),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant(CXmlConstant::ZABBIX_PASSIVE, CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant(CXmlConstant::SNMPV1, CXmlDefine::ITEM_TYPE_SNMPV1)
					->addConstant(CXmlConstant::TRAP, CXmlDefine::ITEM_TYPE_TRAP)
					->addConstant(CXmlConstant::SIMPLE, CXmlDefine::ITEM_TYPE_SIMPLE)
					->addConstant(CXmlConstant::SNMPV2, CXmlDefine::ITEM_TYPE_SNMPV2)
					->addConstant(CXmlConstant::INTERNAL, CXmlDefine::ITEM_TYPE_INTERNAL)
					->addConstant(CXmlConstant::SNMPV3, CXmlDefine::ITEM_TYPE_SNMPV3)
					->addConstant(CXmlConstant::ZABBIX_ACTIVE, CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE)
					->addConstant(CXmlConstant::EXTERNAL, CXmlDefine::ITEM_TYPE_EXTERNAL)
					->addConstant(CXmlConstant::ODBC, CXmlDefine::ITEM_TYPE_ODBC)
					->addConstant(CXmlConstant::IPMI, CXmlDefine::ITEM_TYPE_IPMI)
					->addConstant(CXmlConstant::SSH, CXmlDefine::ITEM_TYPE_SSH)
					->addConstant(CXmlConstant::TELNET, CXmlDefine::ITEM_TYPE_TELNET)
					->addConstant(CXmlConstant::JMX, CXmlDefine::ITEM_TYPE_JMX)
					->addConstant(CXmlConstant::DEPENDENT, CXmlDefine::ITEM_TYPE_DEPENDENT)
					->addConstant(CXmlConstant::HTTP_AGENT, CXmlDefine::ITEM_TYPE_HTTP_AGENT),
				new CXmlTagString('url'),
				new CXmlTagString('username'),
				(new CXmlTagString('verify_host'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
				(new CXmlTagString('verify_peer'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES)
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
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						new CXmlTagString('headers'),
						new CXmlTagString('posts'),
						new CXmlTagString('query_fields'),
						new CXmlTagString('required'),
						(new CXmlTagString('retrieve_mode'))
							->setDefaultValue(CXmlDefine::BODY)
							->addConstant(CXmlConstant::BODY, CXmlDefine::BODY)
							->addConstant(CXmlConstant::HEADERS, CXmlDefine::HEADERS)
							->addConstant(CXmlConstant::BOTH, CXmlDefine::BOTH),
						new CXmlTagString('status_codes'),
						(new CXmlTagString('timeout'))
							->setDefaultValue('15s'),
						new CXmlTagString('variables')
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
					->addConstant(CXmlConstant::NONE, CXmlDefine::NONE)
					->addConstant(CXmlConstant::BASIC, CXmlDefine::BASIC)
					->addConstant(CXmlConstant::NTLM, CXmlDefine::NTLM),
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
					->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
					->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
				new CXmlTagString('variables'),
				(new CXmlTagString('verify_host'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
				(new CXmlTagString('verify_peer'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES)
			)
		),
		(new CXmlTagIndexedArray('items'))->setSchema(
			(new CXmlTagArray('item'))->setSchema(
				(new CXmlTagString('key'))->setRequired()->setKey('key_'),
				(new CXmlTagString('name'))->setRequired(),
				(new CXmlTagString('allow_traps'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
				(new CXmlTagString('allowed_hosts'))->setKey('trapper_hosts'),
				(new CXmlTagIndexedArray('applications'))->setSchema(
					(new CXmlTagArray('application'))->setSchema(
						(new CXmlTagString('name'))->setRequired()
					)
				),
				(new CXmlTagString('authtype'))
					->setDefaultValue('0')
					->addConstant(CXmlConstant::NONE, CXmlDefine::NONE, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant(CXmlConstant::BASIC, CXmlDefine::BASIC, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant(CXmlConstant::NTLM, CXmlDefine::NTLM, CXmlDefine::ITEM_TYPE_HTTP_AGENT)
					->addConstant(CXmlConstant::PASSWORD, CXmlDefine::PASSWORD, CXmlDefine::ITEM_TYPE_SSH)
					->addConstant(CXmlConstant::PUBLIC_KEY, CXmlDefine::PUBLIC_KEY, CXmlDefine::ITEM_TYPE_SSH)
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
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
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
					->addConstant(CXmlConstant::NONE, CXmlDefine::NONE)
					->addConstant(CXmlConstant::ALIAS, CXmlDefine::ALIAS)
					->addConstant(CXmlConstant::ASSET_TAG, CXmlDefine::ASSET_TAG)
					->addConstant(CXmlConstant::CHASSIS, CXmlDefine::CHASSIS)
					->addConstant(CXmlConstant::CONTACT, CXmlDefine::CONTACT)
					->addConstant(CXmlConstant::CONTRACT_NUMBER, CXmlDefine::CONTRACT_NUMBER)
					->addConstant(CXmlConstant::DATE_HW_DECOMM, CXmlDefine::DATE_HW_DECOMM)
					->addConstant(CXmlConstant::DATE_HW_EXPIRY, CXmlDefine::DATE_HW_EXPIRY)
					->addConstant(CXmlConstant::DATE_HW_INSTALL, CXmlDefine::DATE_HW_INSTALL)
					->addConstant(CXmlConstant::DATE_HW_PURCHASE, CXmlDefine::DATE_HW_PURCHASE)
					->addConstant(CXmlConstant::DEPLOYMENT_STATUS, CXmlDefine::DEPLOYMENT_STATUS)
					->addConstant(CXmlConstant::HARDWARE, CXmlDefine::HARDWARE)
					->addConstant(CXmlConstant::HARDWARE_FULL, CXmlDefine::HARDWARE_FULL)
					->addConstant(CXmlConstant::HOST_NETMASK, CXmlDefine::HOST_NETMASK)
					->addConstant(CXmlConstant::HOST_NETWORKS, CXmlDefine::HOST_NETWORKS)
					->addConstant(CXmlConstant::HOST_ROUTER, CXmlDefine::HOST_ROUTER)
					->addConstant(CXmlConstant::HW_ARCH, CXmlDefine::HW_ARCH)
					->addConstant(CXmlConstant::INSTALLER_NAME, CXmlDefine::INSTALLER_NAME)
					->addConstant(CXmlConstant::LOCATION, CXmlDefine::LOCATION)
					->addConstant(CXmlConstant::LOCATION_LAT, CXmlDefine::LOCATION_LAT)
					->addConstant(CXmlConstant::LOCATION_LON, CXmlDefine::LOCATION_LON)
					->addConstant(CXmlConstant::MACADDRESS_A, CXmlDefine::MACADDRESS_A)
					->addConstant(CXmlConstant::MACADDRESS_B, CXmlDefine::MACADDRESS_B)
					->addConstant(CXmlConstant::MODEL, CXmlDefine::MODEL)
					->addConstant(CXmlConstant::NAME, CXmlDefine::NAME)
					->addConstant(CXmlConstant::NOTES, CXmlDefine::NOTES)
					->addConstant(CXmlConstant::OOB_IP, CXmlDefine::OOB_IP)
					->addConstant(CXmlConstant::OOB_NETMASK, CXmlDefine::OOB_NETMASK)
					->addConstant(CXmlConstant::OOB_ROUTER, CXmlDefine::OOB_ROUTER)
					->addConstant(CXmlConstant::OS, CXmlDefine::OS)
					->addConstant(CXmlConstant::OS_FULL, CXmlDefine::OS_FULL)
					->addConstant(CXmlConstant::OS_SHORT, CXmlDefine::OS_SHORT)
					->addConstant(CXmlConstant::POC_1_CELL, CXmlDefine::POC_1_CELL)
					->addConstant(CXmlConstant::POC_1_EMAIL, CXmlDefine::POC_1_EMAIL)
					->addConstant(CXmlConstant::POC_1_NAME, CXmlDefine::POC_1_NAME)
					->addConstant(CXmlConstant::POC_1_NOTES, CXmlDefine::POC_1_NOTES)
					->addConstant(CXmlConstant::POC_1_PHONE_A, CXmlDefine::POC_1_PHONE_A)
					->addConstant(CXmlConstant::POC_1_PHONE_B, CXmlDefine::POC_1_PHONE_B)
					->addConstant(CXmlConstant::POC_1_SCREEN, CXmlDefine::POC_1_SCREEN)
					->addConstant(CXmlConstant::POC_2_CELL, CXmlDefine::POC_2_CELL)
					->addConstant(CXmlConstant::POC_2_EMAIL, CXmlDefine::POC_2_EMAIL)
					->addConstant(CXmlConstant::POC_2_NAME, CXmlDefine::POC_2_NAME)
					->addConstant(CXmlConstant::POC_2_NOTES, CXmlDefine::POC_2_NOTES)
					->addConstant(CXmlConstant::POC_2_PHONE_A, CXmlDefine::POC_2_PHONE_A)
					->addConstant(CXmlConstant::POC_2_PHONE_B, CXmlDefine::POC_2_PHONE_B)
					->addConstant(CXmlConstant::POC_2_SCREEN, CXmlDefine::POC_2_SCREEN)
					->addConstant(CXmlConstant::SERIALNO_A, CXmlDefine::SERIALNO_A)
					->addConstant(CXmlConstant::SERIALNO_B, CXmlDefine::SERIALNO_B)
					->addConstant(CXmlConstant::SITE_ADDRESS_A, CXmlDefine::SITE_ADDRESS_A)
					->addConstant(CXmlConstant::SITE_ADDRESS_B, CXmlDefine::SITE_ADDRESS_B)
					->addConstant(CXmlConstant::SITE_ADDRESS_C, CXmlDefine::SITE_ADDRESS_C)
					->addConstant(CXmlConstant::SITE_CITY, CXmlDefine::SITE_CITY)
					->addConstant(CXmlConstant::SITE_COUNTRY, CXmlDefine::SITE_COUNTRY)
					->addConstant(CXmlConstant::SITE_NOTES, CXmlDefine::SITE_NOTES)
					->addConstant(CXmlConstant::SITE_RACK, CXmlDefine::SITE_RACK)
					->addConstant(CXmlConstant::SITE_STATE, CXmlDefine::SITE_STATE)
					->addConstant(CXmlConstant::SITE_ZIP, CXmlDefine::SITE_ZIP)
					->addConstant(CXmlConstant::SOFTWARE, CXmlDefine::SOFTWARE)
					->addConstant(CXmlConstant::SOFTWARE_APP_A, CXmlDefine::SOFTWARE_APP_A)
					->addConstant(CXmlConstant::SOFTWARE_APP_B, CXmlDefine::SOFTWARE_APP_B)
					->addConstant(CXmlConstant::SOFTWARE_APP_C, CXmlDefine::SOFTWARE_APP_C)
					->addConstant(CXmlConstant::SOFTWARE_APP_D, CXmlDefine::SOFTWARE_APP_D)
					->addConstant(CXmlConstant::SOFTWARE_APP_E, CXmlDefine::SOFTWARE_APP_E)
					->addConstant(CXmlConstant::SOFTWARE_FULL, CXmlDefine::SOFTWARE_FULL)
					->addConstant(CXmlConstant::TAG, CXmlDefine::TAG)
					->addConstant(CXmlConstant::TYPE, CXmlDefine::TYPE)
					->addConstant(CXmlConstant::TYPE_FULL, CXmlDefine::TYPE_FULL)
					->addConstant(CXmlConstant::URL_A, CXmlDefine::URL_A)
					->addConstant(CXmlConstant::URL_B, CXmlDefine::URL_B)
					->addConstant(CXmlConstant::URL_C, CXmlDefine::URL_C)
					->addConstant(CXmlConstant::VENDOR, CXmlDefine::VENDOR),
				new CXmlTagString('ipmi_sensor'),
				new CXmlTagString('jmx_endpoint'),
				new CXmlTagString('logtimefmt'),
				(new CXmlTagArray('master_item'))->setSchema(
					(new CXmlTagString('key'))->setRequired()
				),
				(new CXmlTagString('output_format'))
					->setDefaultValue(CXmlDefine::RAW)
					->addConstant(CXmlConstant::RAW, CXmlDefine::RAW)
					->addConstant(CXmlConstant::JSON, CXmlDefine::JSON),
				new CXmlTagString('params'),
				new CXmlTagString('password'),
				new CXmlTagString('port'),
				(new CXmlTagString('post_type'))
					->setDefaultValue(CXmlDefine::RAW)
					->addConstant(CXmlConstant::RAW, CXmlDefine::RAW)
					->addConstant(CXmlConstant::JSON, CXmlDefine::JSON)
					->addConstant(CXmlConstant::XML, CXmlDefine::XML),
				new CXmlTagString('posts'),
				(new CXmlTagIndexedArray('preprocessing'))->setSchema(
					(new CXmlTagArray('step'))->setSchema(
						(new CXmlTagString('params'))->setRequired(),
						(new CXmlTagString('type'))->setRequired()
							->addConstant(CXmlConstant::MULTIPLIER, CXmlDefine::MULTIPLIER)
							->addConstant(CXmlConstant::RTRIM, CXmlDefine::RTRIM)
							->addConstant(CXmlConstant::LTRIM, CXmlDefine::LTRIM)
							->addConstant(CXmlConstant::TRIM, CXmlDefine::TRIM)
							->addConstant(CXmlConstant::REGEX, CXmlDefine::REGEX)
							->addConstant(CXmlConstant::BOOL_TO_DECIMAL, CXmlDefine::BOOL_TO_DECIMAL)
							->addConstant(CXmlConstant::OCTAL_TO_DECIMAL, CXmlDefine::OCTAL_TO_DECIMAL)
							->addConstant(CXmlConstant::HEX_TO_DECIMAL, CXmlDefine::HEX_TO_DECIMAL)
							->addConstant(CXmlConstant::SIMPLE_CHANGE, CXmlDefine::SIMPLE_CHANGE)
							->addConstant(CXmlConstant::CHANGE_PER_SECOND, CXmlDefine::CHANGE_PER_SECOND)
							->addConstant(CXmlConstant::XMLPATH, CXmlDefine::XMLPATH)
							->addConstant(CXmlConstant::JSONPATH, CXmlDefine::JSONPATH)
							->addConstant(CXmlConstant::IN_RANGE, CXmlDefine::IN_RANGE)
							->addConstant(CXmlConstant::MATCHES_REGEX, CXmlDefine::MATCHES_REGEX)
							->addConstant(CXmlConstant::NOT_MATCHES_REGEX, CXmlDefine::NOT_MATCHES_REGEX)
							->addConstant(CXmlConstant::CHECK_JSON_ERROR, CXmlDefine::CHECK_JSON_ERROR)
							->addConstant(CXmlConstant::CHECK_XML_ERROR, CXmlDefine::CHECK_XML_ERROR)
							->addConstant(CXmlConstant::CHECK_REGEX_ERROR, CXmlDefine::CHECK_REGEX_ERROR)
							->addConstant(CXmlConstant::DISCARD_UNCHANGED, CXmlDefine::DISCARD_UNCHANGED)
							->addConstant(CXmlConstant::DISCARD_UNCHANGED_HEARTBEAT, CXmlDefine::DISCARD_UNCHANGED_HEARTBEAT)
							->addConstant(CXmlConstant::JAVASCRIPT, CXmlDefine::JAVASCRIPT)
							->addConstant(CXmlConstant::PROMETHEUS_PATTERN, CXmlDefine::PROMETHEUS_PATTERN)
							->addConstant(CXmlConstant::PROMETHEUS_TO_JSON, CXmlDefine::PROMETHEUS_TO_JSON),
						(new CXmlTagString('error_handler'))
							->setDefaultValue(CXmlDefine::ORIGINAL_ERROR)
							->addConstant(CXmlConstant::ORIGINAL_ERROR, CXmlDefine::ORIGINAL_ERROR)
							->addConstant(CXmlConstant::DISCARD_VALUE, CXmlDefine::DISCARD_VALUE)
							->addConstant(CXmlConstant::CUSTOM_VALUE, CXmlDefine::CUSTOM_VALUE)
							->addConstant(CXmlConstant::CUSTOM_ERROR, CXmlDefine::CUSTOM_ERROR),
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
					->addConstant(CXmlConstant::GET, CXmlDefine::GET)
					->addConstant(CXmlConstant::POST, CXmlDefine::POST)
					->addConstant(CXmlConstant::PUT, CXmlDefine::PUT)
					->addConstant(CXmlConstant::HEAD, CXmlDefine::HEAD),
				(new CXmlTagString('retrieve_mode'))
					->setDefaultValue(CXmlDefine::BODY)
					->addConstant(CXmlConstant::BODY, CXmlDefine::BODY)
					->addConstant(CXmlConstant::HEADERS, CXmlDefine::HEADERS)
					->addConstant(CXmlConstant::BOTH, CXmlDefine::BOTH),
				new CXmlTagString('snmp_community'),
				new CXmlTagString('snmp_oid'),
				new CXmlTagString('snmpv3_authpassphrase'),
				(new CXmlTagString('snmpv3_authprotocol'))
					->setDefaultValue(CXmlDefine::SNMPV3_MD5)
					->addConstant(CXmlConstant::MD5, CXmlDefine::SNMPV3_MD5)
					->addConstant(CXmlConstant::SHA, CXmlDefine::SNMPV3_SHA),
				new CXmlTagString('snmpv3_contextname'),
				new CXmlTagString('snmpv3_privpassphrase'),
				(new CXmlTagString('snmpv3_privprotocol'))
					->setDefaultValue(CXmlDefine::DES)
					->addConstant(CXmlConstant::DES, CXmlDefine::DES)
					->addConstant(CXmlConstant::AES, CXmlDefine::AES),
				(new CXmlTagString('snmpv3_securitylevel'))
					->setDefaultValue(CXmlDefine::NOAUTHNOPRIV)
					->addConstant(CXmlConstant::NOAUTHNOPRIV, CXmlDefine::NOAUTHNOPRIV)
					->addConstant(CXmlConstant::AUTHNOPRIV, CXmlDefine::AUTHNOPRIV)
					->addConstant(CXmlConstant::AUTHPRIV, CXmlDefine::AUTHPRIV),
				new CXmlTagString('snmpv3_securityname'),
				new CXmlTagString('ssl_cert_file'),
				new CXmlTagString('ssl_key_file'),
				new CXmlTagString('ssl_key_password'),
				(new CXmlTagString('status'))
					->setDefaultValue(CXmlDefine::ENABLED)
					->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
					->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
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
							->addConstant(CXmlConstant::DISABLED, CXmlDefine::TRIGGER_DISABLED)
							->addConstant(CXmlConstant::TAG_VALUE, CXmlDefine::TRIGGER_TAG_VALUE),
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
							->addConstant(CXmlConstant::NO, CXmlDefine::NO)
							->addConstant(CXmlConstant::YES, CXmlDefine::YES),
						(new CXmlTagString('priority'))
							->setDefaultValue(CXmlDefine::NOT_CLASSIFIED)
							->addConstant(CXmlConstant::NOT_CLASSIFIED, CXmlDefine::NOT_CLASSIFIED)
							->addConstant(CXmlConstant::INFO, CXmlDefine::INFO)
							->addConstant(CXmlConstant::WARNING, CXmlDefine::WARNING)
							->addConstant(CXmlConstant::AVERAGE, CXmlDefine::AVERAGE)
							->addConstant(CXmlConstant::HIGH, CXmlDefine::HIGH)
							->addConstant(CXmlConstant::DISASTER, CXmlDefine::DISASTER),
						new CXmlTagString('recovery_expression'),
						(new CXmlTagString('recovery_mode'))
							->setDefaultValue(CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant(CXmlConstant::EXPRESSION, CXmlDefine::TRIGGER_EXPRESSION)
							->addConstant(CXmlConstant::RECOVERY_EXPRESSION, CXmlDefine::TRIGGER_RECOVERY_EXPRESSION)
							->addConstant(CXmlConstant::NONE, CXmlDefine::TRIGGER_NONE),
						(new CXmlTagString('status'))
							->setDefaultValue(CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::ENABLED, CXmlDefine::ENABLED)
							->addConstant(CXmlConstant::DISABLED, CXmlDefine::DISABLED),
						(new CXmlTagIndexedArray('tags'))->setSchema(
							(new CXmlTagArray('tag'))->setSchema(
								(new CXmlTagString('tag'))->setRequired(),
								new CXmlTagString('value')
							)
						),
						(new CXmlTagString('type'))
							->setDefaultValue(CXmlDefine::SINGLE)
							->addConstant(CXmlConstant::SINGLE, CXmlDefine::SINGLE)
							->addConstant(CXmlConstant::MULTIPLE, CXmlDefine::MULTIPLE),
						new CXmlTagString('url')
					)
				),
				(new CXmlTagString('type'))
					->setDefaultValue(CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant(CXmlConstant::ZABBIX_PASSIVE, CXmlDefine::ITEM_TYPE_ZABBIX_PASSIVE)
					->addConstant(CXmlConstant::SNMPV1, CXmlDefine::ITEM_TYPE_SNMPV1)
					->addConstant(CXmlConstant::TRAP, CXmlDefine::ITEM_TYPE_TRAP)
					->addConstant(CXmlConstant::SIMPLE, CXmlDefine::ITEM_TYPE_SIMPLE)
					->addConstant(CXmlConstant::SNMPV2, CXmlDefine::ITEM_TYPE_SNMPV2)
					->addConstant(CXmlConstant::INTERNAL, CXmlDefine::ITEM_TYPE_INTERNAL)
					->addConstant(CXmlConstant::SNMPV3, CXmlDefine::ITEM_TYPE_SNMPV3)
					->addConstant(CXmlConstant::ZABBIX_ACTIVE, CXmlDefine::ITEM_TYPE_ZABBIX_ACTIVE)
					->addConstant(CXmlConstant::AGGREGATE, CXmlDefine::ITEM_TYPE_AGGREGATE)
					->addConstant(CXmlConstant::EXTERNAL, CXmlDefine::ITEM_TYPE_EXTERNAL)
					->addConstant(CXmlConstant::ODBC, CXmlDefine::ITEM_TYPE_ODBC)
					->addConstant(CXmlConstant::IPMI, CXmlDefine::ITEM_TYPE_IPMI)
					->addConstant(CXmlConstant::SSH, CXmlDefine::ITEM_TYPE_SSH)
					->addConstant(CXmlConstant::TELNET, CXmlDefine::ITEM_TYPE_TELNET)
					->addConstant(CXmlConstant::CALCULATED, CXmlDefine::ITEM_TYPE_CALCULATED)
					->addConstant(CXmlConstant::JMX, CXmlDefine::ITEM_TYPE_JMX)
					->addConstant(CXmlConstant::SNMP_TRAP, CXmlDefine::ITEM_TYPE_SNMP_TRAP)
					->addConstant(CXmlConstant::DEPENDENT, CXmlDefine::ITEM_TYPE_DEPENDENT)
					->addConstant(CXmlConstant::HTTP_AGENT, CXmlDefine::ITEM_TYPE_HTTP_AGENT),
				new CXmlTagString('units'),
				new CXmlTagString('url'),
				new CXmlTagString('username'),
				(new CXmlTagString('value_type'))
					->setDefaultValue(CXmlDefine::UNSIGNED)
					->addConstant(CXmlConstant::FLOAT, CXmlDefine::FLOAT)
					->addConstant(CXmlConstant::CHAR, CXmlDefine::CHAR)
					->addConstant(CXmlConstant::LOG, CXmlDefine::LOG)
					->addConstant(CXmlConstant::UNSIGNED, CXmlDefine::UNSIGNED)
					->addConstant(CXmlConstant::TEXT, CXmlDefine::TEXT),
				(new CXmlTagArray('valuemap'))->setSchema(
					(new CXmlTagString('name'))
				),
				(new CXmlTagString('verify_host'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES),
				(new CXmlTagString('verify_peer'))
					->setDefaultValue(CXmlDefine::NO)
					->addConstant(CXmlConstant::NO, CXmlDefine::NO)
					->addConstant(CXmlConstant::YES, CXmlDefine::YES)
			)
		),
		(new CXmlTagIndexedArray('macros'))->setSchema(
			(new CXmlTagArray('macro'))->setSchema(
				(new CXmlTagString('macro'))->setRequired(),
				new CXmlTagString('value')
			)
		),
		(new CXmlTagIndexedArray('screens'))->setSchema(
			(new CXmlTagArray('screen'))->setSchema(
				new CXmlTagString('name'),
				new CXmlTagString('hsize'),
				(new CXmlTagIndexedArray('screen_items'))->setKey('screenitems')->setSchema(
					(new CXmlTagArray('screen_item'))->setSchema(
						new CXmlTagString('x'),
						new CXmlTagString('y'),
						new CXmlTagString('application'),
						new CXmlTagString('colspan'),
						new CXmlTagString('dynamic'),
						new CXmlTagString('elements'),
						new CXmlTagString('halign'),
						new CXmlTagString('height'),
						new CXmlTagString('max_columns'),
						(new CXmlTagString('resource'))->setKey('resourceid'),
						new CXmlTagString('resourcetype'),
						new CXmlTagString('rowspan'),
						new CXmlTagString('sort_triggers'),
						new CXmlTagString('style'),
						new CXmlTagString('url'),
						new CXmlTagString('valign'),
						new CXmlTagString('width')
					)
				),
				new CXmlTagString('vsize')
			)
		),
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
		)
	)
);
