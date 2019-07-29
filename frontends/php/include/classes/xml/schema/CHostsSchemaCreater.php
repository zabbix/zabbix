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


class CHostsSchemaCreater implements CSchemaCreater {

	public function create() {
		return (new CIndexedArrayXmlTag('hosts'))
			->setSchema(
				(new CArrayXmlTag('host'))
					->setSchema(
						(new CStringXmlTag('host'))->setRequired(),
						(new CIndexedArrayXmlTag('applications'))
							->setSchema(
								(new CArrayXmlTag('application'))
									->setSchema(
										(new CStringXmlTag('name'))->setRequired()
									)
							),
						new CStringXmlTag('description'),
						(new CIndexedArrayXmlTag('discovery_rules'))
							->setKey('discoveryRules')
							->setSchema(
								(new CArrayXmlTag('discovery_rule'))
									->setSchema(
										(new CStringXmlTag('key'))
											->setRequired()
											->setKey('key_'),
										(new CStringXmlTag('name'))->setRequired(),
										(new CStringXmlTag('allow_traps'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CStringXmlTag('allowed_hosts'))->setKey('trapper_hosts'),
										(new CStringXmlTag('authtype'))
											->setDefaultValue(CXmlConstantValue::NONE)
											->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
											->addConstant(CXmlConstantName::BASIC, CXmlConstantValue::BASIC, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
											->addConstant(CXmlConstantName::NTLM, CXmlConstantValue::NTLM, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
											->addConstant(CXmlConstantName::PASSWORD, CXmlConstantValue::PASSWORD, CXmlConstantValue::ITEM_TYPE_SSH)
											->addConstant(CXmlConstantName::PUBLIC_KEY, CXmlConstantValue::PUBLIC_KEY, CXmlConstantValue::ITEM_TYPE_SSH)
											->setExportHandler(function(array $data, CXmlTagInterface $class) {
												return $class->getConstantByValue($data['authtype'], $data['type']);
											})
											->setImportHandler(function(array $data, CXmlTagInterface $class) {
												if (!array_key_exists('authtype', $data)) {
													return (string) CXmlConstantValue::NONE;
												}

												$type = ($data['type'] === CXmlConstantName::HTTP_AGENT
													? CXmlConstantValue::ITEM_TYPE_HTTP_AGENT
													: CXmlConstantValue::ITEM_TYPE_SSH);
												return (string) $class->getConstantValueByName($data['authtype'], $type);
											}),
										(new CStringXmlTag('delay'))->setDefaultValue('1m'),
										new CStringXmlTag('description'),
										(new CArrayXmlTag('filter'))
											->setSchema(
												(new CIndexedArrayXmlTag('conditions'))
													->setSchema(
														(new CArrayXmlTag('condition'))
															->setSchema(
																(new CStringXmlTag('formulaid'))->setRequired(),
																(new CStringXmlTag('macro'))->setRequired(),
																(new CStringXmlTag('operator'))
																	->setDefaultValue(CXmlConstantValue::CONDITION_MATCHES_REGEX)
																	->addConstant(CXmlConstantName::MATCHES_REGEX, CXmlConstantValue::CONDITION_MATCHES_REGEX)
																	->addConstant(CXmlConstantName::NOT_MATCHES_REGEX, CXmlConstantValue::CONDITION_NOT_MATCHES_REGEX),
																new CStringXmlTag('value')
															)
													),
												(new CStringXmlTag('evaltype'))
													->setDefaultValue(CXmlConstantValue::AND_OR)
													->addConstant(CXmlConstantName::AND_OR, CXmlConstantValue::AND_OR)
													->addConstant(CXmlConstantName::XML_AND, CXmlConstantValue::XML_AND)
													->addConstant(CXmlConstantName::XML_OR, CXmlConstantValue::XML_OR)
													->addConstant(CXmlConstantName::FORMULA, CXmlConstantValue::FORMULA),
												(new CStringXmlTag('formula'))
											)->setImportHandler(function(array $data, CXmlTagInterface $class) {
												if (!array_key_exists('filter', $data)) {
													return [
														'conditions' => '',
														'evaltype' => '0',
														'formula' => ''
													];
												}

												return $data['filter'];
											}),
										(new CStringXmlTag('follow_redirects'))
											->setDefaultValue(CXmlConstantValue::YES)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CIndexedArrayXmlTag('graph_prototypes'))
											->setKey('graphPrototypes')
											->setSchema(
												(new CArrayXmlTag('graph_prototype'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired(),
														(new CIndexedArrayXmlTag('graph_items'))
															->setRequired()
															->setKey('gitems')
															->setSchema(
																(new CArrayXmlTag('graph_item'))
																	->setSchema(
																		(new CArrayXmlTag('item'))
																			->setRequired()
																			->setKey('itemid')
																			->setSchema(
																				(new CStringXmlTag('host'))->setRequired(),
																				(new CStringXmlTag('key'))->setRequired()
																			),
																		(new CStringXmlTag('calc_fnc'))
																			->setDefaultValue(CXmlConstantValue::AVG)
																			->addConstant(CXmlConstantName::MIN, CXmlConstantValue::MIN)
																			->addConstant(CXmlConstantName::AVG, CXmlConstantValue::AVG)
																			->addConstant(CXmlConstantName::MAX, CXmlConstantValue::MAX)
																			->addConstant(CXmlConstantName::ALL, CXmlConstantValue::ALL)
																			->addConstant(CXmlConstantName::LAST, CXmlConstantValue::LAST),
																		new CStringXmlTag('color'),
																		(new CStringXmlTag('drawtype'))
																			->setDefaultValue(CXmlConstantValue::SINGLE_LINE)
																			->addConstant(CXmlConstantName::SINGLE_LINE, CXmlConstantValue::SINGLE_LINE)
																			->addConstant(CXmlConstantName::FILLED_REGION, CXmlConstantValue::FILLED_REGION)
																			->addConstant(CXmlConstantName::BOLD_LINE, CXmlConstantValue::BOLD_LINE)
																			->addConstant(CXmlConstantName::DOTTED_LINE, CXmlConstantValue::DOTTED_LINE)
																			->addConstant(CXmlConstantName::DASHED_LINE, CXmlConstantValue::DASHED_LINE)
																			->addConstant(CXmlConstantName::GRADIENT_LINE, CXmlConstantValue::GRADIENT_LINE),
																		(new CStringXmlTag('sortorder'))->setDefaultValue('0'),
																		(new CStringXmlTag('type'))
																			->setDefaultValue(CXmlConstantValue::SIMPLE)
																			->addConstant(CXmlConstantName::SIMPLE, CXmlConstantValue::SIMPLE)
																			->addConstant(CXmlConstantName::GRAPH_SUM, CXmlConstantValue::GRAPH_SUM),
																		(new CStringXmlTag('yaxisside'))
																			->setDefaultValue(CXmlConstantValue::LEFT)
																			->addConstant(CXmlConstantName::LEFT, CXmlConstantValue::LEFT)
																			->addConstant(CXmlConstantName::RIGHT, CXmlConstantValue::RIGHT)
																	)
															),
														(new CStringXmlTag('height'))->setDefaultValue('200'),
														(new CStringXmlTag('percent_left'))->setDefaultValue('0'),
														(new CStringXmlTag('percent_right'))->setDefaultValue('0'),
														(new CStringXmlTag('show_3d'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('show_legend'))
															->setDefaultValue(CXmlConstantValue::YES)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('show_triggers'))
															->setDefaultValue(CXmlConstantValue::YES)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('show_work_period'))
															->setDefaultValue(CXmlConstantValue::YES)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('type'))
															->setKey('graphtype')
															->setDefaultValue(CXmlConstantValue::NORMAL)
															->addConstant(CXmlConstantName::NORMAL, CXmlConstantValue::NORMAL)
															->addConstant(CXmlConstantName::STACKED, CXmlConstantValue::STACKED)
															->addConstant(CXmlConstantName::PIE, CXmlConstantValue::PIE)
															->addConstant(CXmlConstantName::EXPLODED, CXmlConstantValue::EXPLODED),
														(new CStringXmlTag('width'))->setDefaultValue('900'),
														(new CStringXmlTag('yaxismax'))->setDefaultValue('100'),
														(new CStringXmlTag('yaxismin'))->setDefaultValue('0'),
														(new CStringXmlTag('ymax_item_1'))->setKey('ymax_itemid'),
														(new CStringXmlTag('ymax_type_1'))
															->setKey('ymax_type')
															->setDefaultValue(CXmlConstantValue::CALCULATED)
															->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::CALCULATED)
															->addConstant(CXmlConstantName::FIXED, CXmlConstantValue::FIXED)
															->addConstant(CXmlConstantName::ITEM, CXmlConstantValue::ITEM),
														(new CStringXmlTag('ymin_item_1'))->setKey('ymin_itemid'),
														(new CStringXmlTag('ymin_type_1'))
															->setKey('ymin_type')
															->setDefaultValue(CXmlConstantValue::CALCULATED)
															->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::CALCULATED)
															->addConstant(CXmlConstantName::FIXED, CXmlConstantValue::FIXED)
															->addConstant(CXmlConstantName::ITEM, CXmlConstantValue::ITEM)
													)
											),
										(new CIndexedArrayXmlTag('headers'))
											->setSchema(
												(new CArrayXmlTag('header'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired(),
														(new CStringXmlTag('value'))->setRequired()
													)
											),
										(new CIndexedArrayXmlTag('host_prototypes'))
											->setKey('hostPrototypes')
											->setSchema(
												(new CArrayXmlTag('host_prototype'))
													->setSchema(
														(new CIndexedArrayXmlTag('group_links'))
															->setKey('groupLinks')
															->setSchema(
																(new CArrayXmlTag('group_link'))
																	->setSchema(
																		(new CArrayXmlTag('group'))
																			->setKey('groupid')
																			->setSchema(
																				(new CStringXmlTag('name'))->setRequired()
																			)
																	)
															),
														(new CIndexedArrayXmlTag('group_prototypes'))
															->setKey('groupPrototypes')
															->setSchema(
																(new CArrayXmlTag('group_prototype'))
																	->setSchema(
																		(new CStringXmlTag('name'))->setRequired()
																	)
															),
														(new CStringXmlTag('host'))->setRequired(),
														new CStringXmlTag('name'),
														(new CStringXmlTag('status'))
															->setDefaultValue(CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
														(new CIndexedArrayXmlTag('templates'))
															->setSchema(
																(new CArrayXmlTag('template'))
																	->setSchema(
																		(new CStringXmlTag('name'))
																			->setRequired()
																			->setKey('host')
																	)
															)
													)
											),
										new CStringXmlTag('http_proxy'),
										new CStringXmlTag('interface_ref'),
										new CStringXmlTag('ipmi_sensor'),
										(new CIndexedArrayXmlTag('item_prototypes'))
											->setKey('itemPrototypes')
											->setSchema(
												(new CArrayXmlTag('item_prototype'))
													->setSchema(
														(new CStringXmlTag('key'))
															->setRequired()
															->setKey('key_'),
														(new CStringXmlTag('name'))->setRequired(),
														(new CStringXmlTag('allow_traps'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('allowed_hosts'))->setKey('trapper_hosts'),
														(new CIndexedArrayXmlTag('applications'))
															->setSchema(
																(new CArrayXmlTag('application'))
																	->setSchema(
																		(new CStringXmlTag('name'))->setRequired()
																	)
															),
														(new CStringXmlTag('authtype'))
															->setDefaultValue(CXmlConstantValue::NONE)
															->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
															->addConstant(CXmlConstantName::BASIC, CXmlConstantValue::BASIC, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
															->addConstant(CXmlConstantName::NTLM, CXmlConstantValue::NTLM, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
															->addConstant(CXmlConstantName::PASSWORD, CXmlConstantValue::PASSWORD, CXmlConstantValue::ITEM_TYPE_SSH)
															->addConstant(CXmlConstantName::PUBLIC_KEY, CXmlConstantValue::PUBLIC_KEY, CXmlConstantValue::ITEM_TYPE_SSH)
															->setExportHandler(function(array $data, CXmlTagInterface $class) {
																return $class->getConstantByValue($data['authtype'], $data['type']);
															})
															->setImportHandler(function(array $data, CXmlTagInterface $class) {
																if (!array_key_exists('authtype', $data)) {
																	return (string) CXmlConstantValue::NONE;
																}

																$type = ($data['type'] === CXmlConstantName::HTTP_AGENT
																	? CXmlConstantValue::ITEM_TYPE_HTTP_AGENT
																	: CXmlConstantValue::ITEM_TYPE_SSH);
																return (string) $class->getConstantValueByName($data['authtype'], $type);
															}),
														(new CStringXmlTag('delay'))->setDefaultValue('1m'),
														new CStringXmlTag('description'),
														(new CStringXmlTag('follow_redirects'))
															->setDefaultValue(CXmlConstantValue::YES)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CIndexedArrayXmlTag('headers'))
															->setSchema(
																(new CArrayXmlTag('header'))
																	->setSchema(
																		(new CStringXmlTag('name'))->setRequired(),
																		(new CStringXmlTag('value'))->setRequired()
																	)
															),
														(new CStringXmlTag('history'))->setDefaultValue('90d'),
														new CStringXmlTag('http_proxy'),
														new CStringXmlTag('interface_ref'),
														(new CStringXmlTag('inventory_link'))
															->setDefaultValue(CXmlConstantValue::NONE)
															->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE)
															->addConstant(CXmlConstantName::ALIAS, CXmlConstantValue::ALIAS)
															->addConstant(CXmlConstantName::ASSET_TAG, CXmlConstantValue::ASSET_TAG)
															->addConstant(CXmlConstantName::CHASSIS, CXmlConstantValue::CHASSIS)
															->addConstant(CXmlConstantName::CONTACT, CXmlConstantValue::CONTACT)
															->addConstant(CXmlConstantName::CONTRACT_NUMBER, CXmlConstantValue::CONTRACT_NUMBER)
															->addConstant(CXmlConstantName::DATE_HW_DECOMM, CXmlConstantValue::DATE_HW_DECOMM)
															->addConstant(CXmlConstantName::DATE_HW_EXPIRY, CXmlConstantValue::DATE_HW_EXPIRY)
															->addConstant(CXmlConstantName::DATE_HW_INSTALL, CXmlConstantValue::DATE_HW_INSTALL)
															->addConstant(CXmlConstantName::DATE_HW_PURCHASE, CXmlConstantValue::DATE_HW_PURCHASE)
															->addConstant(CXmlConstantName::DEPLOYMENT_STATUS, CXmlConstantValue::DEPLOYMENT_STATUS)
															->addConstant(CXmlConstantName::HARDWARE, CXmlConstantValue::HARDWARE)
															->addConstant(CXmlConstantName::HARDWARE_FULL, CXmlConstantValue::HARDWARE_FULL)
															->addConstant(CXmlConstantName::HOST_NETMASK, CXmlConstantValue::HOST_NETMASK)
															->addConstant(CXmlConstantName::HOST_NETWORKS, CXmlConstantValue::HOST_NETWORKS)
															->addConstant(CXmlConstantName::HOST_ROUTER, CXmlConstantValue::HOST_ROUTER)
															->addConstant(CXmlConstantName::HW_ARCH, CXmlConstantValue::HW_ARCH)
															->addConstant(CXmlConstantName::INSTALLER_NAME, CXmlConstantValue::INSTALLER_NAME)
															->addConstant(CXmlConstantName::LOCATION, CXmlConstantValue::LOCATION)
															->addConstant(CXmlConstantName::LOCATION_LAT, CXmlConstantValue::LOCATION_LAT)
															->addConstant(CXmlConstantName::LOCATION_LON, CXmlConstantValue::LOCATION_LON)
															->addConstant(CXmlConstantName::MACADDRESS_A, CXmlConstantValue::MACADDRESS_A)
															->addConstant(CXmlConstantName::MACADDRESS_B, CXmlConstantValue::MACADDRESS_B)
															->addConstant(CXmlConstantName::MODEL, CXmlConstantValue::MODEL)
															->addConstant(CXmlConstantName::NAME, CXmlConstantValue::NAME)
															->addConstant(CXmlConstantName::NOTES, CXmlConstantValue::NOTES)
															->addConstant(CXmlConstantName::OOB_IP, CXmlConstantValue::OOB_IP)
															->addConstant(CXmlConstantName::OOB_NETMASK, CXmlConstantValue::OOB_NETMASK)
															->addConstant(CXmlConstantName::OOB_ROUTER, CXmlConstantValue::OOB_ROUTER)
															->addConstant(CXmlConstantName::OS, CXmlConstantValue::OS)
															->addConstant(CXmlConstantName::OS_FULL, CXmlConstantValue::OS_FULL)
															->addConstant(CXmlConstantName::OS_SHORT, CXmlConstantValue::OS_SHORT)
															->addConstant(CXmlConstantName::POC_1_CELL, CXmlConstantValue::POC_1_CELL)
															->addConstant(CXmlConstantName::POC_1_EMAIL, CXmlConstantValue::POC_1_EMAIL)
															->addConstant(CXmlConstantName::POC_1_NAME, CXmlConstantValue::POC_1_NAME)
															->addConstant(CXmlConstantName::POC_1_NOTES, CXmlConstantValue::POC_1_NOTES)
															->addConstant(CXmlConstantName::POC_1_PHONE_A, CXmlConstantValue::POC_1_PHONE_A)
															->addConstant(CXmlConstantName::POC_1_PHONE_B, CXmlConstantValue::POC_1_PHONE_B)
															->addConstant(CXmlConstantName::POC_1_SCREEN, CXmlConstantValue::POC_1_SCREEN)
															->addConstant(CXmlConstantName::POC_2_CELL, CXmlConstantValue::POC_2_CELL)
															->addConstant(CXmlConstantName::POC_2_EMAIL, CXmlConstantValue::POC_2_EMAIL)
															->addConstant(CXmlConstantName::POC_2_NAME, CXmlConstantValue::POC_2_NAME)
															->addConstant(CXmlConstantName::POC_2_NOTES, CXmlConstantValue::POC_2_NOTES)
															->addConstant(CXmlConstantName::POC_2_PHONE_A, CXmlConstantValue::POC_2_PHONE_A)
															->addConstant(CXmlConstantName::POC_2_PHONE_B, CXmlConstantValue::POC_2_PHONE_B)
															->addConstant(CXmlConstantName::POC_2_SCREEN, CXmlConstantValue::POC_2_SCREEN)
															->addConstant(CXmlConstantName::SERIALNO_A, CXmlConstantValue::SERIALNO_A)
															->addConstant(CXmlConstantName::SERIALNO_B, CXmlConstantValue::SERIALNO_B)
															->addConstant(CXmlConstantName::SITE_ADDRESS_A, CXmlConstantValue::SITE_ADDRESS_A)
															->addConstant(CXmlConstantName::SITE_ADDRESS_B, CXmlConstantValue::SITE_ADDRESS_B)
															->addConstant(CXmlConstantName::SITE_ADDRESS_C, CXmlConstantValue::SITE_ADDRESS_C)
															->addConstant(CXmlConstantName::SITE_CITY, CXmlConstantValue::SITE_CITY)
															->addConstant(CXmlConstantName::SITE_COUNTRY, CXmlConstantValue::SITE_COUNTRY)
															->addConstant(CXmlConstantName::SITE_NOTES, CXmlConstantValue::SITE_NOTES)
															->addConstant(CXmlConstantName::SITE_RACK, CXmlConstantValue::SITE_RACK)
															->addConstant(CXmlConstantName::SITE_STATE, CXmlConstantValue::SITE_STATE)
															->addConstant(CXmlConstantName::SITE_ZIP, CXmlConstantValue::SITE_ZIP)
															->addConstant(CXmlConstantName::SOFTWARE, CXmlConstantValue::SOFTWARE)
															->addConstant(CXmlConstantName::SOFTWARE_APP_A, CXmlConstantValue::SOFTWARE_APP_A)
															->addConstant(CXmlConstantName::SOFTWARE_APP_B, CXmlConstantValue::SOFTWARE_APP_B)
															->addConstant(CXmlConstantName::SOFTWARE_APP_C, CXmlConstantValue::SOFTWARE_APP_C)
															->addConstant(CXmlConstantName::SOFTWARE_APP_D, CXmlConstantValue::SOFTWARE_APP_D)
															->addConstant(CXmlConstantName::SOFTWARE_APP_E, CXmlConstantValue::SOFTWARE_APP_E)
															->addConstant(CXmlConstantName::SOFTWARE_FULL, CXmlConstantValue::SOFTWARE_FULL)
															->addConstant(CXmlConstantName::TAG, CXmlConstantValue::TAG)
															->addConstant(CXmlConstantName::TYPE, CXmlConstantValue::TYPE)
															->addConstant(CXmlConstantName::TYPE_FULL, CXmlConstantValue::TYPE_FULL)
															->addConstant(CXmlConstantName::URL_A, CXmlConstantValue::URL_A)
															->addConstant(CXmlConstantName::URL_B, CXmlConstantValue::URL_B)
															->addConstant(CXmlConstantName::URL_C, CXmlConstantValue::URL_C)
															->addConstant(CXmlConstantName::VENDOR, CXmlConstantValue::VENDOR),
														new CStringXmlTag('ipmi_sensor'),
														new CStringXmlTag('jmx_endpoint'),
														new CStringXmlTag('logtimefmt'),
														(new CArrayXmlTag('master_item'))
															->setSchema(
																(new CStringXmlTag('key'))->setRequired()
															),
														(new CStringXmlTag('output_format'))
															->setDefaultValue(CXmlConstantValue::RAW)
															->addConstant(CXmlConstantName::RAW, CXmlConstantValue::RAW)
															->addConstant(CXmlConstantName::JSON, CXmlConstantValue::JSON),
														new CStringXmlTag('params'),
														new CStringXmlTag('password'),
														new CStringXmlTag('port'),
														(new CStringXmlTag('post_type'))
															->setDefaultValue(CXmlConstantValue::RAW)
															->addConstant(CXmlConstantName::RAW, CXmlConstantValue::RAW)
															->addConstant(CXmlConstantName::JSON, CXmlConstantValue::JSON)
															->addConstant(CXmlConstantName::XML, CXmlConstantValue::XML),
														new CStringXmlTag('posts'),
														(new CIndexedArrayXmlTag('preprocessing'))
															->setSchema(
																(new CArrayXmlTag('step'))
																	->setSchema(
																		(new CStringXmlTag('params'))->setRequired(),
																		(new CStringXmlTag('type'))
																			->setRequired()
																			->addConstant(CXmlConstantName::MULTIPLIER, CXmlConstantValue::MULTIPLIER)
																			->addConstant(CXmlConstantName::RTRIM, CXmlConstantValue::RTRIM)
																			->addConstant(CXmlConstantName::LTRIM, CXmlConstantValue::LTRIM)
																			->addConstant(CXmlConstantName::TRIM, CXmlConstantValue::TRIM)
																			->addConstant(CXmlConstantName::REGEX, CXmlConstantValue::REGEX)
																			->addConstant(CXmlConstantName::BOOL_TO_DECIMAL, CXmlConstantValue::BOOL_TO_DECIMAL)
																			->addConstant(CXmlConstantName::OCTAL_TO_DECIMAL, CXmlConstantValue::OCTAL_TO_DECIMAL)
																			->addConstant(CXmlConstantName::HEX_TO_DECIMAL, CXmlConstantValue::HEX_TO_DECIMAL)
																			->addConstant(CXmlConstantName::SIMPLE_CHANGE, CXmlConstantValue::SIMPLE_CHANGE)
																			->addConstant(CXmlConstantName::CHANGE_PER_SECOND, CXmlConstantValue::CHANGE_PER_SECOND)
																			->addConstant(CXmlConstantName::XMLPATH, CXmlConstantValue::XMLPATH)
																			->addConstant(CXmlConstantName::JSONPATH, CXmlConstantValue::JSONPATH)
																			->addConstant(CXmlConstantName::IN_RANGE, CXmlConstantValue::IN_RANGE)
																			->addConstant(CXmlConstantName::MATCHES_REGEX, CXmlConstantValue::MATCHES_REGEX)
																			->addConstant(CXmlConstantName::NOT_MATCHES_REGEX, CXmlConstantValue::NOT_MATCHES_REGEX)
																			->addConstant(CXmlConstantName::CHECK_JSON_ERROR, CXmlConstantValue::CHECK_JSON_ERROR)
																			->addConstant(CXmlConstantName::CHECK_XML_ERROR, CXmlConstantValue::CHECK_XML_ERROR)
																			->addConstant(CXmlConstantName::CHECK_REGEX_ERROR, CXmlConstantValue::CHECK_REGEX_ERROR)
																			->addConstant(CXmlConstantName::DISCARD_UNCHANGED, CXmlConstantValue::DISCARD_UNCHANGED)
																			->addConstant(CXmlConstantName::DISCARD_UNCHANGED_HEARTBEAT, CXmlConstantValue::DISCARD_UNCHANGED_HEARTBEAT)
																			->addConstant(CXmlConstantName::JAVASCRIPT, CXmlConstantValue::JAVASCRIPT)
																			->addConstant(CXmlConstantName::PROMETHEUS_PATTERN, CXmlConstantValue::PROMETHEUS_PATTERN)
																			->addConstant(CXmlConstantName::PROMETHEUS_TO_JSON, CXmlConstantValue::PROMETHEUS_TO_JSON),
																		(new CStringXmlTag('error_handler'))
																			->setDefaultValue(CXmlConstantValue::ORIGINAL_ERROR)
																			->addConstant(CXmlConstantName::ORIGINAL_ERROR, CXmlConstantValue::ORIGINAL_ERROR)
																			->addConstant(CXmlConstantName::DISCARD_VALUE, CXmlConstantValue::DISCARD_VALUE)
																			->addConstant(CXmlConstantName::CUSTOM_VALUE, CXmlConstantValue::CUSTOM_VALUE)
																			->addConstant(CXmlConstantName::CUSTOM_ERROR, CXmlConstantValue::CUSTOM_ERROR),
																		new CStringXmlTag('error_handler_params')
																	)
															),
														new CStringXmlTag('privatekey'),
														new CStringXmlTag('publickey'),
														(new CIndexedArrayXmlTag('query_fields'))
															->setSchema(
																(new CArrayXmlTag('query_field'))
																	->setSchema(
																		(new CStringXmlTag('name'))->setRequired(),
																		new CStringXmlTag('value')
																	)
															),
														(new CStringXmlTag('request_method'))
															->setDefaultValue(CXmlConstantValue::GET)
															->addConstant(CXmlConstantName::GET, CXmlConstantValue::GET)
															->addConstant(CXmlConstantName::POST, CXmlConstantValue::POST)
															->addConstant(CXmlConstantName::PUT, CXmlConstantValue::PUT)
															->addConstant(CXmlConstantName::HEAD, CXmlConstantValue::HEAD),
														(new CStringXmlTag('retrieve_mode'))
															->setDefaultValue(CXmlConstantValue::BODY)
															->addConstant(CXmlConstantName::BODY, CXmlConstantValue::BODY)
															->addConstant(CXmlConstantName::HEADERS, CXmlConstantValue::HEADERS)
															->addConstant(CXmlConstantName::BOTH, CXmlConstantValue::BOTH),
														new CStringXmlTag('snmp_community'),
														new CStringXmlTag('snmp_oid'),
														new CStringXmlTag('snmpv3_authpassphrase'),
														(new CStringXmlTag('snmpv3_authprotocol'))
															->setDefaultValue(CXmlConstantValue::SNMPV3_MD5)
															->addConstant(CXmlConstantName::MD5, CXmlConstantValue::SNMPV3_MD5)
															->addConstant(CXmlConstantName::SHA, CXmlConstantValue::SNMPV3_SHA),
														new CStringXmlTag('snmpv3_contextname'),
														new CStringXmlTag('snmpv3_privpassphrase'),
														(new CStringXmlTag('snmpv3_privprotocol'))
															->setDefaultValue(CXmlConstantValue::DES)
															->addConstant(CXmlConstantName::DES, CXmlConstantValue::DES)
															->addConstant(CXmlConstantName::AES, CXmlConstantValue::AES),
														(new CStringXmlTag('snmpv3_securitylevel'))
															->setDefaultValue(CXmlConstantValue::NOAUTHNOPRIV)
															->addConstant(CXmlConstantName::NOAUTHNOPRIV, CXmlConstantValue::NOAUTHNOPRIV)
															->addConstant(CXmlConstantName::AUTHNOPRIV, CXmlConstantValue::AUTHNOPRIV)
															->addConstant(CXmlConstantName::AUTHPRIV, CXmlConstantValue::AUTHPRIV),
														new CStringXmlTag('snmpv3_securityname'),
														new CStringXmlTag('ssl_cert_file'),
														new CStringXmlTag('ssl_key_file'),
														new CStringXmlTag('ssl_key_password'),
														(new CStringXmlTag('status'))
															->setDefaultValue(CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
														new CStringXmlTag('status_codes'),
														new CStringXmlTag('timeout'),
														(new CStringXmlTag('trends'))->setDefaultValue('365d'),
														(new CStringXmlTag('type'))
															->setDefaultValue(CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE)
															->addConstant(CXmlConstantName::ZABBIX_PASSIVE, CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE)
															->addConstant(CXmlConstantName::SNMPV1, CXmlConstantValue::ITEM_TYPE_SNMPV1)
															->addConstant(CXmlConstantName::TRAP, CXmlConstantValue::ITEM_TYPE_TRAP)
															->addConstant(CXmlConstantName::SIMPLE, CXmlConstantValue::ITEM_TYPE_SIMPLE)
															->addConstant(CXmlConstantName::SNMPV2, CXmlConstantValue::ITEM_TYPE_SNMPV2)
															->addConstant(CXmlConstantName::INTERNAL, CXmlConstantValue::ITEM_TYPE_INTERNAL)
															->addConstant(CXmlConstantName::SNMPV3, CXmlConstantValue::ITEM_TYPE_SNMPV3)
															->addConstant(CXmlConstantName::ZABBIX_ACTIVE, CXmlConstantValue::ITEM_TYPE_ZABBIX_ACTIVE)
															->addConstant(CXmlConstantName::AGGREGATE, CXmlConstantValue::ITEM_TYPE_AGGREGATE)
															->addConstant(CXmlConstantName::EXTERNAL, CXmlConstantValue::ITEM_TYPE_EXTERNAL)
															->addConstant(CXmlConstantName::ODBC, CXmlConstantValue::ITEM_TYPE_ODBC)
															->addConstant(CXmlConstantName::IPMI, CXmlConstantValue::ITEM_TYPE_IPMI)
															->addConstant(CXmlConstantName::SSH, CXmlConstantValue::ITEM_TYPE_SSH)
															->addConstant(CXmlConstantName::TELNET, CXmlConstantValue::ITEM_TYPE_TELNET)
															->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::ITEM_TYPE_CALCULATED)
															->addConstant(CXmlConstantName::JMX, CXmlConstantValue::ITEM_TYPE_JMX)
															->addConstant(CXmlConstantName::SNMP_TRAP, CXmlConstantValue::ITEM_TYPE_SNMP_TRAP)
															->addConstant(CXmlConstantName::DEPENDENT, CXmlConstantValue::ITEM_TYPE_DEPENDENT)
															->addConstant(CXmlConstantName::HTTP_AGENT, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT),
														new CStringXmlTag('units'),
														new CStringXmlTag('url'),
														new CStringXmlTag('username'),
														(new CStringXmlTag('value_type'))
															->setDefaultValue(CXmlConstantValue::UNSIGNED)
															->addConstant(CXmlConstantName::FLOAT, CXmlConstantValue::FLOAT)
															->addConstant(CXmlConstantName::CHAR, CXmlConstantValue::CHAR)
															->addConstant(CXmlConstantName::LOG, CXmlConstantValue::LOG)
															->addConstant(CXmlConstantName::UNSIGNED, CXmlConstantValue::UNSIGNED)
															->addConstant(CXmlConstantName::TEXT, CXmlConstantValue::TEXT),
														(new CArrayXmlTag('valuemap'))
															->setSchema(
																new CStringXmlTag('name')
															),
														(new CStringXmlTag('verify_host'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('verify_peer'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CIndexedArrayXmlTag('application_prototypes'))
															->setKey('applicationPrototypes')
															->setSchema(
																(new CArrayXmlTag('application_prototype'))
																	->setSchema(
																		(new CStringXmlTag('name'))->setRequired()
																	)
															),
														(new CIndexedArrayXmlTag('trigger_prototypes'))
															->setKey('triggerPrototypes')
															->setSchema(
																(new CArrayXmlTag('trigger_prototype'))
																	->setSchema(
																		(new CStringXmlTag('expression'))->setRequired(),
																		(new CStringXmlTag('name'))
																			->setRequired()
																			->setKey('description'),
																		(new CStringXmlTag('correlation_mode'))
																			->setDefaultValue(CXmlConstantValue::TRIGGER_DISABLED)
																			->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_DISABLED)
																			->addConstant(CXmlConstantName::TAG_VALUE, CXmlConstantValue::TRIGGER_TAG_VALUE),
																		new CStringXmlTag('correlation_tag'),
																		(new CIndexedArrayXmlTag('dependencies'))
																			->setSchema(
																				(new CArrayXmlTag('dependency'))
																					->setSchema(
																						(new CStringXmlTag('expression'))->setRequired(),
																						(new CStringXmlTag('name'))
																							->setRequired()
																							->setKey('description'),
																						new CStringXmlTag('recovery_expression')
																					)
																			),
																		(new CStringXmlTag('description'))->setKey('comments'),
																		(new CStringXmlTag('manual_close'))
																			->setDefaultValue(CXmlConstantValue::NO)
																			->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
																			->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
																		(new CStringXmlTag('priority'))
																			->setDefaultValue(CXmlConstantValue::NOT_CLASSIFIED)
																			->addConstant(CXmlConstantName::NOT_CLASSIFIED, CXmlConstantValue::NOT_CLASSIFIED)
																			->addConstant(CXmlConstantName::INFO, CXmlConstantValue::INFO)
																			->addConstant(CXmlConstantName::WARNING, CXmlConstantValue::WARNING)
																			->addConstant(CXmlConstantName::AVERAGE, CXmlConstantValue::AVERAGE)
																			->addConstant(CXmlConstantName::HIGH, CXmlConstantValue::HIGH)
																			->addConstant(CXmlConstantName::DISASTER, CXmlConstantValue::DISASTER),
																		new CStringXmlTag('recovery_expression'),
																		(new CStringXmlTag('recovery_mode'))
																			->setDefaultValue(CXmlConstantValue::TRIGGER_EXPRESSION)
																			->addConstant(CXmlConstantName::EXPRESSION, CXmlConstantValue::TRIGGER_EXPRESSION)
																			->addConstant(CXmlConstantName::RECOVERY_EXPRESSION, CXmlConstantValue::TRIGGER_RECOVERY_EXPRESSION)
																			->addConstant(CXmlConstantName::NONE, CXmlConstantValue::TRIGGER_NONE),
																		(new CStringXmlTag('status'))
																			->setDefaultValue(CXmlConstantValue::ENABLED)
																			->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
																			->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
																		(new CIndexedArrayXmlTag('tags'))
																			->setSchema(
																				(new CArrayXmlTag('tag'))
																					->setSchema(
																						(new CStringXmlTag('tag'))->setRequired(),
																						new CStringXmlTag('value')
																					)
																			),
																		(new CStringXmlTag('type'))
																			->setDefaultValue(CXmlConstantValue::SINGLE)
																			->addConstant(CXmlConstantName::SINGLE, CXmlConstantValue::SINGLE)
																			->addConstant(CXmlConstantName::MULTIPLE, CXmlConstantValue::MULTIPLE),
																		new CStringXmlTag('url')
																	)
															)
													)
											),
										new CStringXmlTag('jmx_endpoint'),
										(new CStringXmlTag('lifetime'))->setDefaultValue('30d'),
										(new CIndexedArrayXmlTag('lld_macro_paths'))
											->setSchema(
												(new CArrayXmlTag('lld_macro_path'))
													->setSchema(
														new CStringXmlTag('lld_macro'),
														new CStringXmlTag('path')
													)
											),
										(new CArrayXmlTag('master_item'))
											->setSchema(
												(new CStringXmlTag('key'))->setRequired()
											),
										new CStringXmlTag('params'),
										new CStringXmlTag('password'),
										new CStringXmlTag('port'),
										(new CStringXmlTag('post_type'))
											->setDefaultValue(CXmlConstantValue::RAW)
											->addConstant(CXmlConstantName::RAW, CXmlConstantValue::RAW)
											->addConstant(CXmlConstantName::JSON, CXmlConstantValue::JSON)
											->addConstant(CXmlConstantName::XML, CXmlConstantValue::XML),
										new CStringXmlTag('posts'),
										(new CIndexedArrayXmlTag('preprocessing'))
											->setSchema(
												(new CArrayXmlTag('step'))
													->setSchema(
														(new CStringXmlTag('params'))->setRequired(),
														(new CStringXmlTag('type'))
															->setRequired()
															->addConstant(CXmlConstantName::MULTIPLIER, CXmlConstantValue::MULTIPLIER)
															->addConstant(CXmlConstantName::RTRIM, CXmlConstantValue::RTRIM)
															->addConstant(CXmlConstantName::LTRIM, CXmlConstantValue::LTRIM)
															->addConstant(CXmlConstantName::TRIM, CXmlConstantValue::TRIM)
															->addConstant(CXmlConstantName::REGEX, CXmlConstantValue::REGEX)
															->addConstant(CXmlConstantName::BOOL_TO_DECIMAL, CXmlConstantValue::BOOL_TO_DECIMAL)
															->addConstant(CXmlConstantName::OCTAL_TO_DECIMAL, CXmlConstantValue::OCTAL_TO_DECIMAL)
															->addConstant(CXmlConstantName::HEX_TO_DECIMAL, CXmlConstantValue::HEX_TO_DECIMAL)
															->addConstant(CXmlConstantName::SIMPLE_CHANGE, CXmlConstantValue::SIMPLE_CHANGE)
															->addConstant(CXmlConstantName::CHANGE_PER_SECOND, CXmlConstantValue::CHANGE_PER_SECOND)
															->addConstant(CXmlConstantName::XMLPATH, CXmlConstantValue::XMLPATH)
															->addConstant(CXmlConstantName::JSONPATH, CXmlConstantValue::JSONPATH)
															->addConstant(CXmlConstantName::IN_RANGE, CXmlConstantValue::IN_RANGE)
															->addConstant(CXmlConstantName::MATCHES_REGEX, CXmlConstantValue::MATCHES_REGEX)
															->addConstant(CXmlConstantName::NOT_MATCHES_REGEX, CXmlConstantValue::NOT_MATCHES_REGEX)
															->addConstant(CXmlConstantName::CHECK_JSON_ERROR, CXmlConstantValue::CHECK_JSON_ERROR)
															->addConstant(CXmlConstantName::CHECK_XML_ERROR, CXmlConstantValue::CHECK_XML_ERROR)
															->addConstant(CXmlConstantName::CHECK_REGEX_ERROR, CXmlConstantValue::CHECK_REGEX_ERROR)
															->addConstant(CXmlConstantName::DISCARD_UNCHANGED, CXmlConstantValue::DISCARD_UNCHANGED)
															->addConstant(CXmlConstantName::DISCARD_UNCHANGED_HEARTBEAT, CXmlConstantValue::DISCARD_UNCHANGED_HEARTBEAT)
															->addConstant(CXmlConstantName::JAVASCRIPT, CXmlConstantValue::JAVASCRIPT)
															->addConstant(CXmlConstantName::PROMETHEUS_PATTERN, CXmlConstantValue::PROMETHEUS_PATTERN)
															->addConstant(CXmlConstantName::PROMETHEUS_TO_JSON, CXmlConstantValue::PROMETHEUS_TO_JSON),
														(new CStringXmlTag('error_handler'))
															->setDefaultValue(CXmlConstantValue::ORIGINAL_ERROR)
															->addConstant(CXmlConstantName::ORIGINAL_ERROR, CXmlConstantValue::ORIGINAL_ERROR)
															->addConstant(CXmlConstantName::DISCARD_VALUE, CXmlConstantValue::DISCARD_VALUE)
															->addConstant(CXmlConstantName::CUSTOM_VALUE, CXmlConstantValue::CUSTOM_VALUE)
															->addConstant(CXmlConstantName::CUSTOM_ERROR, CXmlConstantValue::CUSTOM_ERROR),
														new CStringXmlTag('error_handler_params')
													)
											),
										new CStringXmlTag('privatekey'),
										new CStringXmlTag('publickey'),
										(new CIndexedArrayXmlTag('query_fields'))
											->setSchema(
												(new CArrayXmlTag('query_field'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired(),
														new CStringXmlTag('value')
													)
											),
										(new CStringXmlTag('request_method'))
											->setDefaultValue(CXmlConstantValue::GET)
											->addConstant(CXmlConstantName::GET, CXmlConstantValue::GET)
											->addConstant(CXmlConstantName::POST, CXmlConstantValue::POST)
											->addConstant(CXmlConstantName::PUT, CXmlConstantValue::PUT)
											->addConstant(CXmlConstantName::HEAD, CXmlConstantValue::HEAD),
										(new CStringXmlTag('retrieve_mode'))
											->setDefaultValue(CXmlConstantValue::BODY)
											->addConstant(CXmlConstantName::BODY, CXmlConstantValue::BODY)
											->addConstant(CXmlConstantName::HEADERS, CXmlConstantValue::HEADERS)
											->addConstant(CXmlConstantName::BOTH, CXmlConstantValue::BOTH),
										new CStringXmlTag('snmp_community'),
										new CStringXmlTag('snmp_oid'),
										new CStringXmlTag('snmpv3_authpassphrase'),
										(new CStringXmlTag('snmpv3_authprotocol'))
											->setDefaultValue(CXmlConstantValue::SNMPV3_MD5)
											->addConstant(CXmlConstantName::MD5, CXmlConstantValue::SNMPV3_MD5)
											->addConstant(CXmlConstantName::SHA, CXmlConstantValue::SNMPV3_SHA),
										new CStringXmlTag('snmpv3_contextname'),
										new CStringXmlTag('snmpv3_privpassphrase'),
										(new CStringXmlTag('snmpv3_privprotocol'))
											->setDefaultValue(CXmlConstantValue::DES)
											->addConstant(CXmlConstantName::DES, CXmlConstantValue::DES)
											->addConstant(CXmlConstantName::AES, CXmlConstantValue::AES),
										(new CStringXmlTag('snmpv3_securitylevel'))
											->setDefaultValue(CXmlConstantValue::NOAUTHNOPRIV)
											->addConstant(CXmlConstantName::NOAUTHNOPRIV, CXmlConstantValue::NOAUTHNOPRIV)
											->addConstant(CXmlConstantName::AUTHNOPRIV, CXmlConstantValue::AUTHNOPRIV)
											->addConstant(CXmlConstantName::AUTHPRIV, CXmlConstantValue::AUTHPRIV),
										new CStringXmlTag('snmpv3_securityname'),
										new CStringXmlTag('ssl_cert_file'),
										new CStringXmlTag('ssl_key_file'),
										new CStringXmlTag('ssl_key_password'),
										(new CStringXmlTag('status'))
											->setDefaultValue(CXmlConstantValue::ENABLED)
											->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
											->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
										new CStringXmlTag('status_codes'),
										new CStringXmlTag('timeout'),
										(new CIndexedArrayXmlTag('trigger_prototypes'))
											->setKey('triggerPrototypes')
											->setSchema(
												(new CArrayXmlTag('trigger_prototype'))
													->setSchema(
														(new CStringXmlTag('expression'))->setRequired(),
														(new CStringXmlTag('name'))
															->setRequired()
															->setKey('description'),
														(new CStringXmlTag('correlation_mode'))
															->setDefaultValue(CXmlConstantValue::TRIGGER_DISABLED)
															->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_DISABLED)
															->addConstant(CXmlConstantName::TAG_VALUE, CXmlConstantValue::TRIGGER_TAG_VALUE),
														new CStringXmlTag('correlation_tag'),
														(new CIndexedArrayXmlTag('dependencies'))
															->setSchema(
																(new CArrayXmlTag('dependency'))
																	->setSchema(
																		(new CStringXmlTag('expression'))->setRequired(),
																		(new CStringXmlTag('name'))
																			->setRequired()
																			->setKey('description'),
																		new CStringXmlTag('recovery_expression')
																	)
															),
														(new CStringXmlTag('description'))->setKey('comments'),
														(new CStringXmlTag('manual_close'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('priority'))
															->setDefaultValue(CXmlConstantValue::NOT_CLASSIFIED)
															->addConstant(CXmlConstantName::NOT_CLASSIFIED, CXmlConstantValue::NOT_CLASSIFIED)
															->addConstant(CXmlConstantName::INFO, CXmlConstantValue::INFO)
															->addConstant(CXmlConstantName::WARNING, CXmlConstantValue::WARNING)
															->addConstant(CXmlConstantName::AVERAGE, CXmlConstantValue::AVERAGE)
															->addConstant(CXmlConstantName::HIGH, CXmlConstantValue::HIGH)
															->addConstant(CXmlConstantName::DISASTER, CXmlConstantValue::DISASTER),
														new CStringXmlTag('recovery_expression'),
														(new CStringXmlTag('recovery_mode'))
															->setDefaultValue(CXmlConstantValue::TRIGGER_EXPRESSION)
															->addConstant(CXmlConstantName::EXPRESSION, CXmlConstantValue::TRIGGER_EXPRESSION)
															->addConstant(CXmlConstantName::RECOVERY_EXPRESSION, CXmlConstantValue::TRIGGER_RECOVERY_EXPRESSION)
															->addConstant(CXmlConstantName::NONE, CXmlConstantValue::TRIGGER_NONE),
														(new CStringXmlTag('status'))
															->setDefaultValue(CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
														(new CIndexedArrayXmlTag('tags'))
															->setSchema(
																(new CArrayXmlTag('tag'))
																	->setSchema(
																		(new CStringXmlTag('tag'))->setRequired(),
																		new CStringXmlTag('value')
																	)
															),
														(new CStringXmlTag('type'))
															->setDefaultValue(CXmlConstantValue::SINGLE)
															->addConstant(CXmlConstantName::SINGLE, CXmlConstantValue::SINGLE)
															->addConstant(CXmlConstantName::MULTIPLE, CXmlConstantValue::MULTIPLE),
														new CStringXmlTag('url')
													)
											),
										(new CStringXmlTag('type'))
											->setDefaultValue(CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE)
											->addConstant(CXmlConstantName::ZABBIX_PASSIVE, CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE)
											->addConstant(CXmlConstantName::SNMPV1, CXmlConstantValue::ITEM_TYPE_SNMPV1)
											->addConstant(CXmlConstantName::TRAP, CXmlConstantValue::ITEM_TYPE_TRAP)
											->addConstant(CXmlConstantName::SIMPLE, CXmlConstantValue::ITEM_TYPE_SIMPLE)
											->addConstant(CXmlConstantName::SNMPV2, CXmlConstantValue::ITEM_TYPE_SNMPV2)
											->addConstant(CXmlConstantName::INTERNAL, CXmlConstantValue::ITEM_TYPE_INTERNAL)
											->addConstant(CXmlConstantName::SNMPV3, CXmlConstantValue::ITEM_TYPE_SNMPV3)
											->addConstant(CXmlConstantName::ZABBIX_ACTIVE, CXmlConstantValue::ITEM_TYPE_ZABBIX_ACTIVE)
											->addConstant(CXmlConstantName::AGGREGATE, CXmlConstantValue::ITEM_TYPE_AGGREGATE)
											->addConstant(CXmlConstantName::EXTERNAL, CXmlConstantValue::ITEM_TYPE_EXTERNAL)
											->addConstant(CXmlConstantName::ODBC, CXmlConstantValue::ITEM_TYPE_ODBC)
											->addConstant(CXmlConstantName::IPMI, CXmlConstantValue::ITEM_TYPE_IPMI)
											->addConstant(CXmlConstantName::SSH, CXmlConstantValue::ITEM_TYPE_SSH)
											->addConstant(CXmlConstantName::TELNET, CXmlConstantValue::ITEM_TYPE_TELNET)
											->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::ITEM_TYPE_CALCULATED)
											->addConstant(CXmlConstantName::JMX, CXmlConstantValue::ITEM_TYPE_JMX)
											->addConstant(CXmlConstantName::SNMP_TRAP, CXmlConstantValue::ITEM_TYPE_SNMP_TRAP)
											->addConstant(CXmlConstantName::DEPENDENT, CXmlConstantValue::ITEM_TYPE_DEPENDENT)
											->addConstant(CXmlConstantName::HTTP_AGENT, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT),
										new CStringXmlTag('url'),
										new CStringXmlTag('username'),
										(new CStringXmlTag('verify_host'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CStringXmlTag('verify_peer'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES)
									)
							),
						(new CIndexedArrayXmlTag('groups'))
							->setSchema(
								(new CArrayXmlTag('group'))
									->setSchema(
										(new CStringXmlTag('name'))->setRequired()
									)
							),
						(new CIndexedArrayXmlTag('httptests'))
							->setSchema(
								(new CArrayXmlTag('httptest'))
									->setSchema(
										(new CStringXmlTag('name'))->setRequired(),
										(new CIndexedArrayXmlTag('steps'))
											->setRequired()
											->setSchema(
												(new CArrayXmlTag('step'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired(),
														(new CStringXmlTag('url'))->setRequired(),
														(new CStringXmlTag('follow_redirects'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														new CStringXmlTag('headers'),
														new CStringXmlTag('posts'),
														new CStringXmlTag('query_fields'),
														new CStringXmlTag('required'),
														(new CStringXmlTag('retrieve_mode'))
															->setDefaultValue(CXmlConstantValue::BODY)
															->addConstant(CXmlConstantName::BODY, CXmlConstantValue::BODY)
															->addConstant(CXmlConstantName::HEADERS, CXmlConstantValue::HEADERS)
															->addConstant(CXmlConstantName::BOTH, CXmlConstantValue::BOTH),
														new CStringXmlTag('status_codes'),
														(new CStringXmlTag('timeout'))->setDefaultValue('15s'),
														(new CStringXmlTag('variables'))
													)
											),
										(new CStringXmlTag('agent'))->setDefaultValue('Zabbix'),
										(new CArrayXmlTag('application'))
											->setSchema(
												(new CStringXmlTag('name'))->setRequired()
											),
										(new CStringXmlTag('attempts'))
											->setKey('retries')
											->setDefaultValue('1'),
										(new CStringXmlTag('authentication'))
											->setDefaultValue(CXmlConstantValue::NONE)
											->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE)
											->addConstant(CXmlConstantName::BASIC, CXmlConstantValue::BASIC)
											->addConstant(CXmlConstantName::NTLM, CXmlConstantValue::NTLM),
										(new CStringXmlTag('delay'))->setDefaultValue('1m'),
										new CStringXmlTag('headers'),
										new CStringXmlTag('http_password'),
										new CStringXmlTag('http_proxy'),
										new CStringXmlTag('http_user'),
										new CStringXmlTag('ssl_cert_file'),
										new CStringXmlTag('ssl_key_file'),
										new CStringXmlTag('ssl_key_password'),
										(new CStringXmlTag('status'))
											->setDefaultValue(CXmlConstantValue::ENABLED)
											->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
											->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
										new CStringXmlTag('variables'),
										(new CStringXmlTag('verify_host'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CStringXmlTag('verify_peer'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES)
									)
							),
						(new CIndexedArrayXmlTag('interfaces'))
							->setSchema(
								(new CArrayXmlTag('interface'))
									->setSchema(
										(new CStringXmlTag('interface_ref'))->setRequired(),
										(new CStringXmlTag('bulk'))
											->setDefaultValue(CXmlConstantValue::YES)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CStringXmlTag('default'))
											->setKey('main')
											->setDefaultValue(CXmlConstantValue::YES)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										new CStringXmlTag('dns'),
										new CStringXmlTag('ip'),
										new CStringXmlTag('port'),
										(new CStringXmlTag('type'))
											->setDefaultValue(CXmlConstantValue::ZABBIX)
											->addConstant(CXmlConstantName::ZABBIX, CXmlConstantValue::ZABBIX)
											->addConstant(CXmlConstantName::SNMP, CXmlConstantValue::SNMP)
											->addConstant(CXmlConstantName::IPMI, CXmlConstantValue::IPMI)
											->addConstant(CXmlConstantName::JMX, CXmlConstantValue::JMX),
										(new CStringXmlTag('useip'))
											->setDefaultValue(CXmlConstantValue::YES)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES)
									)
							),
						(new CArrayXmlTag('inventory'))
							->setSchema(
								new CStringXmlTag('type'),
								new CStringXmlTag('type_full'),
								new CStringXmlTag('name'),
								new CStringXmlTag('alias'),
								new CStringXmlTag('os'),
								new CStringXmlTag('os_full'),
								new CStringXmlTag('os_short'),
								new CStringXmlTag('serialno_a'),
								new CStringXmlTag('serialno_b'),
								new CStringXmlTag('tag'),
								new CStringXmlTag('asset_tag'),
								new CStringXmlTag('macaddress_a'),
								new CStringXmlTag('macaddress_b'),
								new CStringXmlTag('hardware'),
								new CStringXmlTag('hardware_full'),
								new CStringXmlTag('software'),
								new CStringXmlTag('software_full'),
								new CStringXmlTag('software_app_a'),
								new CStringXmlTag('software_app_b'),
								new CStringXmlTag('software_app_c'),
								new CStringXmlTag('software_app_d'),
								new CStringXmlTag('software_app_e'),
								new CStringXmlTag('contact'),
								new CStringXmlTag('location'),
								new CStringXmlTag('location_lat'),
								new CStringXmlTag('location_lon'),
								new CStringXmlTag('notes'),
								new CStringXmlTag('chassis'),
								new CStringXmlTag('model'),
								new CStringXmlTag('hw_arch'),
								new CStringXmlTag('vendor'),
								new CStringXmlTag('contract_number'),
								new CStringXmlTag('installer_name'),
								new CStringXmlTag('deployment_status'),
								new CStringXmlTag('url_a'),
								new CStringXmlTag('url_b'),
								new CStringXmlTag('url_c'),
								new CStringXmlTag('host_networks'),
								new CStringXmlTag('host_netmask'),
								new CStringXmlTag('host_router'),
								new CStringXmlTag('oob_ip'),
								new CStringXmlTag('oob_netmask'),
								new CStringXmlTag('oob_router'),
								new CStringXmlTag('date_hw_purchase'),
								new CStringXmlTag('date_hw_install'),
								new CStringXmlTag('date_hw_expiry'),
								new CStringXmlTag('date_hw_decomm'),
								new CStringXmlTag('site_address_a'),
								new CStringXmlTag('site_address_b'),
								new CStringXmlTag('site_address_c'),
								new CStringXmlTag('site_city'),
								new CStringXmlTag('site_state'),
								new CStringXmlTag('site_country'),
								new CStringXmlTag('site_zip'),
								new CStringXmlTag('site_rack'),
								new CStringXmlTag('site_notes'),
								new CStringXmlTag('poc_1_name'),
								new CStringXmlTag('poc_1_email'),
								new CStringXmlTag('poc_1_phone_a'),
								new CStringXmlTag('poc_1_phone_b'),
								new CStringXmlTag('poc_1_cell'),
								new CStringXmlTag('poc_1_screen'),
								new CStringXmlTag('poc_1_notes'),
								new CStringXmlTag('poc_2_name'),
								new CStringXmlTag('poc_2_email'),
								new CStringXmlTag('poc_2_phone_a'),
								new CStringXmlTag('poc_2_phone_b'),
								new CStringXmlTag('poc_2_cell'),
								new CStringXmlTag('poc_2_screen'),
								new CStringXmlTag('poc_2_notes')
							),
						(new CStringXmlTag('inventory_mode'))
							->setDefaultValue(CXmlConstantValue::INV_MODE_MANUAL)
							->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::INV_MODE_DISABLED)
							->addConstant(CXmlConstantName::MANUAL, CXmlConstantValue::INV_MODE_MANUAL)
							->addConstant(CXmlConstantName::AUTOMATIC, CXmlConstantValue::INV_MODE_AUTOMATIC),
						(new CStringXmlTag('ipmi_authtype'))
							->setDefaultValue(CXmlConstantValue::XML_DEFAULT)
							->addConstant(CXmlConstantName::XML_DEFAULT, CXmlConstantValue::XML_DEFAULT)
							->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE)
							->addConstant(CXmlConstantName::MD2, CXmlConstantValue::MD2)
							->addConstant(CXmlConstantName::MD5, CXmlConstantValue::MD5)
							->addConstant(CXmlConstantName::STRAIGHT, CXmlConstantValue::STRAIGHT)
							->addConstant(CXmlConstantName::OEM, CXmlConstantValue::OEM)
							->addConstant(CXmlConstantName::RMCP_PLUS, CXmlConstantValue::RMCP_PLUS),
						new CStringXmlTag('ipmi_password'),
						(new CStringXmlTag('ipmi_privilege'))
							->setDefaultValue(CXmlConstantValue::USER)
							->addConstant(CXmlConstantName::CALLBACK, CXmlConstantValue::CALLBACK)
							->addConstant(CXmlConstantName::USER, CXmlConstantValue::USER)
							->addConstant(CXmlConstantName::OPERATOR, CXmlConstantValue::OPERATOR)
							->addConstant(CXmlConstantName::ADMIN, CXmlConstantValue::ADMIN)
							->addConstant(CXmlConstantName::OEM, CXmlConstantValue::OEM),
						new CStringXmlTag('ipmi_username'),
						(new CIndexedArrayXmlTag('items'))
							->setSchema(
								(new CArrayXmlTag('item'))
									->setSchema(
										(new CStringXmlTag('key'))
											->setRequired()
											->setKey('key_'),
										(new CStringXmlTag('name'))->setRequired(),
										(new CStringXmlTag('allow_traps'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CStringXmlTag('allowed_hosts'))->setKey('trapper_hosts'),
										(new CIndexedArrayXmlTag('applications'))
											->setSchema(
												(new CArrayXmlTag('application'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired()
													)
											),
										(new CStringXmlTag('authtype'))
											->setDefaultValue('0')
											->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
											->addConstant(CXmlConstantName::BASIC, CXmlConstantValue::BASIC, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
											->addConstant(CXmlConstantName::NTLM, CXmlConstantValue::NTLM, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT)
											->addConstant(CXmlConstantName::PASSWORD, CXmlConstantValue::PASSWORD, CXmlConstantValue::ITEM_TYPE_SSH)
											->addConstant(CXmlConstantName::PUBLIC_KEY, CXmlConstantValue::PUBLIC_KEY, CXmlConstantValue::ITEM_TYPE_SSH)
											->setExportHandler(function(array $data, CXmlTagInterface $class) {
												return $class->getConstantByValue($data['authtype'], $data['type']);
											})
											->setImportHandler(function(array $data, CXmlTagInterface $class) {
												if (!array_key_exists('authtype', $data)) {
													return (string) CXmlConstantValue::NONE;
												}

												$type = ($data['type'] === CXmlConstantName::HTTP_AGENT
													? CXmlConstantValue::ITEM_TYPE_HTTP_AGENT
													: CXmlConstantValue::ITEM_TYPE_SSH);
												return (string) $class->getConstantValueByName($data['authtype'], $type);
											}),
										(new CStringXmlTag('delay'))->setDefaultValue('1m'),
										new CStringXmlTag('description'),
										(new CStringXmlTag('follow_redirects'))
											->setDefaultValue(CXmlConstantValue::YES)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CIndexedArrayXmlTag('headers'))
											->setSchema(
												(new CArrayXmlTag('header'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired(),
														(new CStringXmlTag('value'))->setRequired()
													)
											),
										(new CStringXmlTag('history'))->setDefaultValue('90d'),
										new CStringXmlTag('http_proxy'),
										new CStringXmlTag('interface_ref'),
										(new CStringXmlTag('inventory_link'))
											->setDefaultValue(CXmlConstantValue::NONE)
											->addConstant(CXmlConstantName::NONE, CXmlConstantValue::NONE)
											->addConstant(CXmlConstantName::ALIAS, CXmlConstantValue::ALIAS)
											->addConstant(CXmlConstantName::ASSET_TAG, CXmlConstantValue::ASSET_TAG)
											->addConstant(CXmlConstantName::CHASSIS, CXmlConstantValue::CHASSIS)
											->addConstant(CXmlConstantName::CONTACT, CXmlConstantValue::CONTACT)
											->addConstant(CXmlConstantName::CONTRACT_NUMBER, CXmlConstantValue::CONTRACT_NUMBER)
											->addConstant(CXmlConstantName::DATE_HW_DECOMM, CXmlConstantValue::DATE_HW_DECOMM)
											->addConstant(CXmlConstantName::DATE_HW_EXPIRY, CXmlConstantValue::DATE_HW_EXPIRY)
											->addConstant(CXmlConstantName::DATE_HW_INSTALL, CXmlConstantValue::DATE_HW_INSTALL)
											->addConstant(CXmlConstantName::DATE_HW_PURCHASE, CXmlConstantValue::DATE_HW_PURCHASE)
											->addConstant(CXmlConstantName::DEPLOYMENT_STATUS, CXmlConstantValue::DEPLOYMENT_STATUS)
											->addConstant(CXmlConstantName::HARDWARE, CXmlConstantValue::HARDWARE)
											->addConstant(CXmlConstantName::HARDWARE_FULL, CXmlConstantValue::HARDWARE_FULL)
											->addConstant(CXmlConstantName::HOST_NETMASK, CXmlConstantValue::HOST_NETMASK)
											->addConstant(CXmlConstantName::HOST_NETWORKS, CXmlConstantValue::HOST_NETWORKS)
											->addConstant(CXmlConstantName::HOST_ROUTER, CXmlConstantValue::HOST_ROUTER)
											->addConstant(CXmlConstantName::HW_ARCH, CXmlConstantValue::HW_ARCH)
											->addConstant(CXmlConstantName::INSTALLER_NAME, CXmlConstantValue::INSTALLER_NAME)
											->addConstant(CXmlConstantName::LOCATION, CXmlConstantValue::LOCATION)
											->addConstant(CXmlConstantName::LOCATION_LAT, CXmlConstantValue::LOCATION_LAT)
											->addConstant(CXmlConstantName::LOCATION_LON, CXmlConstantValue::LOCATION_LON)
											->addConstant(CXmlConstantName::MACADDRESS_A, CXmlConstantValue::MACADDRESS_A)
											->addConstant(CXmlConstantName::MACADDRESS_B, CXmlConstantValue::MACADDRESS_B)
											->addConstant(CXmlConstantName::MODEL, CXmlConstantValue::MODEL)
											->addConstant(CXmlConstantName::NAME, CXmlConstantValue::NAME)
											->addConstant(CXmlConstantName::NOTES, CXmlConstantValue::NOTES)
											->addConstant(CXmlConstantName::OOB_IP, CXmlConstantValue::OOB_IP)
											->addConstant(CXmlConstantName::OOB_NETMASK, CXmlConstantValue::OOB_NETMASK)
											->addConstant(CXmlConstantName::OOB_ROUTER, CXmlConstantValue::OOB_ROUTER)
											->addConstant(CXmlConstantName::OS, CXmlConstantValue::OS)
											->addConstant(CXmlConstantName::OS_FULL, CXmlConstantValue::OS_FULL)
											->addConstant(CXmlConstantName::OS_SHORT, CXmlConstantValue::OS_SHORT)
											->addConstant(CXmlConstantName::POC_1_CELL, CXmlConstantValue::POC_1_CELL)
											->addConstant(CXmlConstantName::POC_1_EMAIL, CXmlConstantValue::POC_1_EMAIL)
											->addConstant(CXmlConstantName::POC_1_NAME, CXmlConstantValue::POC_1_NAME)
											->addConstant(CXmlConstantName::POC_1_NOTES, CXmlConstantValue::POC_1_NOTES)
											->addConstant(CXmlConstantName::POC_1_PHONE_A, CXmlConstantValue::POC_1_PHONE_A)
											->addConstant(CXmlConstantName::POC_1_PHONE_B, CXmlConstantValue::POC_1_PHONE_B)
											->addConstant(CXmlConstantName::POC_1_SCREEN, CXmlConstantValue::POC_1_SCREEN)
											->addConstant(CXmlConstantName::POC_2_CELL, CXmlConstantValue::POC_2_CELL)
											->addConstant(CXmlConstantName::POC_2_EMAIL, CXmlConstantValue::POC_2_EMAIL)
											->addConstant(CXmlConstantName::POC_2_NAME, CXmlConstantValue::POC_2_NAME)
											->addConstant(CXmlConstantName::POC_2_NOTES, CXmlConstantValue::POC_2_NOTES)
											->addConstant(CXmlConstantName::POC_2_PHONE_A, CXmlConstantValue::POC_2_PHONE_A)
											->addConstant(CXmlConstantName::POC_2_PHONE_B, CXmlConstantValue::POC_2_PHONE_B)
											->addConstant(CXmlConstantName::POC_2_SCREEN, CXmlConstantValue::POC_2_SCREEN)
											->addConstant(CXmlConstantName::SERIALNO_A, CXmlConstantValue::SERIALNO_A)
											->addConstant(CXmlConstantName::SERIALNO_B, CXmlConstantValue::SERIALNO_B)
											->addConstant(CXmlConstantName::SITE_ADDRESS_A, CXmlConstantValue::SITE_ADDRESS_A)
											->addConstant(CXmlConstantName::SITE_ADDRESS_B, CXmlConstantValue::SITE_ADDRESS_B)
											->addConstant(CXmlConstantName::SITE_ADDRESS_C, CXmlConstantValue::SITE_ADDRESS_C)
											->addConstant(CXmlConstantName::SITE_CITY, CXmlConstantValue::SITE_CITY)
											->addConstant(CXmlConstantName::SITE_COUNTRY, CXmlConstantValue::SITE_COUNTRY)
											->addConstant(CXmlConstantName::SITE_NOTES, CXmlConstantValue::SITE_NOTES)
											->addConstant(CXmlConstantName::SITE_RACK, CXmlConstantValue::SITE_RACK)
											->addConstant(CXmlConstantName::SITE_STATE, CXmlConstantValue::SITE_STATE)
											->addConstant(CXmlConstantName::SITE_ZIP, CXmlConstantValue::SITE_ZIP)
											->addConstant(CXmlConstantName::SOFTWARE, CXmlConstantValue::SOFTWARE)
											->addConstant(CXmlConstantName::SOFTWARE_APP_A, CXmlConstantValue::SOFTWARE_APP_A)
											->addConstant(CXmlConstantName::SOFTWARE_APP_B, CXmlConstantValue::SOFTWARE_APP_B)
											->addConstant(CXmlConstantName::SOFTWARE_APP_C, CXmlConstantValue::SOFTWARE_APP_C)
											->addConstant(CXmlConstantName::SOFTWARE_APP_D, CXmlConstantValue::SOFTWARE_APP_D)
											->addConstant(CXmlConstantName::SOFTWARE_APP_E, CXmlConstantValue::SOFTWARE_APP_E)
											->addConstant(CXmlConstantName::SOFTWARE_FULL, CXmlConstantValue::SOFTWARE_FULL)
											->addConstant(CXmlConstantName::TAG, CXmlConstantValue::TAG)
											->addConstant(CXmlConstantName::TYPE, CXmlConstantValue::TYPE)
											->addConstant(CXmlConstantName::TYPE_FULL, CXmlConstantValue::TYPE_FULL)
											->addConstant(CXmlConstantName::URL_A, CXmlConstantValue::URL_A)
											->addConstant(CXmlConstantName::URL_B, CXmlConstantValue::URL_B)
											->addConstant(CXmlConstantName::URL_C, CXmlConstantValue::URL_C)
											->addConstant(CXmlConstantName::VENDOR, CXmlConstantValue::VENDOR),
										new CStringXmlTag('ipmi_sensor'),
										new CStringXmlTag('jmx_endpoint'),
										new CStringXmlTag('logtimefmt'),
										(new CArrayXmlTag('master_item'))
											->setSchema(
												(new CStringXmlTag('key'))->setRequired()
											),
										(new CStringXmlTag('output_format'))
											->setDefaultValue(CXmlConstantValue::RAW)
											->addConstant(CXmlConstantName::RAW, CXmlConstantValue::RAW)
											->addConstant(CXmlConstantName::JSON, CXmlConstantValue::JSON),
										new CStringXmlTag('params'),
										new CStringXmlTag('password'),
										new CStringXmlTag('port'),
										(new CStringXmlTag('post_type'))
											->setDefaultValue(CXmlConstantValue::RAW)
											->addConstant(CXmlConstantName::RAW, CXmlConstantValue::RAW)
											->addConstant(CXmlConstantName::JSON, CXmlConstantValue::JSON)
											->addConstant(CXmlConstantName::XML, CXmlConstantValue::XML),
										new CStringXmlTag('posts'),
										(new CIndexedArrayXmlTag('preprocessing'))
											->setSchema(
												(new CArrayXmlTag('step'))
													->setSchema(
														(new CStringXmlTag('params'))->setRequired(),
														(new CStringXmlTag('type'))
															->setRequired()
															->addConstant(CXmlConstantName::MULTIPLIER, CXmlConstantValue::MULTIPLIER)
															->addConstant(CXmlConstantName::RTRIM, CXmlConstantValue::RTRIM)
															->addConstant(CXmlConstantName::LTRIM, CXmlConstantValue::LTRIM)
															->addConstant(CXmlConstantName::TRIM, CXmlConstantValue::TRIM)
															->addConstant(CXmlConstantName::REGEX, CXmlConstantValue::REGEX)
															->addConstant(CXmlConstantName::BOOL_TO_DECIMAL, CXmlConstantValue::BOOL_TO_DECIMAL)
															->addConstant(CXmlConstantName::OCTAL_TO_DECIMAL, CXmlConstantValue::OCTAL_TO_DECIMAL)
															->addConstant(CXmlConstantName::HEX_TO_DECIMAL, CXmlConstantValue::HEX_TO_DECIMAL)
															->addConstant(CXmlConstantName::SIMPLE_CHANGE, CXmlConstantValue::SIMPLE_CHANGE)
															->addConstant(CXmlConstantName::CHANGE_PER_SECOND, CXmlConstantValue::CHANGE_PER_SECOND)
															->addConstant(CXmlConstantName::XMLPATH, CXmlConstantValue::XMLPATH)
															->addConstant(CXmlConstantName::JSONPATH, CXmlConstantValue::JSONPATH)
															->addConstant(CXmlConstantName::IN_RANGE, CXmlConstantValue::IN_RANGE)
															->addConstant(CXmlConstantName::MATCHES_REGEX, CXmlConstantValue::MATCHES_REGEX)
															->addConstant(CXmlConstantName::NOT_MATCHES_REGEX, CXmlConstantValue::NOT_MATCHES_REGEX)
															->addConstant(CXmlConstantName::CHECK_JSON_ERROR, CXmlConstantValue::CHECK_JSON_ERROR)
															->addConstant(CXmlConstantName::CHECK_XML_ERROR, CXmlConstantValue::CHECK_XML_ERROR)
															->addConstant(CXmlConstantName::CHECK_REGEX_ERROR, CXmlConstantValue::CHECK_REGEX_ERROR)
															->addConstant(CXmlConstantName::DISCARD_UNCHANGED, CXmlConstantValue::DISCARD_UNCHANGED)
															->addConstant(CXmlConstantName::DISCARD_UNCHANGED_HEARTBEAT, CXmlConstantValue::DISCARD_UNCHANGED_HEARTBEAT)
															->addConstant(CXmlConstantName::JAVASCRIPT, CXmlConstantValue::JAVASCRIPT)
															->addConstant(CXmlConstantName::PROMETHEUS_PATTERN, CXmlConstantValue::PROMETHEUS_PATTERN)
															->addConstant(CXmlConstantName::PROMETHEUS_TO_JSON, CXmlConstantValue::PROMETHEUS_TO_JSON),
														(new CStringXmlTag('error_handler'))
															->setDefaultValue(CXmlConstantValue::ORIGINAL_ERROR)
															->addConstant(CXmlConstantName::ORIGINAL_ERROR, CXmlConstantValue::ORIGINAL_ERROR)
															->addConstant(CXmlConstantName::DISCARD_VALUE, CXmlConstantValue::DISCARD_VALUE)
															->addConstant(CXmlConstantName::CUSTOM_VALUE, CXmlConstantValue::CUSTOM_VALUE)
															->addConstant(CXmlConstantName::CUSTOM_ERROR, CXmlConstantValue::CUSTOM_ERROR),
														new CStringXmlTag('error_handler_params')
													)
											),
										new CStringXmlTag('privatekey'),
										new CStringXmlTag('publickey'),
										(new CIndexedArrayXmlTag('query_fields'))
											->setSchema(
												(new CArrayXmlTag('query_field'))
													->setSchema(
														(new CStringXmlTag('name'))->setRequired(),
														new CStringXmlTag('value')
													)
											),
										(new CStringXmlTag('request_method'))
											->setDefaultValue(CXmlConstantValue::GET)
											->addConstant(CXmlConstantName::GET, CXmlConstantValue::GET)
											->addConstant(CXmlConstantName::POST, CXmlConstantValue::POST)
											->addConstant(CXmlConstantName::PUT, CXmlConstantValue::PUT)
											->addConstant(CXmlConstantName::HEAD, CXmlConstantValue::HEAD),
										(new CStringXmlTag('retrieve_mode'))
											->setDefaultValue(CXmlConstantValue::BODY)
											->addConstant(CXmlConstantName::BODY, CXmlConstantValue::BODY)
											->addConstant(CXmlConstantName::HEADERS, CXmlConstantValue::HEADERS)
											->addConstant(CXmlConstantName::BOTH, CXmlConstantValue::BOTH),
										new CStringXmlTag('snmp_community'),
										new CStringXmlTag('snmp_oid'),
										new CStringXmlTag('snmpv3_authpassphrase'),
										(new CStringXmlTag('snmpv3_authprotocol'))
											->setDefaultValue(CXmlConstantValue::SNMPV3_MD5)
											->addConstant(CXmlConstantName::MD5, CXmlConstantValue::SNMPV3_MD5)
											->addConstant(CXmlConstantName::SHA, CXmlConstantValue::SNMPV3_SHA),
										new CStringXmlTag('snmpv3_contextname'),
										new CStringXmlTag('snmpv3_privpassphrase'),
										(new CStringXmlTag('snmpv3_privprotocol'))
											->setDefaultValue(CXmlConstantValue::DES)
											->addConstant(CXmlConstantName::DES, CXmlConstantValue::DES)
											->addConstant(CXmlConstantName::AES, CXmlConstantValue::AES),
										(new CStringXmlTag('snmpv3_securitylevel'))
											->setDefaultValue(CXmlConstantValue::NOAUTHNOPRIV)
											->addConstant(CXmlConstantName::NOAUTHNOPRIV, CXmlConstantValue::NOAUTHNOPRIV)
											->addConstant(CXmlConstantName::AUTHNOPRIV, CXmlConstantValue::AUTHNOPRIV)
											->addConstant(CXmlConstantName::AUTHPRIV, CXmlConstantValue::AUTHPRIV),
										new CStringXmlTag('snmpv3_securityname'),
										new CStringXmlTag('ssl_cert_file'),
										new CStringXmlTag('ssl_key_file'),
										new CStringXmlTag('ssl_key_password'),
										(new CStringXmlTag('status'))
											->setDefaultValue(CXmlConstantValue::ENABLED)
											->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
											->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
										new CStringXmlTag('status_codes'),
										new CStringXmlTag('timeout'),
										(new CStringXmlTag('trends'))->setDefaultValue('365d'),
										(new CIndexedArrayXmlTag('triggers'))
											->setSchema(
												(new CArrayXmlTag('trigger'))
													->setSchema(
														(new CStringXmlTag('expression'))->setRequired(),
														(new CStringXmlTag('name'))
															->setRequired()
															->setKey('description'),
														(new CStringXmlTag('correlation_mode'))
															->setDefaultValue(CXmlConstantValue::TRIGGER_DISABLED)
															->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::TRIGGER_DISABLED)
															->addConstant(CXmlConstantName::TAG_VALUE, CXmlConstantValue::TRIGGER_TAG_VALUE),
														new CStringXmlTag('correlation_tag'),
														(new CIndexedArrayXmlTag('dependencies'))
															->setSchema(
																(new CArrayXmlTag('dependency'))
																	->setSchema(
																		(new CStringXmlTag('expression'))->setRequired(),
																		(new CStringXmlTag('name'))
																			->setRequired()
																			->setKey('description'),
																		new CStringXmlTag('recovery_expression')
																	)
															),
														(new CStringXmlTag('description'))->setKey('comments'),
														(new CStringXmlTag('manual_close'))
															->setDefaultValue(CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
															->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
														(new CStringXmlTag('priority'))
															->setDefaultValue(CXmlConstantValue::NOT_CLASSIFIED)
															->addConstant(CXmlConstantName::NOT_CLASSIFIED, CXmlConstantValue::NOT_CLASSIFIED)
															->addConstant(CXmlConstantName::INFO, CXmlConstantValue::INFO)
															->addConstant(CXmlConstantName::WARNING, CXmlConstantValue::WARNING)
															->addConstant(CXmlConstantName::AVERAGE, CXmlConstantValue::AVERAGE)
															->addConstant(CXmlConstantName::HIGH, CXmlConstantValue::HIGH)
															->addConstant(CXmlConstantName::DISASTER, CXmlConstantValue::DISASTER),
														new CStringXmlTag('recovery_expression'),
														(new CStringXmlTag('recovery_mode'))
															->setDefaultValue(CXmlConstantValue::TRIGGER_EXPRESSION)
															->addConstant(CXmlConstantName::EXPRESSION, CXmlConstantValue::TRIGGER_EXPRESSION)
															->addConstant(CXmlConstantName::RECOVERY_EXPRESSION, CXmlConstantValue::TRIGGER_RECOVERY_EXPRESSION)
															->addConstant(CXmlConstantName::NONE, CXmlConstantValue::TRIGGER_NONE),
														(new CStringXmlTag('status'))
															->setDefaultValue(CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
															->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
														(new CIndexedArrayXmlTag('tags'))
															->setSchema(
																(new CArrayXmlTag('tag'))
																	->setSchema(
																		(new CStringXmlTag('tag'))->setRequired(),
																		new CStringXmlTag('value')
																	)
															),
														(new CStringXmlTag('type'))
															->setDefaultValue(CXmlConstantValue::SINGLE)
															->addConstant(CXmlConstantName::SINGLE, CXmlConstantValue::SINGLE)
															->addConstant(CXmlConstantName::MULTIPLE, CXmlConstantValue::MULTIPLE),
														new CStringXmlTag('url')
													)
											),
										(new CStringXmlTag('type'))
											->setDefaultValue(CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE)
											->addConstant(CXmlConstantName::ZABBIX_PASSIVE, CXmlConstantValue::ITEM_TYPE_ZABBIX_PASSIVE)
											->addConstant(CXmlConstantName::SNMPV1, CXmlConstantValue::ITEM_TYPE_SNMPV1)
											->addConstant(CXmlConstantName::TRAP, CXmlConstantValue::ITEM_TYPE_TRAP)
											->addConstant(CXmlConstantName::SIMPLE, CXmlConstantValue::ITEM_TYPE_SIMPLE)
											->addConstant(CXmlConstantName::SNMPV2, CXmlConstantValue::ITEM_TYPE_SNMPV2)
											->addConstant(CXmlConstantName::INTERNAL, CXmlConstantValue::ITEM_TYPE_INTERNAL)
											->addConstant(CXmlConstantName::SNMPV3, CXmlConstantValue::ITEM_TYPE_SNMPV3)
											->addConstant(CXmlConstantName::ZABBIX_ACTIVE, CXmlConstantValue::ITEM_TYPE_ZABBIX_ACTIVE)
											->addConstant(CXmlConstantName::AGGREGATE, CXmlConstantValue::ITEM_TYPE_AGGREGATE)
											->addConstant(CXmlConstantName::EXTERNAL, CXmlConstantValue::ITEM_TYPE_EXTERNAL)
											->addConstant(CXmlConstantName::ODBC, CXmlConstantValue::ITEM_TYPE_ODBC)
											->addConstant(CXmlConstantName::IPMI, CXmlConstantValue::ITEM_TYPE_IPMI)
											->addConstant(CXmlConstantName::SSH, CXmlConstantValue::ITEM_TYPE_SSH)
											->addConstant(CXmlConstantName::TELNET, CXmlConstantValue::ITEM_TYPE_TELNET)
											->addConstant(CXmlConstantName::CALCULATED, CXmlConstantValue::ITEM_TYPE_CALCULATED)
											->addConstant(CXmlConstantName::JMX, CXmlConstantValue::ITEM_TYPE_JMX)
											->addConstant(CXmlConstantName::SNMP_TRAP, CXmlConstantValue::ITEM_TYPE_SNMP_TRAP)
											->addConstant(CXmlConstantName::DEPENDENT, CXmlConstantValue::ITEM_TYPE_DEPENDENT)
											->addConstant(CXmlConstantName::HTTP_AGENT, CXmlConstantValue::ITEM_TYPE_HTTP_AGENT),
										new CStringXmlTag('units'),
										new CStringXmlTag('url'),
										new CStringXmlTag('username'),
										(new CStringXmlTag('value_type'))
											->setDefaultValue(CXmlConstantValue::UNSIGNED)
											->addConstant(CXmlConstantName::FLOAT, CXmlConstantValue::FLOAT)
											->addConstant(CXmlConstantName::CHAR, CXmlConstantValue::CHAR)
											->addConstant(CXmlConstantName::LOG, CXmlConstantValue::LOG)
											->addConstant(CXmlConstantName::UNSIGNED, CXmlConstantValue::UNSIGNED)
											->addConstant(CXmlConstantName::TEXT, CXmlConstantValue::TEXT),
										(new CArrayXmlTag('valuemap'))
											->setSchema(
												(new CStringXmlTag('name'))
											),
										(new CStringXmlTag('verify_host'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES),
										(new CStringXmlTag('verify_peer'))
											->setDefaultValue(CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::NO, CXmlConstantValue::NO)
											->addConstant(CXmlConstantName::YES, CXmlConstantValue::YES)
									)
							),
						(new CIndexedArrayXmlTag('macros'))
							->setSchema(
								(new CArrayXmlTag('macro'))
									->setSchema(
										(new CStringXmlTag('macro'))->setRequired(),
										new CStringXmlTag('value')
									)
							),
						new CStringXmlTag('name'),
						(new CArrayXmlTag('proxy'))
							->setSchema(
								(new CStringXmlTag('name'))->setRequired()
							),
						(new CStringXmlTag('status'))
							->setDefaultValue(CXmlConstantValue::ENABLED)
							->addConstant(CXmlConstantName::ENABLED, CXmlConstantValue::ENABLED)
							->addConstant(CXmlConstantName::DISABLED, CXmlConstantValue::DISABLED),
						(new CIndexedArrayXmlTag('tags'))
							->setSchema(
								(new CArrayXmlTag('tag'))
									->setSchema(
										(new CStringXmlTag('tag'))->setRequired(),
										new CStringXmlTag('value')
									)
							),
						(new CIndexedArrayXmlTag('templates'))
							->setKey('parentTemplates')
							->setSchema(
								(new CArrayXmlTag('template'))
									->setSchema(
										(new CStringXmlTag('name'))->setRequired()
											->setKey('host')
									)
							),
						(new CStringXmlTag('tls_accept'))
							->setDefaultValue(CXmlConstantValue::NO_ENCRYPTION)
							->addConstant(CXmlConstantName::NO_ENCRYPTION, CXmlConstantValue::NO_ENCRYPTION)
							->addConstant(CXmlConstantName::TLS_PSK, CXmlConstantValue::TLS_PSK)
							->addConstant([CXmlConstantName::NO_ENCRYPTION, CXmlConstantName::TLS_PSK], 3)
							->addConstant(CXmlConstantName::TLS_CERTIFICATE, CXmlConstantValue::TLS_CERTIFICATE)
							->addConstant([CXmlConstantName::NO_ENCRYPTION, CXmlConstantName::TLS_CERTIFICATE], 5)
							->addConstant([CXmlConstantName::TLS_PSK, CXmlConstantName::TLS_CERTIFICATE], 6)
							->addConstant([CXmlConstantName::NO_ENCRYPTION, CXmlConstantName::TLS_PSK, CXmlConstantName::TLS_CERTIFICATE], 7)
							->setExportHandler(function(array $data, CXmlTagInterface $class) {
								$const = $class->getConstantByValue($data['tls_accept']);
								return is_array($const) ? $const : [$const];
							})
							->setImportHandler(function(array $data, CXmlTagInterface $class) {
								if (!array_key_exists('tls_accept', $data)) {
									return (string) $class->getDefaultValue();
								}

								$result = 0;
								foreach ($data['tls_accept'] as $const) {
									$result += $class->getConstantValueByName($const);
								}
								return (string) $result;
							}),
						(new CStringXmlTag('tls_connect'))
							->setDefaultValue(CXmlConstantValue::NO_ENCRYPTION)
							->addConstant(CXmlConstantName::NO_ENCRYPTION, CXmlConstantValue::NO_ENCRYPTION)
							->addConstant(CXmlConstantName::TLS_PSK, CXmlConstantValue::TLS_PSK)
							->addConstant(CXmlConstantName::TLS_CERTIFICATE, CXmlConstantValue::TLS_CERTIFICATE),
						new CStringXmlTag('tls_issuer'),
						new CStringXmlTag('tls_psk'),
						new CStringXmlTag('tls_psk_identity'),
						new CStringXmlTag('tls_subject')
					)
			);
	}
}
