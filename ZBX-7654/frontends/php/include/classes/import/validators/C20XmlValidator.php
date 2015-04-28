<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * Validate import data from Zabbix 2.x.
 */
class C20XmlValidator extends CXmlValidatorGeneral {

	public function __construct() {
		parent::__construct(
			array('type' => self::XML_ARRAY, 'rules' => array(
				'version' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
				'date' =>					array('type' => self::XML_STRING, 'ex_validate' => array($this, 'validateDateTime')),
				'groups' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'group', 'rules' => array(
					'group' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
					))
				)),
				'hosts' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => array(
					'host' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'host' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'description' =>			array('type' => self::XML_STRING),
						'proxy' =>					array('type' => self::XML_STRING),
						'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'ipmi_authtype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'ipmi_privilege' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'ipmi_username' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'ipmi_password' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'templates' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => array(
							'template' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'groups' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group', 'rules' => array(
							'group' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'interfaces' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'interface', 'rules' => array(
							'interface' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'default' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'useip' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
/* REQUIRED ??? */				'ip' =>						array('type' => self::XML_STRING),
/* REQUIRED ??? */				'dns' =>					array('type' => self::XML_STRING),
								'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'bulk' =>					array('type' => self::XML_STRING),
								'interface_ref' =>			array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'applications' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => array(
							'application' =>			array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'items' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => array(
							'item' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'multiplier' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'history' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'trends' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'value_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */		'allowed_hosts' =>			array('type' => 0),
								'units' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delta' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_contextname' =>		array('type' => self::XML_STRING),
								'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_authprotocol' =>	array('type' => self::XML_STRING),
								'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */		'snmpv3_privprotocol' =>	array('type' => 0),
								'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'formula' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'data_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'inventory_link' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* REQUIRED ??? */				'applications' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => array(
									'application' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
									))
								)),
/* REQUIRED ??? */				'valuemap' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'logtimefmt' =>				array('type' => self::XML_STRING),
								'interface_ref' =>			array('type' => self::XML_STRING)
							))
						)),
						'discovery_rules' =>		array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => array(
							'discovery_rule' =>			array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'allowed_hosts' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_contextname' =>		array('type' => self::XML_STRING),
								'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_authprotocol' =>	array('type' => self::XML_STRING),
								'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */		'snmpv3_privprotocol' =>	array('type' => 0),
								'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE ??? */					'filter' =>					array('type' => self::XML_REQUIRED),
								'lifetime' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'interface_ref' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'item_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'item_prototype', 'rules' => array(
									'item_prototype' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'multiplier' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'history' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'trends' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'value_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'allowed_hosts' =>			array('type' => 0),
										'units' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'delta' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmpv3_contextname' =>		array('type' => self::XML_STRING),
										'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmpv3_authprotocol' =>	array('type' => self::XML_STRING),
										'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'snmpv3_privprotocol' =>	array('type' => 0),
										'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
										'formula' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'data_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'inventory_link' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* REQUIRED ??? */						'applications' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => array(
											'application' =>			array('type' => self::XML_ARRAY, 'rules' => array(
												'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
											))
										)),
/* REQUIRED ??? */						'valuemap' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
											'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
										)),
										'logtimefmt' =>				array('type' => self::XML_STRING),
										'interface_ref' =>			array('type' => self::XML_STRING)
									))
								)),
								'trigger_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'trigger_prototype', 'rules' => array(
									'trigger_prototype' =>		array('type' => self::XML_ARRAY, 'rules' => array(
										'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'url' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'priority' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
									))
								)),
								'graph_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_prototype', 'rules' => array(
									'graph_prototype' =>		array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'yaxismin' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'yaxismax' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_work_period' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_triggers' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_legend' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_3d' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'percent_left' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'percent_right' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'ymin_item_1' =>			array('type' => 0),
										'ymin_type_1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'ymax_item_1' =>			array('type' => 0),
										'ymax_type_1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'graph_items' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => array(
											'graph_item' =>				array('type' => self::XML_ARRAY, 'rules' => array(
												'sortorder' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
												'yaxisside' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'calc_fnc' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
												'item' =>					array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
													'host' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
													'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
												))
											))
										))
									))
								)),
								'host_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => array(
									'host_prototype' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'host' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'group_links' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_link', 'rules' => array(
/* INDEXED_ARRAY ??? */						'group_link' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'group', 'rules' => array(
												'group' =>					array('type' => self::XML_ARRAY, 'rules' => array(
													'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
												))
											))
										)),
										'group_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_prototype', 'rules' => array(
											'group_prototype' =>		array('type' => self::XML_ARRAY, 'rules' => array(
												'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
											))
										)),
										'templates' =>				array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'template', 'rules' => array(
											'template' =>				array('type' => self::XML_ARRAY, 'rules' => array(
												'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
											))
										))
									))
								))
							))
						)),
						'macros' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => array(
							'macro' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'macro' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'value' =>				array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'inventory' =>				array('type' => self::XML_ARRAY, 'rules' => array(
							'inventory_mode' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'type_full' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'alias' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'os' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
							'os_full' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'os_short' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'serialno_a' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'serialno_b' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'tag' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'asset_tag' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'macaddress_a' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'macaddress_b' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'hardware' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'hardware_full' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software_full' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software_app_a' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software_app_b' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software_app_c' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software_app_d' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'software_app_e' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'contact' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'location' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'location_lat' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'location_lon' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'notes' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'chassis' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'model' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'hw_arch' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'vendor' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'contract_number' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
							'installer_name' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'deployment_status' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
							'url_a' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'url_b' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'url_c' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'host_networks' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'host_netmask' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'host_router' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'oob_ip' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
							'oob_netmask' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'oob_router' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'date_hw_purchase' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
							'date_hw_install' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
							'date_hw_expiry' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'date_hw_decomm' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_address_a' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_address_b' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_address_c' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_city' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_state' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_country' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_zip' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_rack' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'site_notes' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_name' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_email' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_phone_a' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_phone_b' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_cell' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_screen' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_1_notes' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_name' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_email' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_phone_a' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_phone_b' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_cell' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_screen' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
							'poc_2_notes' =>			array('type' => self::XML_STRING | self::XML_REQUIRED)
						))
					))
				)),
				'templates' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => array(
					'template' =>				array('type' => self::XML_ARRAY, 'rules' => array(
						'template' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'description' =>			array('type' => self::XML_STRING),
						'templates' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => array(
							'template' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'groups' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group', 'rules' => array(
							'group' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'applications' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => array(
							'application' =>			array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'items' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => array(
							'item' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'multiplier' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'history' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'trends' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'value_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */		'allowed_hosts' =>			array('type' => 0),
								'units' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delta' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_contextname' =>		array('type' => self::XML_STRING),
								'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_authprotocol' =>	array('type' => self::XML_STRING),
								'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */		'snmpv3_privprotocol' =>	array('type' => 0),
								'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'formula' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'data_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'inventory_link' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* REQUIRED */					'applications' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => array(
									'application' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
									))
								)),
/* REQUIRED */					'valuemap' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'logtimefmt' =>				array('type' => self::XML_STRING),
/* REMOVE ??? */				'interface_ref' =>			array('type' => self::XML_STRING)
							))
						)),
						'discovery_rules' =>		array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => array(
							'discovery_rule' =>			array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'allowed_hosts' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_contextname' =>		array('type' => self::XML_STRING),
								'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_authprotocol' =>	array('type' => self::XML_STRING),
								'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */		'snmpv3_privprotocol' =>	array('type' => 0),
								'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE ??? */					'filter' =>					array('type' => self::XML_REQUIRED),
								'lifetime' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'item_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'item_prototype', 'rules' => array(
									'item_prototype' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'multiplier' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'history' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'trends' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'value_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'allowed_hosts' =>			array('type' => 0),
										'units' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'delta' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmpv3_contextname' =>		array('type' => self::XML_STRING),
										'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
										'snmpv3_authprotocol' =>	array('type' => self::XML_STRING),
										'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'snmpv3_privprotocol' =>	array('type' => 0),
										'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
										'formula' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'data_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'inventory_link' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* REQUIRED ??? */						'applications' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => array(
											'application' =>			array('type' => self::XML_ARRAY, 'rules' => array(
												'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
											))
										)),
/* REQUIRED ??? */						'valuemap' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
											'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
										)),
										'logtimefmt' =>				array('type' => self::XML_STRING)
									))
								)),
								'trigger_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'trigger_prototype', 'rules' => array(
									'trigger_prototype' =>		array('type' => self::XML_ARRAY, 'rules' => array(
										'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'url' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'priority' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
									))
								)),
								'graph_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_prototype', 'rules' => array(
									'graph_prototype' =>		array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'yaxismin' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'yaxismax' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_work_period' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_triggers' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_legend' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'show_3d' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'percent_left' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'percent_right' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'ymin_item_1' =>			array('type' => 0),
										'ymin_type_1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */				'ymax_item_1' =>			array('type' => 0),
										'ymax_type_1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'graph_items' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => array(
											'graph_item' =>				array('type' => self::XML_ARRAY, 'rules' => array(
												'sortorder' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
												'yaxisside' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'calc_fnc' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
												'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
												'item' =>					array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
													'host' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
													'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
												))
											))
										))
									))
								)),
								'host_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => array(
									'host_prototype' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'host' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'group_links' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_link', 'rules' => array(
/* INDEXED_ARRAY ??? */						'group_link' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'group', 'rules' => array(
												'group' =>					array('type' => self::XML_ARRAY, 'rules' => array(
													'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
												))
											))
										)),
										'group_prototypes' =>		array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_prototype', 'rules' => array(
											'group_prototype' =>		array('type' => self::XML_ARRAY, 'rules' => array(
												'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
											))
										)),
										'templates' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => array(
											'template' =>				array('type' => self::XML_ARRAY, 'rules' => array(
												'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
											))
										))
									))
								))
							))
						)),
						'macros' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => array(
							'macro' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'macro' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'value' =>				array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'screens' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => array(
							'screen' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'hsize' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'vsize' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'screen_items' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen_item', 'rules' => array(
									'screen_item' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'resourcetype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'resource' =>				array('type' => self::XML_REQUIRED),
										'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'x' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
										'y' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
										'colspan' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'rowspan' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'elements' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'valign' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'halign' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'style' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'dynamic' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'sort_triggers' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
										'url' =>					array('type' => self::XML_STRING),
										'application' =>			array('type' => self::XML_STRING),
										'max_columns' =>			array('type' => self::XML_STRING)
									))
								))
							))
						))
					))
				)),
				'triggers' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => array(
					'trigger' =>				array('type' => self::XML_ARRAY, 'rules' => array(
						'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'url' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'priority' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'dependencies' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => array(
							'dependency' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						))
					))
				)),
				'graphs' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => array(
					'graph' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'yaxismin' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'yaxismax' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'show_work_period' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'show_triggers' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'show_legend' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'show_3d' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'percent_left' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'percent_right' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */'ymin_item_1' =>			array('type' => 0),
						'ymin_type_1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE, REQUIRED ??? */'ymax_item_1' =>			array('type' => 0),
						'ymax_type_1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'graph_items' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => array(
							'graph_item' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'sortorder' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'yaxisside' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'calc_fnc' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'item' =>					array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'host' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
									'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								))
							))
						))
					))
				)),
				'screens' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => array(
					'screen' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'hsize' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'vsize' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'screen_items' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen_item', 'rules' => array(
							'screen_item' =>			array('type' => self::XML_ARRAY, 'rules' => array(
								'resourcetype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'resource' =>				array('type' => self::XML_REQUIRED),
								'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'x' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
								'y' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
								'colspan' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'rowspan' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'elements' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'valign' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'halign' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'style' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'dynamic' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'sort_triggers' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'url' =>					array('type' => self::XML_STRING),
								'application' =>			array('type' => self::XML_STRING),
								'max_columns' =>			array('type' => self::XML_STRING)
							))
						))
					))
				)),
				'images' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'image', 'rules' => array(
					'image' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'imagetype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'encodedImage' =>			array('type' => self::XML_STRING | self::XML_REQUIRED)
					))
				)),
				'maps' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'map', 'rules' => array(
					'map' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_location' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'highlight' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'expandproblem' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'markelements' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'show_unack' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'severity_min' =>			array('type' => self::XML_STRING),
						'grid_size' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'grid_show' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'grid_align' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_format' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_type_host' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_type_hostgroup' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_type_trigger' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_type_map' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_type_image' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_string_host' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_string_hostgroup' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_string_trigger' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_string_map' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_string_image' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
						'expand_macros' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'background' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
							'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
						)),
						'iconmap' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
							'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
						)),
						'urls' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'url', 'rules' => array(
							'url' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'url' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'elementtype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'selements' =>				array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'selement', 'rules' => array(
							'selement' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'elementtype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'label' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'label_location' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'x' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
								'y' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
								'elementsubtype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'areatype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'viewtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'use_iconmap' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'selementid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE ??? */					'element' =>				array('type' => self::XML_REQUIRED),
								'icon_off' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'icon_on' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'icon_disabled' =>			array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'icon_maintenance' =>		array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'application' =>			array('type' => self::XML_STRING),
								'urls' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'url', 'rules' => array(
									'url' =>					array('type' => self::XML_ARRAY, 'rules' => array(
										'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'url' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
									))
								))
							))
						)),
						'links' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'link', 'rules' => array(
							'link' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'label' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'selementid1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'selementid2' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'linktriggers' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'linktrigger', 'rules' => array(
									'linktrigger' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'trigger' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
											'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
											'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED)
										))
									))
								))
							))
						))
					))
				))
			))
		);
	}

	/**
	 * Base validation function.
	 *
	 * @param array  $zabbix_export	import data
	 * @param string $path			XML path
	 */
	public function validate(array $zabbix_export, $path) {
		if (array_key_exists('images', $zabbix_export)) {
			$this->validateImages($zabbix_export['images'], $path.'/images');
		}
		if (array_key_exists('maps', $zabbix_export)) {
			$this->validateMaps($zabbix_export['maps'], $path.'/maps');
		}
	}

	/**
	 * Validate date and time format.
	 *
	 * @param string $date	export date and time
	 *
	 * @throws Exception	if the date or time is invalid
	 */
	protected function validateDateTime($date, $path) {
		if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[01])T(2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]Z$/', $date)) {
			throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('YYYY-MM-DDThh:mm:ssZ', 'XML date and time format'))));
		}
	}
}
