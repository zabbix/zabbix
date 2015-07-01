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
class C20XmlValidator {

	/**
	 * Base validation function.
	 *
	 * @param array  $data	import data
	 * @param string $path	XML path (for error reporting)
	 *
	 * @return array		Validator does some manipulation for the incoming data. For example, converts empty tags to
	 *						an array, if desired. Converted array is returned.
	 */
	public function validate(array $data, $path) {
		$rules = ['type' => XML_ARRAY, 'rules' => [
			'version' =>				['type' => XML_STRING | XML_REQUIRED],
			'date' =>					['type' => XML_STRING, 'ex_validate' => [$this, 'validateDateTime']],
			'groups' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'group', 'rules' => [
				'group' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED]
				]]
			]],
			'hosts' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => [
				'host' =>					['type' => XML_ARRAY, 'rules' => [
					'host' =>					['type' => XML_STRING | XML_REQUIRED],
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'description' =>			['type' => XML_STRING],
					'proxy' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
						'name' =>					['type' => XML_STRING]
					]],
					'status' =>					['type' => XML_STRING | XML_REQUIRED],
					'ipmi_authtype' =>			['type' => XML_STRING | XML_REQUIRED],
					'ipmi_privilege' =>			['type' => XML_STRING | XML_REQUIRED],
					'ipmi_username' =>			['type' => XML_STRING | XML_REQUIRED],
					'ipmi_password' =>			['type' => XML_STRING | XML_REQUIRED],
					'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
						'template' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'groups' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group', 'rules' => [
						'group' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'interfaces' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'interface', 'rules' => [
						'interface' =>				['type' => XML_ARRAY, 'rules' => [
							'default' =>				['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'useip' =>					['type' => XML_STRING | XML_REQUIRED],
							'ip' =>						['type' => XML_STRING | XML_REQUIRED],
							'dns' =>					['type' => XML_STRING | XML_REQUIRED],
							'port' =>					['type' => XML_STRING | XML_REQUIRED],
							'bulk' =>					['type' => XML_STRING],
							'interface_ref' =>			['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'applications' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => [
						'application' =>			['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'items' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
						'item' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
							'multiplier' =>				['type' => XML_STRING | XML_REQUIRED],
							'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING | XML_REQUIRED],
							'history' =>				['type' => XML_STRING | XML_REQUIRED],
							'trends' =>					['type' => XML_STRING | XML_REQUIRED],
							'status' =>					['type' => XML_STRING | XML_REQUIRED],
							'value_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'allowed_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
							'units' =>					['type' => XML_STRING | XML_REQUIRED],
							'delta' =>					['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_contextname' =>		['type' => XML_STRING],
							'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_authprotocol' =>	['type' => XML_STRING],
							'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_privprotocol' =>	['type' => XML_STRING],
							'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'formula' =>				['type' => XML_STRING | XML_REQUIRED],
							'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
							'params' =>					['type' => XML_STRING | XML_REQUIRED],
							'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
							'data_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'username' =>				['type' => XML_STRING | XML_REQUIRED],
							'password' =>				['type' => XML_STRING | XML_REQUIRED],
							'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
							'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
							'port' =>					['type' => XML_STRING | XML_REQUIRED],
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'inventory_link' =>			['type' => XML_STRING | XML_REQUIRED],
							'applications' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'application', 'rules' => [
								'application' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'valuemap' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'logtimefmt' =>				['type' => XML_STRING],
							'interface_ref' =>			['type' => XML_STRING]
						]]
					]],
					'discovery_rules' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => [
						'discovery_rule' =>			['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
							'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING | XML_REQUIRED],
							'status' =>					['type' => XML_STRING | XML_REQUIRED],
							'allowed_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_contextname' =>		['type' => XML_STRING],
							'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_authprotocol' =>	['type' => XML_STRING],
							'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_privprotocol' =>	['type' => XML_STRING],
							'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
							'params' =>					['type' => XML_STRING | XML_REQUIRED],
							'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
							'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'username' =>				['type' => XML_STRING | XML_REQUIRED],
							'password' =>				['type' => XML_STRING | XML_REQUIRED],
							'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
							'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
							'port' =>					['type' => XML_STRING | XML_REQUIRED],
							'filter' =>					['type' => XML_REQUIRED, 'ex_validate' => [$this, 'validateFilter']],
							'lifetime' =>				['type' => XML_STRING | XML_REQUIRED],
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'interface_ref' =>			['type' => XML_STRING],
							'item_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'item_prototype', 'rules' => [
								'item_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED],
									'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
									'multiplier' =>				['type' => XML_STRING | XML_REQUIRED],
									'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
									'key' =>					['type' => XML_STRING | XML_REQUIRED],
									'delay' =>					['type' => XML_STRING | XML_REQUIRED],
									'history' =>				['type' => XML_STRING | XML_REQUIRED],
									'trends' =>					['type' => XML_STRING | XML_REQUIRED],
									'status' =>					['type' => XML_STRING | XML_REQUIRED],
									'value_type' =>				['type' => XML_STRING | XML_REQUIRED],
									'allowed_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
									'units' =>					['type' => XML_STRING | XML_REQUIRED],
									'delta' =>					['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_contextname' =>		['type' => XML_STRING],
									'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_authprotocol' =>	['type' => XML_STRING],
									'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_privprotocol' =>	['type' => XML_STRING],
									'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
									'formula' =>				['type' => XML_STRING | XML_REQUIRED],
									'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
									'params' =>					['type' => XML_STRING | XML_REQUIRED],
									'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
									'data_type' =>				['type' => XML_STRING | XML_REQUIRED],
									'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
									'username' =>				['type' => XML_STRING | XML_REQUIRED],
									'password' =>				['type' => XML_STRING | XML_REQUIRED],
									'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
									'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
									'port' =>					['type' => XML_STRING | XML_REQUIRED],
									'description' =>			['type' => XML_STRING | XML_REQUIRED],
									'inventory_link' =>			['type' => XML_STRING | XML_REQUIRED],
									'applications' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'application', 'rules' => [
										'application' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'valuemap' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
										'name' =>					['type' => XML_STRING]
									]],
									'logtimefmt' =>				['type' => XML_STRING],
									'interface_ref' =>			['type' => XML_STRING]
								]]
							]],
							'trigger_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'trigger_prototype', 'rules' => [
								'trigger_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'expression' =>				['type' => XML_STRING | XML_REQUIRED],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING | XML_REQUIRED],
									'status' =>					['type' => XML_STRING | XML_REQUIRED],
									'priority' =>				['type' => XML_STRING | XML_REQUIRED],
									'description' =>			['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'graph_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_prototype', 'rules' => [
								'graph_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'width' =>					['type' => XML_STRING | XML_REQUIRED],
									'height' =>					['type' => XML_STRING | XML_REQUIRED],
									'yaxismin' =>				['type' => XML_STRING | XML_REQUIRED],
									'yaxismax' =>				['type' => XML_STRING | XML_REQUIRED],
									'show_work_period' =>		['type' => XML_STRING | XML_REQUIRED],
									'show_triggers' =>			['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED],
									'show_legend' =>			['type' => XML_STRING | XML_REQUIRED],
									'show_3d' =>				['type' => XML_STRING | XML_REQUIRED],
									'percent_left' =>			['type' => XML_STRING | XML_REQUIRED],
									'percent_right' =>			['type' => XML_STRING | XML_REQUIRED],
									// The tag 'ymin_type_1' should be validated before the 'ymin_item_1' because it is used in 'ex_validate' method.
									'ymin_type_1' =>			['type' => XML_STRING | XML_REQUIRED],
									'ymin_item_1' =>			['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMinItem']],
									// The tag 'ymax_type_1' should be validated before the 'ymax_item_1' because it is used in 'ex_validate' method.
									'ymax_type_1' =>			['type' => XML_STRING | XML_REQUIRED],
									'ymax_item_1' =>			['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMaxItem']],
									'graph_items' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => [
										'graph_item' =>				['type' => XML_ARRAY, 'rules' => [
											'sortorder' =>				['type' => XML_STRING | XML_REQUIRED],
											'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
											'color' =>					['type' => XML_STRING | XML_REQUIRED],
											'yaxisside' =>				['type' => XML_STRING | XML_REQUIRED],
											'calc_fnc' =>				['type' => XML_STRING | XML_REQUIRED],
											'type' =>					['type' => XML_STRING | XML_REQUIRED],
											'item' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'host' =>					['type' => XML_STRING | XML_REQUIRED],
												'key' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]]
								]]
							]],
							'host_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => [
								'host_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'host' =>					['type' => XML_STRING | XML_REQUIRED],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'status' =>					['type' => XML_STRING | XML_REQUIRED],
									'group_links' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group_link', 'rules' => [
										'group_link' =>				['type' => XML_ARRAY, 'rules' => [
											'group' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'name' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'group_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group_prototype', 'rules' => [
										'group_prototype' =>		['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'templates' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'template', 'rules' => [
										'template' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]]
								]]
							]]
						]]
					]],
					'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
						'macro' =>				['type' => XML_ARRAY, 'rules' => [
							'macro' =>				['type' => XML_STRING | XML_REQUIRED],
							'value' =>				['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'inventory' =>				['type' => XML_ARRAY, 'rules' => [
						'inventory_mode' =>			['type' => XML_STRING],
						'type' =>					['type' => XML_STRING],
						'type_full' =>				['type' => XML_STRING],
						'name' =>					['type' => XML_STRING],
						'alias' =>					['type' => XML_STRING],
						'os' =>						['type' => XML_STRING],
						'os_full' =>				['type' => XML_STRING],
						'os_short' =>				['type' => XML_STRING],
						'serialno_a' =>				['type' => XML_STRING],
						'serialno_b' =>				['type' => XML_STRING],
						'tag' =>					['type' => XML_STRING],
						'asset_tag' =>				['type' => XML_STRING],
						'macaddress_a' =>			['type' => XML_STRING],
						'macaddress_b' =>			['type' => XML_STRING],
						'hardware' =>				['type' => XML_STRING],
						'hardware_full' =>			['type' => XML_STRING],
						'software' =>				['type' => XML_STRING],
						'software_full' =>			['type' => XML_STRING],
						'software_app_a' =>			['type' => XML_STRING],
						'software_app_b' =>			['type' => XML_STRING],
						'software_app_c' =>			['type' => XML_STRING],
						'software_app_d' =>			['type' => XML_STRING],
						'software_app_e' =>			['type' => XML_STRING],
						'contact' =>				['type' => XML_STRING],
						'location' =>				['type' => XML_STRING],
						'location_lat' =>			['type' => XML_STRING],
						'location_lon' =>			['type' => XML_STRING],
						'notes' =>					['type' => XML_STRING],
						'chassis' =>				['type' => XML_STRING],
						'model' =>					['type' => XML_STRING],
						'hw_arch' =>				['type' => XML_STRING],
						'vendor' =>					['type' => XML_STRING],
						'contract_number' =>		['type' => XML_STRING],
						'installer_name' =>			['type' => XML_STRING],
						'deployment_status' =>		['type' => XML_STRING],
						'url_a' =>					['type' => XML_STRING],
						'url_b' =>					['type' => XML_STRING],
						'url_c' =>					['type' => XML_STRING],
						'host_networks' =>			['type' => XML_STRING],
						'host_netmask' =>			['type' => XML_STRING],
						'host_router' =>			['type' => XML_STRING],
						'oob_ip' =>					['type' => XML_STRING],
						'oob_netmask' =>			['type' => XML_STRING],
						'oob_router' =>				['type' => XML_STRING],
						'date_hw_purchase' =>		['type' => XML_STRING],
						'date_hw_install' =>		['type' => XML_STRING],
						'date_hw_expiry' =>			['type' => XML_STRING],
						'date_hw_decomm' =>			['type' => XML_STRING],
						'site_address_a' =>			['type' => XML_STRING],
						'site_address_b' =>			['type' => XML_STRING],
						'site_address_c' =>			['type' => XML_STRING],
						'site_city' =>				['type' => XML_STRING],
						'site_state' =>				['type' => XML_STRING],
						'site_country' =>			['type' => XML_STRING],
						'site_zip' =>				['type' => XML_STRING],
						'site_rack' =>				['type' => XML_STRING],
						'site_notes' =>				['type' => XML_STRING],
						'poc_1_name' =>				['type' => XML_STRING],
						'poc_1_email' =>			['type' => XML_STRING],
						'poc_1_phone_a' =>			['type' => XML_STRING],
						'poc_1_phone_b' =>			['type' => XML_STRING],
						'poc_1_cell' =>				['type' => XML_STRING],
						'poc_1_screen' =>			['type' => XML_STRING],
						'poc_1_notes' =>			['type' => XML_STRING],
						'poc_2_name' =>				['type' => XML_STRING],
						'poc_2_email' =>			['type' => XML_STRING],
						'poc_2_phone_a' =>			['type' => XML_STRING],
						'poc_2_phone_b' =>			['type' => XML_STRING],
						'poc_2_cell' =>				['type' => XML_STRING],
						'poc_2_screen' =>			['type' => XML_STRING],
						'poc_2_notes' =>			['type' => XML_STRING]
					]]
				]]
			]],
			'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
				'template' =>				['type' => XML_ARRAY, 'rules' => [
					'template' =>				['type' => XML_STRING | XML_REQUIRED],
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'description' =>			['type' => XML_STRING],
					'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
						'template' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'groups' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group', 'rules' => [
						'group' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'applications' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => [
						'application' =>			['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'items' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
						'item' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
							'multiplier' =>				['type' => XML_STRING | XML_REQUIRED],
							'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING | XML_REQUIRED],
							'history' =>				['type' => XML_STRING | XML_REQUIRED],
							'trends' =>					['type' => XML_STRING | XML_REQUIRED],
							'status' =>					['type' => XML_STRING | XML_REQUIRED],
							'value_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'allowed_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
							'units' =>					['type' => XML_STRING | XML_REQUIRED],
							'delta' =>					['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_contextname' =>		['type' => XML_STRING],
							'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_authprotocol' =>	['type' => XML_STRING],
							'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_privprotocol' =>	['type' => XML_STRING],
							'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'formula' =>				['type' => XML_STRING | XML_REQUIRED],
							'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
							'params' =>					['type' => XML_STRING | XML_REQUIRED],
							'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
							'data_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'username' =>				['type' => XML_STRING | XML_REQUIRED],
							'password' =>				['type' => XML_STRING | XML_REQUIRED],
							'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
							'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
							'port' =>					['type' => XML_STRING | XML_REQUIRED],
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'inventory_link' =>			['type' => XML_STRING | XML_REQUIRED],
							'applications' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'application', 'rules' => [
								'application' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'valuemap' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'logtimefmt' =>				['type' => XML_STRING]
						]]
					]],
					'discovery_rules' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'discovery_rule', 'rules' => [
						'discovery_rule' =>			['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
							'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING | XML_REQUIRED],
							'status' =>					['type' => XML_STRING | XML_REQUIRED],
							'allowed_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_contextname' =>		['type' => XML_STRING],
							'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_authprotocol' =>	['type' => XML_STRING],
							'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_privprotocol' =>	['type' => XML_STRING],
							'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
							'params' =>					['type' => XML_STRING | XML_REQUIRED],
							'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
							'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'username' =>				['type' => XML_STRING | XML_REQUIRED],
							'password' =>				['type' => XML_STRING | XML_REQUIRED],
							'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
							'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
							'port' =>					['type' => XML_STRING | XML_REQUIRED],
							'filter' =>					['type' => XML_REQUIRED, 'ex_validate' => [$this, 'validateFilter']],
							'lifetime' =>				['type' => XML_STRING | XML_REQUIRED],
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'item_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'item_prototype', 'rules' => [
								'item_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED],
									'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
									'multiplier' =>				['type' => XML_STRING | XML_REQUIRED],
									'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
									'key' =>					['type' => XML_STRING | XML_REQUIRED],
									'delay' =>					['type' => XML_STRING | XML_REQUIRED],
									'history' =>				['type' => XML_STRING | XML_REQUIRED],
									'trends' =>					['type' => XML_STRING | XML_REQUIRED],
									'status' =>					['type' => XML_STRING | XML_REQUIRED],
									'value_type' =>				['type' => XML_STRING | XML_REQUIRED],
									'allowed_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
									'units' =>					['type' => XML_STRING | XML_REQUIRED],
									'delta' =>					['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_contextname' =>		['type' => XML_STRING],
									'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_authprotocol' =>	['type' => XML_STRING],
									'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
									'snmpv3_privprotocol' =>	['type' => XML_STRING],
									'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
									'formula' =>				['type' => XML_STRING | XML_REQUIRED],
									'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
									'params' =>					['type' => XML_STRING | XML_REQUIRED],
									'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
									'data_type' =>				['type' => XML_STRING | XML_REQUIRED],
									'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
									'username' =>				['type' => XML_STRING | XML_REQUIRED],
									'password' =>				['type' => XML_STRING | XML_REQUIRED],
									'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
									'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
									'port' =>					['type' => XML_STRING | XML_REQUIRED],
									'description' =>			['type' => XML_STRING | XML_REQUIRED],
									'inventory_link' =>			['type' => XML_STRING | XML_REQUIRED],
									'applications' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'application', 'rules' => [
										'application' =>			['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'valuemap' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
										'name' =>					['type' => XML_STRING]
									]],
									'logtimefmt' =>				['type' => XML_STRING]
								]]
							]],
							'trigger_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'trigger_prototype', 'rules' => [
								'trigger_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'expression' =>				['type' => XML_STRING | XML_REQUIRED],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING | XML_REQUIRED],
									'status' =>					['type' => XML_STRING | XML_REQUIRED],
									'priority' =>				['type' => XML_STRING | XML_REQUIRED],
									'description' =>			['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]],
							'graph_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_prototype', 'rules' => [
								'graph_prototype' =>		['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'width' =>					['type' => XML_STRING | XML_REQUIRED],
									'height' =>					['type' => XML_STRING | XML_REQUIRED],
									'yaxismin' =>				['type' => XML_STRING | XML_REQUIRED],
									'yaxismax' =>				['type' => XML_STRING | XML_REQUIRED],
									'show_work_period' =>		['type' => XML_STRING | XML_REQUIRED],
									'show_triggers' =>			['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED],
									'show_legend' =>			['type' => XML_STRING | XML_REQUIRED],
									'show_3d' =>				['type' => XML_STRING | XML_REQUIRED],
									'percent_left' =>			['type' => XML_STRING | XML_REQUIRED],
									'percent_right' =>			['type' => XML_STRING | XML_REQUIRED],
									// The tag 'ymin_type_1' should be validated before the 'ymin_item_1' because it is used in 'ex_validate' method.
									'ymin_type_1' =>			['type' => XML_STRING | XML_REQUIRED],
									'ymin_item_1' =>			['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMinItem']],
									// The tag 'ymax_type_1' should be validated before the 'ymax_item_1' because it is used in 'ex_validate' method.
									'ymax_type_1' =>			['type' => XML_STRING | XML_REQUIRED],
									'ymax_item_1' =>			['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMaxItem']],
									'graph_items' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => [
										'graph_item' =>				['type' => XML_ARRAY, 'rules' => [
											'sortorder' =>				['type' => XML_STRING | XML_REQUIRED],
											'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
											'color' =>					['type' => XML_STRING | XML_REQUIRED],
											'yaxisside' =>				['type' => XML_STRING | XML_REQUIRED],
											'calc_fnc' =>				['type' => XML_STRING | XML_REQUIRED],
											'type' =>					['type' => XML_STRING | XML_REQUIRED],
											'item' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'host' =>					['type' => XML_STRING | XML_REQUIRED],
												'key' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]]
								]]
							]],
							'host_prototypes' =>		['type' => XML_INDEXED_ARRAY, 'prefix' => 'host_prototype', 'rules' => [
								'host_prototype' =>			['type' => XML_ARRAY, 'rules' => [
									'host' =>					['type' => XML_STRING | XML_REQUIRED],
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'status' =>					['type' => XML_STRING | XML_REQUIRED],
									'group_links' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group_link', 'rules' => [
										'group_link' =>				['type' => XML_ARRAY, 'rules' => [
											'group' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
												'name' =>					['type' => XML_STRING | XML_REQUIRED]
											]]
										]]
									]],
									'group_prototypes' =>		['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group_prototype', 'rules' => [
										'group_prototype' =>		['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]],
									'templates' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'template', 'rules' => [
										'template' =>				['type' => XML_ARRAY, 'rules' => [
											'name' =>					['type' => XML_STRING | XML_REQUIRED]
										]]
									]]
								]]
							]]
						]]
					]],
					'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
						'macro' =>				['type' => XML_ARRAY, 'rules' => [
							'macro' =>				['type' => XML_STRING | XML_REQUIRED],
							'value' =>				['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'screens' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => [
						'screen' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'hsize' =>					['type' => XML_STRING | XML_REQUIRED],
							'vsize' =>					['type' => XML_STRING | XML_REQUIRED],
							'screen_items' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'screen_item', 'rules' => [
								'screen_item' =>			['type' => XML_ARRAY, 'rules' => [
									// The tag 'resourcetype' should be validated before the 'resource' because it is used in 'ex_required' and 'ex_validate' methods.
									'resourcetype' =>			['type' => XML_STRING | XML_REQUIRED],
									'resource' =>				['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateScreenItemResource']],
									'width' =>					['type' => XML_STRING | XML_REQUIRED],
									'height' =>					['type' => XML_STRING | XML_REQUIRED],
									'x' =>						['type' => XML_STRING | XML_REQUIRED],
									'y' =>						['type' => XML_STRING | XML_REQUIRED],
									'colspan' =>				['type' => XML_STRING | XML_REQUIRED],
									'rowspan' =>				['type' => XML_STRING | XML_REQUIRED],
									'elements' =>				['type' => XML_STRING | XML_REQUIRED],
									'valign' =>					['type' => XML_STRING | XML_REQUIRED],
									'halign' =>					['type' => XML_STRING | XML_REQUIRED],
									'style' =>					['type' => XML_STRING | XML_REQUIRED],
									'dynamic' =>				['type' => XML_STRING | XML_REQUIRED],
									'sort_triggers' =>			['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING],
									'application' =>			['type' => XML_STRING],
									'max_columns' =>			['type' => XML_STRING]
								]]
							]]
						]]
					]]
				]]
			]],
			'triggers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
				'trigger' =>				['type' => XML_ARRAY, 'rules' => [
					'expression' =>				['type' => XML_STRING | XML_REQUIRED],
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'url' =>					['type' => XML_STRING | XML_REQUIRED],
					'status' =>					['type' => XML_STRING | XML_REQUIRED],
					'priority' =>				['type' => XML_STRING | XML_REQUIRED],
					'description' =>			['type' => XML_STRING | XML_REQUIRED],
					'type' =>					['type' => XML_STRING | XML_REQUIRED],
					'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
						'dependency' =>				['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'expression' =>				['type' => XML_STRING | XML_REQUIRED]
						]]
					]]
				]]
			]],
			'graphs' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => [
				'graph' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'width' =>					['type' => XML_STRING | XML_REQUIRED],
					'height' =>					['type' => XML_STRING | XML_REQUIRED],
					'yaxismin' =>				['type' => XML_STRING | XML_REQUIRED],
					'yaxismax' =>				['type' => XML_STRING | XML_REQUIRED],
					'show_work_period' =>		['type' => XML_STRING | XML_REQUIRED],
					'show_triggers' =>			['type' => XML_STRING | XML_REQUIRED],
					'type' =>					['type' => XML_STRING | XML_REQUIRED],
					'show_legend' =>			['type' => XML_STRING | XML_REQUIRED],
					'show_3d' =>				['type' => XML_STRING | XML_REQUIRED],
					'percent_left' =>			['type' => XML_STRING | XML_REQUIRED],
					'percent_right' =>			['type' => XML_STRING | XML_REQUIRED],
					// The tag 'ymin_type_1' should be validated before the 'ymin_item_1' because it is used in 'ex_validate' method.
					'ymin_type_1' =>			['type' => XML_STRING | XML_REQUIRED],
					'ymin_item_1' =>			['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMinItem']],
					// The tag 'ymax_type_1' should be validated before the 'ymax_item_1' because it is used in 'ex_validate' method.
					'ymax_type_1' =>			['type' => XML_STRING | XML_REQUIRED],
					'ymax_item_1' =>			['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateYMaxItem']],
					'graph_items' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_item', 'rules' => [
						'graph_item' =>				['type' => XML_ARRAY, 'rules' => [
							'sortorder' =>				['type' => XML_STRING | XML_REQUIRED],
							'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'color' =>					['type' => XML_STRING | XML_REQUIRED],
							'yaxisside' =>				['type' => XML_STRING | XML_REQUIRED],
							'calc_fnc' =>				['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'item' =>					['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'host' =>					['type' => XML_STRING | XML_REQUIRED],
								'key' =>					['type' => XML_STRING | XML_REQUIRED]
							]]
						]]
					]]
				]]
			]],
			'screens' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => [
				'screen' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'hsize' =>					['type' => XML_STRING | XML_REQUIRED],
					'vsize' =>					['type' => XML_STRING | XML_REQUIRED],
					'screen_items' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'screen_item', 'rules' => [
						'screen_item' =>			['type' => XML_ARRAY, 'rules' => [
							// The tag 'resourcetype' should be validated before the 'resource' because it is used in 'ex_required' and 'ex_validate' methods.
							'resourcetype' =>			['type' => XML_STRING | XML_REQUIRED],
							'resource' =>				['type' => XML_REQUIRED, 'preprocessor' => [$this, 'transformZero2Array'], 'ex_validate' => [$this, 'validateScreenItemResource']],
							'width' =>					['type' => XML_STRING | XML_REQUIRED],
							'height' =>					['type' => XML_STRING | XML_REQUIRED],
							'x' =>						['type' => XML_STRING | XML_REQUIRED],
							'y' =>						['type' => XML_STRING | XML_REQUIRED],
							'colspan' =>				['type' => XML_STRING | XML_REQUIRED],
							'rowspan' =>				['type' => XML_STRING | XML_REQUIRED],
							'elements' =>				['type' => XML_STRING | XML_REQUIRED],
							'valign' =>					['type' => XML_STRING | XML_REQUIRED],
							'halign' =>					['type' => XML_STRING | XML_REQUIRED],
							'style' =>					['type' => XML_STRING | XML_REQUIRED],
							'dynamic' =>				['type' => XML_STRING | XML_REQUIRED],
							'sort_triggers' =>			['type' => XML_STRING | XML_REQUIRED],
							'url' =>					['type' => XML_STRING],
							'application' =>			['type' => XML_STRING],
							'max_columns' =>			['type' => XML_STRING]
						]]
					]]
				]]
			]],
			'images' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'image', 'rules' => [
				'image' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'imagetype' =>				['type' => XML_STRING | XML_REQUIRED],
					'encodedImage' =>			['type' => XML_STRING | XML_REQUIRED]
				]]
			]],
			'maps' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'map', 'rules' => [
				'map' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'width' =>					['type' => XML_STRING | XML_REQUIRED],
					'height' =>					['type' => XML_STRING | XML_REQUIRED],
					'label_type' =>				['type' => XML_STRING | XML_REQUIRED],
					'label_location' =>			['type' => XML_STRING | XML_REQUIRED],
					'highlight' =>				['type' => XML_STRING | XML_REQUIRED],
					'expandproblem' =>			['type' => XML_STRING | XML_REQUIRED],
					'markelements' =>			['type' => XML_STRING | XML_REQUIRED],
					'show_unack' =>				['type' => XML_STRING | XML_REQUIRED],
					'severity_min' =>			['type' => XML_STRING],
					'grid_size' =>				['type' => XML_STRING | XML_REQUIRED],
					'grid_show' =>				['type' => XML_STRING | XML_REQUIRED],
					'grid_align' =>				['type' => XML_STRING | XML_REQUIRED],
					'label_format' =>			['type' => XML_STRING | XML_REQUIRED],
					'label_type_host' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_type_hostgroup' =>	['type' => XML_STRING | XML_REQUIRED],
					'label_type_trigger' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_type_map' =>			['type' => XML_STRING | XML_REQUIRED],
					'label_type_image' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_string_host' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_string_hostgroup' =>	['type' => XML_STRING | XML_REQUIRED],
					'label_string_trigger' =>	['type' => XML_STRING | XML_REQUIRED],
					'label_string_map' =>		['type' => XML_STRING | XML_REQUIRED],
					'label_string_image' =>		['type' => XML_STRING | XML_REQUIRED],
					'expand_macros' =>			['type' => XML_STRING | XML_REQUIRED],
					'background' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
						'name' =>					['type' => XML_STRING]
					]],
					'iconmap' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
						'name' =>					['type' => XML_STRING]
					]],
					'urls' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'url', 'rules' => [
						'url' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'url' =>					['type' => XML_STRING | XML_REQUIRED],
							'elementtype' =>			['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'selements' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'selement', 'rules' => [
						'selement' =>				['type' => XML_ARRAY, 'rules' => [
							// The tag 'elementtype' should be validated before the 'element' because it is used in 'ex_required' and 'ex_validate' methods.
							'elementtype' =>			['type' => XML_STRING | XML_REQUIRED],
							'element' =>				['type' => 0, 'ex_required' => [$this, 'requiredMapElement'], 'ex_validate' => [$this, 'validateMapElement']],
							'label' =>					['type' => XML_STRING | XML_REQUIRED],
							'label_location' =>			['type' => XML_STRING | XML_REQUIRED],
							'x' =>						['type' => XML_STRING | XML_REQUIRED],
							'y' =>						['type' => XML_STRING | XML_REQUIRED],
							'elementsubtype' =>			['type' => XML_STRING | XML_REQUIRED],
							'areatype' =>				['type' => XML_STRING | XML_REQUIRED],
							'width' =>					['type' => XML_STRING | XML_REQUIRED],
							'height' =>					['type' => XML_STRING | XML_REQUIRED],
							'viewtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'use_iconmap' =>			['type' => XML_STRING | XML_REQUIRED],
							'selementid' =>				['type' => XML_STRING | XML_REQUIRED],
							'icon_off' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'icon_on' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'icon_disabled' =>			['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'icon_maintenance' =>		['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING]
							]],
							'application' =>			['type' => XML_STRING],
							'urls' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'url', 'rules' => [
								'url' =>					['type' => XML_ARRAY, 'rules' => [
									'name' =>					['type' => XML_STRING | XML_REQUIRED],
									'url' =>					['type' => XML_STRING | XML_REQUIRED]
								]]
							]]
						]]
					]],
					'links' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'link', 'rules' => [
						'link' =>					['type' => XML_ARRAY, 'rules' => [
							'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'color' =>					['type' => XML_STRING | XML_REQUIRED],
							'label' =>					['type' => XML_STRING | XML_REQUIRED],
							'selementid1' =>			['type' => XML_STRING | XML_REQUIRED],
							'selementid2' =>			['type' => XML_STRING | XML_REQUIRED],
							'linktriggers' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'linktrigger', 'rules' => [
								'linktrigger' =>			['type' => XML_ARRAY, 'rules' => [
									'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
									'color' =>					['type' => XML_STRING | XML_REQUIRED],
									'trigger' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
										'description' =>			['type' => XML_STRING | XML_REQUIRED],
										'expression' =>				['type' => XML_STRING | XML_REQUIRED]
									]]
								]]
							]]
						]]
					]]
				]]
			]]
		]];

		return (new CXmlValidatorGeneral($rules))->validate($data, $path);
	}

	/**
	 * Validate date and time format.
	 *
	 * @param string $data			import data
	 * @param array  $parent_data	data's parent array
	 * @param string $path			XML path (for error reporting)
	 *
	 * @throws Exception			if the date or time is invalid
	 */
	public function validateDateTime($data, array $parent_data = null, $path) {
		if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[01])T(2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]Z$/', $data)) {
			throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('YYYY-MM-DDThh:mm:ssZ', 'XML date and time format'))));
		}

		return $data;
	}

	/**
	 * Validate the "discovery_rule/filter" tag.
	 *
	 * @param string $data			import data
	 * @param array  $parent_data	data's parent array
	 * @param string $path			XML path (for error reporting)
	 *
	 * @throws Exception			if the filter is invalid
	 */
	public function validateFilter($data, array $parent_data = null, $path) {
		if (is_array($data)) {
			$rules = ['type' => XML_ARRAY, 'rules' => [
				'evaltype' =>	['type' => XML_STRING | XML_REQUIRED],
				'formula' =>	['type' => XML_STRING | XML_REQUIRED],
				'conditions' =>	['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'condition', 'rules' => [
					'condition' =>	['type' => XML_ARRAY, 'rules' => [
						'macro' =>		['type' => XML_STRING | XML_REQUIRED],
						'value' =>		['type' => XML_STRING | XML_REQUIRED],
						'operator' =>	['type' => XML_STRING | XML_REQUIRED],
						'formulaid' =>	['type' => XML_STRING | XML_REQUIRED]
					]]
				]]
			]];

			$data = (new CXmlValidatorGeneral($rules))->validate($data, $path);
		}

		return $data;
	}

	/**
	 * Checking the map element for requirement.
	 *
	 * @param array  $parent_data	data's parent array
	 *
	 * @throws Exception			if the check is failed
	 */
	public function requiredMapElement(array $parent_data = null) {
		if (zbx_is_int($parent_data['elementtype'])) {
			switch ($parent_data['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST:
				case SYSMAP_ELEMENT_TYPE_MAP:
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					return true;
			}
		}

		return false;
	}

	/**
	 * Validate map element.
	 *
	 * @param string $data			import data
	 * @param array  $parent_data	data's parent array
	 * @param string $path			XML path
	 *
	 * @throws Exception			if the map element is invalid
	 */
	public function validateMapElement($data, array $parent_data = null, $path) {
		if (zbx_is_int($parent_data['elementtype'])) {
			switch ($parent_data['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'host' =>			['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				case SYSMAP_ELEMENT_TYPE_MAP:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'name' =>			['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'description' =>	['type' => XML_STRING | XML_REQUIRED],
						'expression' =>		['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'name' =>			['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				default:
					return $data;
			}

			$data = (new CXmlValidatorGeneral($rules))->validate($data, $path);
		}

		return $data;
	}

	/**
	 * Validate "screen_item/resource" tag.
	 *
	 * @param string $data			import data
	 * @param array  $parent_data	data's parent array
	 * @param string $path			XML path
	 *
	 * @throws Exception			if the map element is invalid
	 */
	public function validateScreenItemResource($data, array $parent_data = null, $path) {
		if (zbx_is_int($parent_data['resourcetype'])) {
			switch ($parent_data['resourcetype']) {
				case SCREEN_RESOURCE_GRAPH:
				case SCREEN_RESOURCE_LLD_GRAPH:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'name' =>			['type' => XML_STRING | XML_REQUIRED],
						'host' =>			['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'key' =>			['type' => XML_STRING | XML_REQUIRED],
						'host' =>			['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				case SCREEN_RESOURCE_MAP:
				case SCREEN_RESOURCE_SCREEN:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'name' =>			['type' => XML_STRING | XML_REQUIRED]
					]];
					break;

				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'name' =>			['type' => XML_STRING]
					]];
					break;

				case SCREEN_RESOURCE_HOST_TRIGGERS:
					$rules = ['type' => XML_ARRAY, 'rules' => [
						'host' =>			['type' => XML_STRING]
					]];
					break;

				default:
					return $data;
			}

			$data = (new CXmlValidatorGeneral($rules))->validate($data, $path);
		}

		return $data;
	}

	/**
	 * Validate "ymin_item_1" tag.
	 *
	 * @param string $data			import data
	 * @param array  $parent_data	data's parent array
	 * @param string $path			XML path
	 *
	 * @throws Exception			if the element is invalid
	 */
	public function validateYMinItem($data, array $parent_data = null, $path) {
		if (zbx_is_int($parent_data['ymin_type_1'])) {
			if ($parent_data['ymin_type_1'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				$rules = ['type' => XML_ARRAY, 'rules' => [
					'host' =>	['type' => XML_STRING | XML_REQUIRED],
					'key' =>	['type' => XML_STRING | XML_REQUIRED]
				]];

				$data = (new CXmlValidatorGeneral($rules))->validate($data, $path);
			}
		}

		return $data;
	}

	/**
	 * Validate "ymax_item_1" tag.
	 *
	 * @param string $data			import data
	 * @param array  $parent_data	data's parent array
	 * @param string $path			XML path
	 *
	 * @throws Exception			if the element is invalid
	 */
	public function validateYMaxItem($data, array $parent_data = null, $path) {
		if (zbx_is_int($parent_data['ymax_type_1'])) {
			if ($parent_data['ymax_type_1'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
				$rules = ['type' => XML_ARRAY, 'rules' => [
					'host' =>	['type' => XML_STRING | XML_REQUIRED],
					'key' =>	['type' => XML_STRING | XML_REQUIRED]
				]];

				$data = (new CXmlValidatorGeneral($rules))->validate($data, $path);
			}
		}

		return $data;
	}

	/**
	 * Transforms tags containing zero into an empty array
	 *
	 * @param mixed $value
	 *
	 * @return mixed		converted value
	 */
	public function transformZero2Array($value) {
		return ($value === '0') ? [] : $value;
	}
}
