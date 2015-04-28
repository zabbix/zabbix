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
			array('type' => self::XML_ARRAY, 'rules' => array(
				'version' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
				'date' =>					array('type' => self::XML_STRING, 'ex_validate' => array($this, 'validateDate')),
				'time' =>					array('type' => self::XML_STRING, 'ex_validate' => array($this, 'validateTime')),
				'hosts' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'host', 'rules' => array(
					'host' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'proxy_hostid' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'useip' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'dns' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'ip' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
						'port' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'useipmi' =>				array('type' => self::XML_STRING),
						'ipmi_ip' =>				array('type' => self::XML_STRING),
						'ipmi_port' =>				array('type' => self::XML_STRING),
						'ipmi_authtype' =>			array('type' => self::XML_STRING),
						'ipmi_privilege' =>			array('type' => self::XML_STRING),
						'ipmi_username' =>			array('type' => self::XML_STRING),
						'ipmi_password' =>			array('type' => self::XML_STRING),
						'groups' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'group', 'rules' => array(
							'group' =>					array('type' => self::XML_STRING)
						)),
						'items' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'item', 'rules' => array(
							'item' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'key' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'value_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ipmi_sensor' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'history' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'trends' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'data_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'units' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'multiplier' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delta' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'formula' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'lastlogsize' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'logtimefmt' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'delay_flex' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'authtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'username' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'password' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'publickey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'privatekey' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'params' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'trapper_hosts' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_community' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_oid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmp_port' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_securityname' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_securitylevel' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_authpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'snmpv3_privpassphrase' =>	array('type' => self::XML_STRING | self::XML_REQUIRED),
								'valuemapid' =>				array('type' => self::XML_STRING),
								'applications' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'application', 'rules' => array(
									'application' =>			array('type' => self::XML_STRING)
								))
							))
						)),
						'triggers' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'trigger', 'rules' => array(
							'trigger' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'url' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'status' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'priority' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'comments' =>				array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						)),
						'templates' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'template', 'rules' => array(
							'template' =>				array('type' => self::XML_STRING)
						)),
						'graphs' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'graph', 'rules' => array(
							'graph' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ymin_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ymax_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ymin_item_key' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'ymax_item_key' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'show_work_period' =>		array('type' => self::XML_STRING | self::XML_REQUIRED),
								'show_triggers' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'graphtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'yaxismin' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'yaxismax' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'show_legend' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'show_3d' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'percent_left' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'percent_right' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'graph_elements' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'graph_element', 'rules' => array(
									'graph_element' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'item' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'sortorder' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'yaxisside' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'calc_fnc' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'type' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'periods_cnt' =>			array('type' => self::XML_STRING | self::XML_REQUIRED)
									))
								))
							))
						)),
						'macros' =>					array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'macro', 'rules' => array(
							'macro' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'value' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
							))
						))
					))
				)),
				'dependencies' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'dependency', 'rules' => array(
					'dependency' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'depends', 'extra' => 'description', 'rules' => array(
						'depends' =>				array('type' => self::XML_STRING),
						'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED)
					))
				)),
				'sysmaps' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'sysmap', 'rules' => array(
					'sysmap' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'selements' =>				array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'selement', 'rules' => array(
							'selement' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'selementid' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'elementid' =>				array('type' => self::XML_ARRAY, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING),
									'host' =>					array('type' => self::XML_STRING),
									'description' =>			array('type' => self::XML_STRING),
									'expression' =>				array('type' => self::XML_STRING)
								)),
								'elementtype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'iconid_on' =>				array('type' => self::XML_ARRAY, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'iconid_off' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'iconid_unknown' =>			array('type' => self::XML_ARRAY, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'iconid_disabled' =>		array('type' => self::XML_ARRAY, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'iconid_maintenance' =>		array('type' => self::XML_ARRAY, 'rules' => array(
									'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
								)),
								'label' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'label_location' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'x' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
								'y' =>						array('type' => self::XML_STRING | self::XML_REQUIRED),
								'url' =>					array('type' => self::XML_STRING)
							))
						)),
						'links' =>					array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'link', 'rules' => array(
							'link' =>					array('type' => self::XML_ARRAY, 'rules' => array(
								'selementid1' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'selementid2' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
								'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
								'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
								'label' =>					array('type' => self::XML_STRING),
								'linktriggers' =>			array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'linktrigger', 'rules' => array(
									'linktrigger' =>			array('type' => self::XML_ARRAY, 'rules' => array(
										'drawtype' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										'color' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
										'triggerid' =>				array('type' => self::XML_ARRAY | self::XML_REQUIRED, 'rules' => array(
											'host' =>					array('type' => self::XML_STRING),
											'description' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
											'expression' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
										))
									))
								))
							))
						)),
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'width' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'height' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'backgroundid' =>			array('type' => self::XML_ARRAY, 'rules' => array(
							'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED)
						)),
						'label_type' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'label_location' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'highlight' =>				array('type' => self::XML_STRING | self::XML_REQUIRED),
						'expandproblem' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'markelements' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
						'show_unack' =>				array('type' => self::XML_STRING | self::XML_REQUIRED)
					))
				)),
				'screens' =>				array('type' => self::XML_INDEXED_ARRAY, 'prefix' => 'screen', 'rules' => array(
					'screen' =>					array('type' => self::XML_ARRAY, 'rules' => array(
						'name' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'hsize' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'vsize' =>					array('type' => self::XML_STRING | self::XML_REQUIRED),
						'screenitems' =>			array('type' => self::XML_INDEXED_ARRAY | self::XML_REQUIRED, 'prefix' => 'screenitem', 'rules' => array(
							'screenitem' =>				array('type' => self::XML_ARRAY, 'rules' => array(
								'resourcetype' =>			array('type' => self::XML_STRING | self::XML_REQUIRED),
/* TYPE: mixed */				'resourceid' =>				array('type' => self::XML_REQUIRED),
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
								'url' =>					array('type' => self::XML_STRING)
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
				))
			))
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('DD.MM.YY', 'XML date format'))));
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, _s('"%1$s" is expected', _x('hh.mm', 'XML time format'))));
		}
	}
}
