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
			['type' => self::XML_ARRAY, 'rules' => [
				'version' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
				'date' =>					['type' => self::XML_STRING, 'ex_validate' => [$this, 'validateDateTime']],
				'groups' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'group', 'rules' => [
					'group' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
					]]
				]],
				'hosts' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => [
					'host' =>					['type' => self::XML_ARRAY, 'rules' => [
						'host' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'description' =>			['type' => self::XML_STRING],
						'proxy' =>					['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
							'name' =>					['type' => self::XML_STRING]
						]],
						'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'ipmi_authtype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'ipmi_privilege' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'ipmi_username' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'ipmi_password' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'templates' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
							'template' =>				['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'groups' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group', 'rules' => [
							'group' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'interfaces' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'interface', 'rules' => [
							'interface' =>				['type' => self::XML_ARRAY, 'rules' => [
								'default' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'useip' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'ip' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'dns' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'bulk' =>					['type' => self::XML_STRING],
								'interface_ref' =>			['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'applications' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => [
							'application' =>			['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'items' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
							'item' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'multiplier' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'history' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'trends' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'value_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'allowed_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'units' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'delta' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_contextname' =>		['type' => self::XML_STRING],
								'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_authprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_privprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'formula' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'data_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'inventory_link' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'applications' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => [
									'application' =>			['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
									]]
								]],
								'valuemap' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING]
								]],
								'logtimefmt' =>				['type' => self::XML_STRING],
								'interface_ref' =>			['type' => self::XML_STRING]
							]]
						]],
						'discovery_rules' =>		['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => [
							'discovery_rule' =>			['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'allowed_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_contextname' =>		['type' => self::XML_STRING],
								'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_authprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_privprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
/* TYPE (2.0 string] */			'filter' =>					['type' => self::XML_REQUIRED],
								'lifetime' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'interface_ref' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'item_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'item_prototype', 'rules' => [
									'item_prototype' =>			['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'multiplier' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'history' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'trends' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'value_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'allowed_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'units' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'delta' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_contextname' =>		['type' => self::XML_STRING],
										'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_authprotocol' =>	['type' => self::XML_STRING],
										'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_privprotocol' =>	['type' => self::XML_STRING],
										'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'formula' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'data_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'inventory_link' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'applications' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => [
											'application' =>			['type' => self::XML_ARRAY, 'rules' => [
												'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
											]]
										]],
										'valuemap' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
											'name' =>					['type' => self::XML_STRING]
										]],
										'logtimefmt' =>				['type' => self::XML_STRING],
										'interface_ref' =>			['type' => self::XML_STRING]
									]]
								]],
								'trigger_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'trigger_prototype', 'rules' => [
									'trigger_prototype' =>		['type' => self::XML_ARRAY, 'rules' => [
										'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'url' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'priority' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
									]]
								]],
								'graph_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_prototype', 'rules' => [
									'graph_prototype' =>		['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'yaxismin' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'yaxismax' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_work_period' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_triggers' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_legend' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_3d' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'percent_left' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'percent_right' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'ymin_item_1' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'preprocessor' => array($this, 'transform_zero2array'), 'rules' => [
											'host' =>					['type' => self::XML_STRING],
											'key' =>					['type' => self::XML_STRING],
										]],
										'ymin_type_1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'ymax_item_1' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'preprocessor' => array($this, 'transform_zero2array'), 'rules' => [
											'host' =>					['type' => self::XML_STRING],
											'key' =>					['type' => self::XML_STRING],
										]],
										'ymax_type_1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'graph_items' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => [
											'graph_item' =>				['type' => self::XML_ARRAY, 'rules' => [
												'sortorder' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
												'yaxisside' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'calc_fnc' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
												'item' =>					['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
													'host' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
													'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
												]]
											]]
										]]
									]]
								]],
								'host_prototypes' =>		['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => [
									'host_prototype' =>			['type' => self::XML_ARRAY, 'rules' => [
										'host' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'group_links' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_link', 'rules' => [
											'group_link' =>				['type' => self::XML_ARRAY, 'rules' => [
												'group' =>					['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
													'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
												]]
											]]
										]],
										'group_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_prototype', 'rules' => [
											'group_prototype' =>		['type' => self::XML_ARRAY, 'rules' => [
												'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
											]]
										]],
										'templates' =>				['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'template', 'rules' => [
											'template' =>				['type' => self::XML_ARRAY, 'rules' => [
												'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
											]]
										]]
									]]
								]]
							]]
						]],
						'macros' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
							'macro' =>				['type' => self::XML_ARRAY, 'rules' => [
								'macro' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'value' =>				['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'inventory' =>				['type' => self::XML_ARRAY, 'rules' => [
							'inventory_mode' =>			['type' => self::XML_STRING],
							'type' =>					['type' => self::XML_STRING],
							'type_full' =>				['type' => self::XML_STRING],
							'name' =>					['type' => self::XML_STRING],
							'alias' =>					['type' => self::XML_STRING],
							'os' =>						['type' => self::XML_STRING],
							'os_full' =>				['type' => self::XML_STRING],
							'os_short' =>				['type' => self::XML_STRING],
							'serialno_a' =>				['type' => self::XML_STRING],
							'serialno_b' =>				['type' => self::XML_STRING],
							'tag' =>					['type' => self::XML_STRING],
							'asset_tag' =>				['type' => self::XML_STRING],
							'macaddress_a' =>			['type' => self::XML_STRING],
							'macaddress_b' =>			['type' => self::XML_STRING],
							'hardware' =>				['type' => self::XML_STRING],
							'hardware_full' =>			['type' => self::XML_STRING],
							'software' =>				['type' => self::XML_STRING],
							'software_full' =>			['type' => self::XML_STRING],
							'software_app_a' =>			['type' => self::XML_STRING],
							'software_app_b' =>			['type' => self::XML_STRING],
							'software_app_c' =>			['type' => self::XML_STRING],
							'software_app_d' =>			['type' => self::XML_STRING],
							'software_app_e' =>			['type' => self::XML_STRING],
							'contact' =>				['type' => self::XML_STRING],
							'location' =>				['type' => self::XML_STRING],
							'location_lat' =>			['type' => self::XML_STRING],
							'location_lon' =>			['type' => self::XML_STRING],
							'notes' =>					['type' => self::XML_STRING],
							'chassis' =>				['type' => self::XML_STRING],
							'model' =>					['type' => self::XML_STRING],
							'hw_arch' =>				['type' => self::XML_STRING],
							'vendor' =>					['type' => self::XML_STRING],
							'contract_number' =>		['type' => self::XML_STRING],
							'installer_name' =>			['type' => self::XML_STRING],
							'deployment_status' =>		['type' => self::XML_STRING],
							'url_a' =>					['type' => self::XML_STRING],
							'url_b' =>					['type' => self::XML_STRING],
							'url_c' =>					['type' => self::XML_STRING],
							'host_networks' =>			['type' => self::XML_STRING],
							'host_netmask' =>			['type' => self::XML_STRING],
							'host_router' =>			['type' => self::XML_STRING],
							'oob_ip' =>					['type' => self::XML_STRING],
							'oob_netmask' =>			['type' => self::XML_STRING],
							'oob_router' =>				['type' => self::XML_STRING],
							'date_hw_purchase' =>		['type' => self::XML_STRING],
							'date_hw_install' =>		['type' => self::XML_STRING],
							'date_hw_expiry' =>			['type' => self::XML_STRING],
							'date_hw_decomm' =>			['type' => self::XML_STRING],
							'site_address_a' =>			['type' => self::XML_STRING],
							'site_address_b' =>			['type' => self::XML_STRING],
							'site_address_c' =>			['type' => self::XML_STRING],
							'site_city' =>				['type' => self::XML_STRING],
							'site_state' =>				['type' => self::XML_STRING],
							'site_country' =>			['type' => self::XML_STRING],
							'site_zip' =>				['type' => self::XML_STRING],
							'site_rack' =>				['type' => self::XML_STRING],
							'site_notes' =>				['type' => self::XML_STRING],
							'poc_1_name' =>				['type' => self::XML_STRING],
							'poc_1_email' =>			['type' => self::XML_STRING],
							'poc_1_phone_a' =>			['type' => self::XML_STRING],
							'poc_1_phone_b' =>			['type' => self::XML_STRING],
							'poc_1_cell' =>				['type' => self::XML_STRING],
							'poc_1_screen' =>			['type' => self::XML_STRING],
							'poc_1_notes' =>			['type' => self::XML_STRING],
							'poc_2_name' =>				['type' => self::XML_STRING],
							'poc_2_email' =>			['type' => self::XML_STRING],
							'poc_2_phone_a' =>			['type' => self::XML_STRING],
							'poc_2_phone_b' =>			['type' => self::XML_STRING],
							'poc_2_cell' =>				['type' => self::XML_STRING],
							'poc_2_screen' =>			['type' => self::XML_STRING],
							'poc_2_notes' =>			['type' => self::XML_STRING]
						]]
					]]
				]],
				'templates' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
					'template' =>				['type' => self::XML_ARRAY, 'rules' => [
						'template' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'description' =>			['type' => self::XML_STRING],
						'templates' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
							'template' =>				['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'groups' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group', 'rules' => [
							'group' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'applications' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => [
							'application' =>			['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'items' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
							'item' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'multiplier' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'history' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'trends' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'value_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'allowed_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'units' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'delta' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_contextname' =>		['type' => self::XML_STRING],
								'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_authprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_privprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'formula' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'data_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'inventory_link' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'applications' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => [
									'application' =>			['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
									]]
								]],
								'valuemap' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING]
								]],
								'logtimefmt' =>				['type' => self::XML_STRING]
							]]
						]],
						'discovery_rules' =>		['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => [
							'discovery_rule' =>			['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'allowed_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_contextname' =>		['type' => self::XML_STRING],
								'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_authprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_privprotocol' =>	['type' => self::XML_STRING],
								'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
/* TYPE (2.0 string] */			'filter' =>					['type' => self::XML_REQUIRED],
								'lifetime' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'item_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'item_prototype', 'rules' => [
									'item_prototype' =>			['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'multiplier' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'history' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'trends' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'value_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'allowed_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'units' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'delta' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_contextname' =>		['type' => self::XML_STRING],
										'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_authprotocol' =>	['type' => self::XML_STRING],
										'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'snmpv3_privprotocol' =>	['type' => self::XML_STRING],
										'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
										'formula' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'data_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'inventory_link' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'applications' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'application', 'rules' => [
											'application' =>			['type' => self::XML_ARRAY, 'rules' => [
												'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
											]]
										]],
										'valuemap' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
											'name' =>					['type' => self::XML_STRING]
										]],
										'logtimefmt' =>				['type' => self::XML_STRING]
									]]
								]],
								'trigger_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'trigger_prototype', 'rules' => [
									'trigger_prototype' =>		['type' => self::XML_ARRAY, 'rules' => [
										'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'url' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'priority' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
									]]
								]],
								'graph_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_prototype', 'rules' => [
									'graph_prototype' =>		['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'yaxismin' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'yaxismax' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_work_period' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_triggers' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_legend' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'show_3d' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'percent_left' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'percent_right' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'ymin_item_1' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'preprocessor' => array($this, 'transform_zero2array'), 'rules' => [
											'host' =>					['type' => self::XML_STRING],
											'key' =>					['type' => self::XML_STRING],
										]],
										'ymin_type_1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'ymax_item_1' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'preprocessor' => array($this, 'transform_zero2array'), 'rules' => [
											'host' =>					['type' => self::XML_STRING],
											'key' =>					['type' => self::XML_STRING],
										]],
										'ymax_type_1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'graph_items' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => [
											'graph_item' =>				['type' => self::XML_ARRAY, 'rules' => [
												'sortorder' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
												'yaxisside' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'calc_fnc' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
												'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
												'item' =>					['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
													'host' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
													'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
												]]
											]]
										]]
									]]
								]],
								'host_prototypes' =>		['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => [
									'host_prototype' =>			['type' => self::XML_ARRAY, 'rules' => [
										'host' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'group_links' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_link', 'rules' => [
											'group_link' =>				['type' => self::XML_ARRAY, 'rules' => [
												'group' =>					['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
													'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
												]]
											]]
										]],
										'group_prototypes' =>		['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group_prototype', 'rules' => [
											'group_prototype' =>		['type' => self::XML_ARRAY, 'rules' => [
												'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
											]]
										]],
										'templates' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
											'template' =>				['type' => self::XML_ARRAY, 'rules' => [
												'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
											]]
										]]
									]]
								]]
							]]
						]],
						'macros' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
							'macro' =>				['type' => self::XML_ARRAY, 'rules' => [
								'macro' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'value' =>				['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'screens' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => [
							'screen' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'hsize' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'vsize' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'screen_items' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen_item', 'rules' => [
									'screen_item' =>			['type' => self::XML_ARRAY, 'rules' => [
										'resourcetype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'resource' =>				['type' => self::XML_REQUIRED],
										'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'x' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
										'y' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
										'colspan' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'rowspan' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'elements' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'valign' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'halign' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'style' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'dynamic' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'sort_triggers' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
										'url' =>					['type' => self::XML_STRING],
										'application' =>			['type' => self::XML_STRING],
										'max_columns' =>			['type' => self::XML_STRING]
									]]
								]]
							]]
						]]
					]]
				]],
				'triggers' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
					'trigger' =>				['type' => self::XML_ARRAY, 'rules' => [
						'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'url' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'priority' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'dependencies' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
							'dependency' =>				['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]]
					]]
				]],
				'graphs' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => [
					'graph' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'yaxismin' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'yaxismax' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'show_work_period' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'show_triggers' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'show_legend' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'show_3d' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'percent_left' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'percent_right' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'ymin_item_1' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'preprocessor' => array($this, 'transform_zero2array'), 'rules' => [
							'host' =>					['type' => self::XML_STRING],
							'key' =>					['type' => self::XML_STRING],
						]],
						'ymin_type_1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'ymax_item_1' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'preprocessor' => array($this, 'transform_zero2array'), 'rules' => [
							'host' =>					['type' => self::XML_STRING],
							'key' =>					['type' => self::XML_STRING],
						]],
						'ymax_type_1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'graph_items' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => [
							'graph_item' =>				['type' => self::XML_ARRAY, 'rules' => [
								'sortorder' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'yaxisside' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'calc_fnc' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'item' =>					['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'host' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
									'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]]
							]]
						]]
					]]
				]],
				'screens' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => [
					'screen' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'hsize' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'vsize' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'screen_items' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen_item', 'rules' => [
							'screen_item' =>			['type' => self::XML_ARRAY, 'rules' => [
								'resourcetype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'resource' =>				['type' => self::XML_REQUIRED],
								'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'x' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'y' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'colspan' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'rowspan' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'elements' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'valign' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'halign' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'style' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'dynamic' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'sort_triggers' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'url' =>					['type' => self::XML_STRING],
								'application' =>			['type' => self::XML_STRING],
								'max_columns' =>			['type' => self::XML_STRING]
							]]
						]]
					]]
				]],
				'images' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'image', 'rules' => [
					'image' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'imagetype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'encodedImage' =>			['type' => self::XML_STRING | self::XML_REQUIRED]
					]]
				]],
				'maps' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'map', 'rules' => [
					'map' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_location' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'highlight' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'expandproblem' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'markelements' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'show_unack' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'severity_min' =>			['type' => self::XML_STRING],
						'grid_size' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'grid_show' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'grid_align' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_format' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_type_host' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_type_hostgroup' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_type_trigger' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_type_map' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_type_image' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_string_host' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_string_hostgroup' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_string_trigger' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_string_map' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_string_image' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
						'expand_macros' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'background' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
							'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
						]],
						'iconmap' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
							'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
						]],
						'urls' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'url', 'rules' => [
							'url' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'url' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'elementtype' =>			['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'selements' =>				['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'selement', 'rules' => [
							'selement' =>				['type' => self::XML_ARRAY, 'rules' => [
								'elementtype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'label' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'label_location' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'x' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'y' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'elementsubtype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'areatype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'viewtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'use_iconmap' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'selementid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
/* TYPE ??? */					'element' =>				['type' => self::XML_REQUIRED],
								'icon_off' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'icon_on' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'icon_disabled' =>			['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'icon_maintenance' =>		['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'application' =>			['type' => self::XML_STRING],
								'urls' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'url', 'rules' => [
									'url' =>					['type' => self::XML_ARRAY, 'rules' => [
										'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'url' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
									]]
								]]
							]]
						]],
						'links' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'link', 'rules' => [
							'link' =>					['type' => self::XML_ARRAY, 'rules' => [
								'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'label' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'selementid1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'selementid2' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'linktriggers' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'linktrigger', 'rules' => [
									'linktrigger' =>			['type' => self::XML_ARRAY, 'rules' => [
										'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'trigger' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
											'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
											'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED]
										]]
									]]
								]]
							]]
						]]
					]]
				]]
			]]
		);
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

	/**
	 * Transforms tags containing zero into an empty array
	 *
	 * @param mixed $value
	 *
	 * @return mixed		converted value
	 */
	protected function transform_zero2array($value) {
		return ($value === '0') ? [] : $value;
	}
}
