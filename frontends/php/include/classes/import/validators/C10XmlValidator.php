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
 * Validate import data from Zabbix 1.8.
 */
class C10XmlValidator extends CXmlValidatorGeneral {

	public function __construct() {
		parent::__construct(
			['type' => self::XML_ARRAY, 'rules' => [
				'version' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
				'date' =>					['type' => self::XML_STRING, 'ex_validate' => [$this, 'validateDate']],
				'time' =>					['type' => self::XML_STRING, 'ex_validate' => [$this, 'validateTime']],
				'hosts' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => [
					'host' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'proxy_hostid' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'useip' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'dns' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'ip' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
						'port' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'useipmi' =>				['type' => self::XML_STRING],
						'ipmi_ip' =>				['type' => self::XML_STRING],
						'ipmi_port' =>				['type' => self::XML_STRING],
						'ipmi_authtype' =>			['type' => self::XML_STRING],
						'ipmi_privilege' =>			['type' => self::XML_STRING],
						'ipmi_username' =>			['type' => self::XML_STRING],
						'ipmi_password' =>			['type' => self::XML_STRING],
						'groups' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group', 'rules' => [
							'group' =>					['type' => self::XML_STRING]
						]],
						'items' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => [
							'item' =>					['type' => self::XML_ARRAY, 'rules' => [
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'key' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'value_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'ipmi_sensor' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'history' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'trends' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'data_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'units' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'multiplier' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'delta' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'formula' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'lastlogsize' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'logtimefmt' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'delay_flex' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'authtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'username' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'password' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'publickey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'privatekey' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'params' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'trapper_hosts' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_community' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_oid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmp_port' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_securityname' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_securitylevel' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_authpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'snmpv3_privpassphrase' =>	['type' => self::XML_STRING | self::XML_REQUIRED],
								'valuemapid' =>				['type' => self::XML_STRING],
								'applications' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => [
									'application' =>			['type' => self::XML_STRING]
								]]
							]]
						]],
						'triggers' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => [
							'trigger' =>				['type' => self::XML_ARRAY, 'rules' => [
								'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'url' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'status' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'priority' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'comments' =>				['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]],
						'templates' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => [
							'template' =>				['type' => self::XML_STRING]
						]],
						'graphs' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => [
							'graph' =>					['type' => self::XML_ARRAY, 'rules' => [
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'ymin_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'ymax_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'ymin_item_key' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'ymax_item_key' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'show_work_period' =>		['type' => self::XML_STRING | self::XML_REQUIRED],
								'show_triggers' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'graphtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'yaxismin' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'yaxismax' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'show_legend' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'show_3d' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'percent_left' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'percent_right' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'graph_elements' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_element', 'rules' => [
									'graph_element' =>			['type' => self::XML_ARRAY, 'rules' => [
										'item' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'sortorder' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'yaxisside' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'calc_fnc' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'type' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'periods_cnt' =>			['type' => self::XML_STRING | self::XML_REQUIRED]
									]]
								]]
							]]
						]],
						'macros' =>					['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => [
							'macro' =>					['type' => self::XML_ARRAY, 'rules' => [
								'value' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
							]]
						]]
					]]
				]],
				'dependencies' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => [
					'dependency' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'depends', 'extra' => 'description', 'rules' => [
						'depends' =>				['type' => self::XML_STRING],
						'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED]
					]]
				]],
				'sysmaps' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'sysmap', 'rules' => [
					'sysmap' =>					['type' => self::XML_ARRAY, 'rules' => [
						'selements' =>				['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'selement', 'rules' => [
							'selement' =>				['type' => self::XML_ARRAY, 'rules' => [
								'selementid' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'elementid' =>				['type' => self::XML_ARRAY, 'rules' => [
									'name' =>					['type' => self::XML_STRING],
									'host' =>					['type' => self::XML_STRING],
									'description' =>			['type' => self::XML_STRING],
									'expression' =>				['type' => self::XML_STRING]
								]],
								'elementtype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'iconid_on' =>				['type' => self::XML_ARRAY, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'iconid_off' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'iconid_unknown' =>			['type' => self::XML_ARRAY, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'iconid_disabled' =>		['type' => self::XML_ARRAY, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'iconid_maintenance' =>		['type' => self::XML_ARRAY, 'rules' => [
									'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
								]],
								'label' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'label_location' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'x' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'y' =>						['type' => self::XML_STRING | self::XML_REQUIRED],
								'url' =>					['type' => self::XML_STRING]
							]]
						]],
						'links' =>					['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'link', 'rules' => [
							'link' =>					['type' => self::XML_ARRAY, 'rules' => [
								'selementid1' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'selementid2' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
								'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
								'label' =>					['type' => self::XML_STRING],
								'linktriggers' =>			['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'linktrigger', 'rules' => [
									'linktrigger' =>			['type' => self::XML_ARRAY, 'rules' => [
										'drawtype' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										'color' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
										'triggerid' =>				['type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => [
											'host' =>					['type' => self::XML_STRING],
											'description' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
											'expression' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
										]]
									]]
								]]
							]]
						]],
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'width' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'height' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'backgroundid' =>			['type' => self::XML_ARRAY, 'rules' => [
							'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED]
						]],
						'label_type' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'label_location' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'highlight' =>				['type' => self::XML_STRING | self::XML_REQUIRED],
						'expandproblem' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'markelements' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
						'show_unack' =>				['type' => self::XML_STRING | self::XML_REQUIRED]
					]]
				]],
				'screens' =>				['type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => [
					'screen' =>					['type' => self::XML_ARRAY, 'rules' => [
						'name' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'hsize' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'vsize' =>					['type' => self::XML_STRING | self::XML_REQUIRED],
						'screenitems' =>			['type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'screenitem', 'rules' => [
							'screenitem' =>				['type' => self::XML_ARRAY, 'rules' => [
								'resourcetype' =>			['type' => self::XML_STRING | self::XML_REQUIRED],
								'resourceid' =>				['type' => self::XML_REQUIRED],
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
								'url' =>					['type' => self::XML_STRING]
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
				]]
			]]
		);
	}

	/**
	 * Validate date format.
	 *
	 * @param string $date	export date
	 * @param string $path	XML path
	 *
	 * @throws Exception	if the date is invalid
	 */
	protected function validateDate($date, $path) {
		if (!preg_match('/^(0[1-9]|[1-2][0-9]|3[01])\.(0[1-9]|1[0-2])\.[0-9]{2}$/', $date)) {
			throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('DD.MM.YY', 'XML date format'))));
		}
	}

	/**
	 * Validate time format.
	 *
	 * @param string $time	export time
	 * @param string $path	XML path
	 *
	 * @throws Exception	if the time is invalid
	 */
	protected function validateTime($time, $path) {
		if (!preg_match('/^(2[0-3]|[01][0-9])\.[0-5][0-9]$/', $time)) {
			throw new Exception(_s('Invalid XML tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('hh.mm', 'XML time format'))));
		}
	}
}
