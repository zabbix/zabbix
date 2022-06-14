<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Validate import data from Zabbix 1.8.
 */
class C10XmlValidator extends CXmlValidatorGeneral {

	/**
	 * Base validation function.
	 *
	 * @param array  $data  Import data.
	 * @param string $path  XML path (for error reporting).
	 *
	 * @return array        Validator does some manipulation for the incoming data. For example, converts empty tags to
	 *                      an array, if desired. Converted array is returned.
	 */
	public function validate(array $data, string $path) {
		$rules = ['type' => XML_ARRAY, 'rules' => [
			'version' =>				['type' => XML_STRING | XML_REQUIRED],
			'date' =>					['type' => XML_STRING, 'ex_validate' => [$this, 'validateDate']],
			'time' =>					['type' => XML_STRING, 'ex_validate' => [$this, 'validateTime']],
			'hosts' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => [
				'host' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'proxy_hostid' =>			['type' => XML_STRING],
					'useip' =>					['type' => XML_STRING | XML_REQUIRED],
					'dns' =>					['type' => XML_STRING | XML_REQUIRED],
					'ip' =>						['type' => XML_STRING | XML_REQUIRED],
					'port' =>					['type' => XML_STRING | XML_REQUIRED],
					'status' =>					['type' => XML_STRING | XML_REQUIRED],
					'useipmi' =>				['type' => XML_STRING],
					'ipmi_ip' =>				['type' => XML_STRING],
					'ipmi_port' =>				['type' => XML_STRING],
					'ipmi_authtype' =>			['type' => XML_STRING],
					'ipmi_privilege' =>			['type' => XML_STRING],
					'ipmi_username' =>			['type' => XML_STRING],
					'ipmi_password' =>			['type' => XML_STRING],
					'groups' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'group', 'rules' => [
						'group' =>					['type' => XML_STRING]
					]],
					'items' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
						'item' =>					['type' => XML_ARRAY, 'rules' => [
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'key' =>					['type' => XML_STRING | XML_REQUIRED],
							'value_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'ipmi_sensor' =>			['type' => XML_STRING | XML_REQUIRED],
							'delay' =>					['type' => XML_STRING | XML_REQUIRED],
							'history' =>				['type' => XML_STRING | XML_REQUIRED],
							'trends' =>					['type' => XML_STRING | XML_REQUIRED],
							'status' =>					['type' => XML_STRING | XML_REQUIRED],
							'data_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'units' =>					['type' => XML_STRING | XML_REQUIRED],
							'multiplier' =>				['type' => XML_STRING | XML_REQUIRED],
							'delta' =>					['type' => XML_STRING | XML_REQUIRED],
							'formula' =>				['type' => XML_STRING | XML_REQUIRED],
							'lastlogsize' =>			['type' => XML_STRING | XML_REQUIRED],
							'logtimefmt' =>				['type' => XML_STRING | XML_REQUIRED],
							'delay_flex' =>				['type' => XML_STRING | XML_REQUIRED],
							'authtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'username' =>				['type' => XML_STRING | XML_REQUIRED],
							'password' =>				['type' => XML_STRING | XML_REQUIRED],
							'publickey' =>				['type' => XML_STRING | XML_REQUIRED],
							'privatekey' =>				['type' => XML_STRING | XML_REQUIRED],
							'params' =>					['type' => XML_STRING | XML_REQUIRED],
							'trapper_hosts' =>			['type' => XML_STRING | XML_REQUIRED],
							'snmp_community' =>			['type' => XML_STRING | XML_REQUIRED],
							'snmp_oid' =>				['type' => XML_STRING | XML_REQUIRED],
							'snmp_port' =>				['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_securityname' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_securitylevel' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_authpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'snmpv3_privpassphrase' =>	['type' => XML_STRING | XML_REQUIRED],
							'valuemapid' =>				['type' => XML_STRING],
							'applications' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'application', 'rules' => [
								'application' =>			['type' => XML_STRING]
							]]
						]]
					]],
					'triggers' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
						'trigger' =>				['type' => XML_ARRAY, 'rules' => [
							'description' =>			['type' => XML_STRING | XML_REQUIRED],
							'type' =>					['type' => XML_STRING | XML_REQUIRED],
							'expression' =>				['type' => XML_STRING | XML_REQUIRED],
							'url' =>					['type' => XML_STRING | XML_REQUIRED],
							'status' =>					['type' => XML_STRING | XML_REQUIRED],
							'priority' =>				['type' => XML_STRING | XML_REQUIRED],
							'comments' =>				['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'templates' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
						'template' =>				['type' => XML_STRING]
					]],
					'graphs' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => [
						'graph' =>					['type' => XML_ARRAY, 'rules' => [
							'name' =>					['type' => XML_STRING | XML_REQUIRED],
							'width' =>					['type' => XML_STRING | XML_REQUIRED],
							'height' =>					['type' => XML_STRING | XML_REQUIRED],
							// The tag 'ymin_type' should be validated before the 'ymin_item_key' because it is used in 'ex_validate' method.
							'ymin_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'ymin_item_key' =>			['type' => XML_STRING | XML_REQUIRED, 'ex_validate' => [$this, 'validateYMinItem']],
							// The tag 'ymax_type' should be validated before the 'ymax_item_key' because it is used in 'ex_validate' method.
							'ymax_type' =>				['type' => XML_STRING | XML_REQUIRED],
							'ymax_item_key' =>			['type' => XML_STRING | XML_REQUIRED, 'ex_validate' => [$this, 'validateYMaxItem']],
							'show_work_period' =>		['type' => XML_STRING | XML_REQUIRED],
							'show_triggers' =>			['type' => XML_STRING | XML_REQUIRED],
							'graphtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'yaxismin' =>				['type' => XML_STRING | XML_REQUIRED],
							'yaxismax' =>				['type' => XML_STRING | XML_REQUIRED],
							'show_legend' =>			['type' => XML_STRING | XML_REQUIRED],
							'show_3d' =>				['type' => XML_STRING | XML_REQUIRED],
							'percent_left' =>			['type' => XML_STRING | XML_REQUIRED],
							'percent_right' =>			['type' => XML_STRING | XML_REQUIRED],
							'graph_elements' =>			['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'graph_element', 'rules' => [
								'graph_element' =>			['type' => XML_ARRAY, 'rules' => [
									'item' =>					['type' => XML_STRING | XML_REQUIRED, 'ex_validate' => [$this, 'validateGraphItem']],
									'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
									'sortorder' =>				['type' => XML_STRING | XML_REQUIRED],
									'color' =>					['type' => XML_STRING | XML_REQUIRED],
									'yaxisside' =>				['type' => XML_STRING | XML_REQUIRED],
									'calc_fnc' =>				['type' => XML_STRING | XML_REQUIRED],
									'type' =>					['type' => XML_STRING | XML_REQUIRED],
									'periods_cnt' =>			['type' => XML_STRING | XML_REQUIRED]
								]]
							]]
						]]
					]],
					'macros' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
						'macro' =>					['type' => XML_ARRAY, 'rules' => [
							'value' =>					['type' => XML_STRING | XML_REQUIRED],
							'name' =>					['type' => XML_STRING | XML_REQUIRED]
						]]
					]],
					'host_profile' =>			['type' => XML_ARRAY, 'rules' => [
						'devicetype' =>				['type' => XML_STRING | XML_REQUIRED],
						'name' =>					['type' => XML_STRING | XML_REQUIRED],
						'os' =>						['type' => XML_STRING | XML_REQUIRED],
						'serialno' =>				['type' => XML_STRING | XML_REQUIRED],
						'tag' =>					['type' => XML_STRING | XML_REQUIRED],
						'macaddress' =>				['type' => XML_STRING | XML_REQUIRED],
						'hardware' =>				['type' => XML_STRING | XML_REQUIRED],
						'software' =>				['type' => XML_STRING | XML_REQUIRED],
						'contact' =>				['type' => XML_STRING | XML_REQUIRED],
						'location' =>				['type' => XML_STRING | XML_REQUIRED],
						'notes' =>					['type' => XML_STRING | XML_REQUIRED]
					]],
					'host_profiles_ext' =>		['type' => XML_ARRAY, 'rules' => [
						'device_alias' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_type' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_chassis' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_os' =>				['type' => XML_STRING | XML_REQUIRED],
						'device_os_short' =>		['type' => XML_STRING | XML_REQUIRED],
						'device_hw_arch' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_serial' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_model' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_tag' =>				['type' => XML_STRING | XML_REQUIRED],
						'device_vendor' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_contract' =>		['type' => XML_STRING | XML_REQUIRED],
						'device_who' =>				['type' => XML_STRING | XML_REQUIRED],
						'device_status' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_app_01' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_app_02' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_app_03' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_app_04' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_app_05' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_url_1' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_url_2' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_url_3' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_networks' =>		['type' => XML_STRING | XML_REQUIRED],
						'device_notes' =>			['type' => XML_STRING | XML_REQUIRED],
						'device_hardware' =>		['type' => XML_STRING | XML_REQUIRED],
						'device_software' =>		['type' => XML_STRING | XML_REQUIRED],
						'ip_subnet_mask' =>			['type' => XML_STRING | XML_REQUIRED],
						'ip_router' =>				['type' => XML_STRING | XML_REQUIRED],
						'ip_macaddress' =>			['type' => XML_STRING | XML_REQUIRED],
						'oob_ip' =>					['type' => XML_STRING | XML_REQUIRED],
						'oob_subnet_mask' =>		['type' => XML_STRING | XML_REQUIRED],
						'oob_router' =>				['type' => XML_STRING | XML_REQUIRED],
						'date_hw_buy' =>			['type' => XML_STRING | XML_REQUIRED],
						'date_hw_install' =>		['type' => XML_STRING | XML_REQUIRED],
						'date_hw_expiry' =>			['type' => XML_STRING | XML_REQUIRED],
						'date_hw_decomm' =>			['type' => XML_STRING | XML_REQUIRED],
						'site_street_1' =>			['type' => XML_STRING | XML_REQUIRED],
						'site_street_2' =>			['type' => XML_STRING | XML_REQUIRED],
						'site_street_3' =>			['type' => XML_STRING | XML_REQUIRED],
						'site_city' =>				['type' => XML_STRING | XML_REQUIRED],
						'site_state' =>				['type' => XML_STRING | XML_REQUIRED],
						'site_country' =>			['type' => XML_STRING | XML_REQUIRED],
						'site_zip' =>				['type' => XML_STRING | XML_REQUIRED],
						'site_rack' =>				['type' => XML_STRING | XML_REQUIRED],
						'site_notes' =>				['type' => XML_STRING | XML_REQUIRED],
						'poc_1_name' =>				['type' => XML_STRING | XML_REQUIRED],
						'poc_1_email' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_1_phone_1' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_1_phone_2' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_1_cell' =>				['type' => XML_STRING | XML_REQUIRED],
						'poc_1_screen' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_1_notes' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_2_name' =>				['type' => XML_STRING | XML_REQUIRED],
						'poc_2_email' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_2_phone_1' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_2_phone_2' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_2_cell' =>				['type' => XML_STRING | XML_REQUIRED],
						'poc_2_screen' =>			['type' => XML_STRING | XML_REQUIRED],
						'poc_2_notes' =>			['type' => XML_STRING | XML_REQUIRED]
					]]
				]]
			]],
			'dependencies' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
				'dependency' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'depends', 'extra' => 'description', 'rules' => [
					'depends' =>				['type' => XML_STRING],
					'description' =>			['type' => XML_STRING | XML_REQUIRED]
				]]
			]],
			'sysmaps' =>				['type' => XML_INDEXED_ARRAY, 'prefix' => 'sysmap', 'rules' => [
				'sysmap' =>					['type' => XML_ARRAY, 'rules' => [
					'selements' =>				['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'selement', 'rules' => [
						'selement' =>				['type' => XML_ARRAY, 'rules' => [
							'selementid' =>				['type' => XML_STRING | XML_REQUIRED],
							// The tag 'elementtype' should be validated before the 'elementid' because it is used in 'ex_required' and 'ex_validate' methods.
							'elementtype' =>			['type' => XML_STRING | XML_REQUIRED],
							'elementid' =>				['type' => 0, 'ex_required' => [$this, 'requiredMapElement'], 'ex_validate' => [$this, 'validateMapElement']],
							'iconid_on' =>				['type' => XML_ARRAY, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'iconid_off' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'iconid_unknown' =>			['type' => XML_ARRAY, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'iconid_disabled' =>		['type' => XML_ARRAY, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'iconid_maintenance' =>		['type' => XML_ARRAY, 'rules' => [
								'name' =>					['type' => XML_STRING | XML_REQUIRED]
							]],
							'label' =>					['type' => XML_STRING | XML_REQUIRED],
							'label_location' =>			['type' => XML_STRING],
							'x' =>						['type' => XML_STRING | XML_REQUIRED],
							'y' =>						['type' => XML_STRING | XML_REQUIRED],
							'url' =>					['type' => XML_STRING]
						]]
					]],
					'links' =>					['type' => XML_INDEXED_ARRAY | XML_REQUIRED, 'prefix' => 'link', 'rules' => [
						'link' =>					['type' => XML_ARRAY, 'rules' => [
							'selementid1' =>			['type' => XML_STRING | XML_REQUIRED],
							'selementid2' =>			['type' => XML_STRING | XML_REQUIRED],
							'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
							'color' =>					['type' => XML_STRING | XML_REQUIRED],
							'label' =>					['type' => XML_STRING],
							'linktriggers' =>			['type' => XML_INDEXED_ARRAY, 'prefix' => 'linktrigger', 'rules' => [
								'linktrigger' =>			['type' => XML_ARRAY, 'rules' => [
									'drawtype' =>				['type' => XML_STRING | XML_REQUIRED],
									'color' =>					['type' => XML_STRING | XML_REQUIRED],
									'triggerid' =>				['type' => XML_ARRAY | XML_REQUIRED, 'rules' => [
										'host' =>					['type' => XML_STRING],
										'description' =>			['type' => XML_STRING | XML_REQUIRED],
										'expression' =>				['type' => XML_STRING | XML_REQUIRED]
									]]
								]]
							]]
						]]
					]],
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'width' =>					['type' => XML_STRING | XML_REQUIRED],
					'height' =>					['type' => XML_STRING | XML_REQUIRED],
					'backgroundid' =>			['type' => XML_ARRAY, 'rules' => [
						'name' =>					['type' => XML_STRING | XML_REQUIRED]
					]],
					'label_type' =>				['type' => XML_STRING | XML_REQUIRED],
					'label_location' =>			['type' => XML_STRING | XML_REQUIRED],
					'highlight' =>				['type' => XML_STRING | XML_REQUIRED],
					'expandproblem' =>			['type' => XML_STRING | XML_REQUIRED],
					'markelements' =>			['type' => XML_STRING | XML_REQUIRED],
					'show_unack' =>				['type' => XML_STRING | XML_REQUIRED]
				]]
			]],
			'images' =>					['type' => XML_INDEXED_ARRAY, 'prefix' => 'image', 'rules' => [
				'image' =>					['type' => XML_ARRAY, 'rules' => [
					'name' =>					['type' => XML_STRING | XML_REQUIRED],
					'imagetype' =>				['type' => XML_STRING | XML_REQUIRED],
					'encodedImage' =>			['type' => XML_STRING | XML_REQUIRED]
				]]
			]]
		]];

		return $this->doValidate($rules, $data, $path);
	}

	/**
	 * Validate date format.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the date is invalid.
	 * @return string
	 */
	public function validateDate($data, ?array $parent_data, $path) {
		if (!preg_match('/^(0[1-9]|[1-2][0-9]|3[01])\.(0[1-9]|1[0-2])\.[0-9]{2}$/', $data)) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('DD.MM.YY', 'XML date format'))));
		}

		return $data;
	}

	/**
	 * Validate time format.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if the time is invalid.
	 * @return string
	 */
	public function validateTime($data, ?array $parent_data, $path) {
		if (!preg_match('/^(2[0-3]|[01][0-9])\.[0-5][0-9]$/', $data)) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('hh.mm', 'XML time format'))));
		}

		return $data;
	}

	/**
	 * Validate Y Axis value.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if tag is invalid.
	 * @return string
	 */
	public function validateYMinItem($data, ?array $parent_data, $path) {
		if (zbx_is_int($parent_data['ymin_type']) && $parent_data['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			if (strpos($data, ':') === false) {
				throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('"host:key" pair is expected')));
			}
		}
		elseif ($data !== '') {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('an empty string is expected')));
		}

		return $data;
	}

	/**
	 * Validate Y Axis value.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if tag is invalid.
	 * @return string
	 */
	public function validateYMaxItem($data, ?array $parent_data, $path) {
		if (zbx_is_int($parent_data['ymax_type']) && $parent_data['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE) {
			if (strpos($data, ':') === false) {
				throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('"host:key" pair is expected')));
			}
		}
		elseif ($data !== '') {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('an empty string is expected')));
		}

		return $data;
	}

	/**
	 * Validate graph item.
	 *
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @throws Exception if tag is invalid.
	 * @return string
	 */
	public function validateGraphItem($data, ?array $parent_data, $path) {
		if (strpos($data, ':') === false) {
			throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path, _('"host:key" pair is expected')));
		}

		return $data;
	}

	/**
	 * Checking the map element for requirement.
	 *
	 * @param array|null $parent_data  Data's parent array.
	 *
	 * @return bool
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
	 * @param string     $data         Import data.
	 * @param array|null $parent_data  Data's parent array.
	 * @param string     $path         XML path.
	 *
	 * @return mixed
	 */
	public function validateMapElement($data, ?array $parent_data, $path) {
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
						'host' =>			['type' => XML_STRING],
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

			$data = $this->doValidate($rules, $data, $path);
		}

		return $data;
	}
}
