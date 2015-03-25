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
class C10XmlValidator {

	/**
	 * @var CXmlArrayValidator
	 */
	protected $arrayValidator;

	/**
	 * Base validation function.
	 *
	 * @param array $content	import data
	 */
	public function validate($content) {
		$this->arrayValidator = new CXmlArrayValidator();

		$this->validateDate($content['date']);
		$this->validateTime($content['time']);

		// prepare content
		$newArray = new CXmlValidatorConverters();
		$content = $newArray->convertEmpStrToArr(array('hosts', 'dependencies', 'sysmaps', 'screens'), $content);

		$fields = array(
			'hosts' =>			'array',
			'dependencies' =>	'array',
			'sysmaps' =>		'array',
			'screens' =>		'array'
		);

		$validator = new CNewValidator($content, $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "zabbix_export": %1$s', $errors[0]));
		}

		$this->validateHosts($content);
		$this->validateTriggerDependencies($content);
		$this->validateSysmaps($content);
		$this->validateScreens($content);
	}

	/**
	 * Validate date format.
	 *
	 * @param string $date	export date
	 *
	 * @throws Exception	if the date is invalid
	 */
	protected function validateDate($date) {
		if (!preg_match('/^(0[1-9]|[1-2][0-9]|3[01]).(0[1-9]|1[0-2]).[0-9]{2}$/', $date)) {
			throw new Exception(_('Incorrect date format: DD.MM.YY is expected.'));
		}
	}

	/**
	 * Validate time format.
	 *
	 * @param string $time	export time
	 *
	 * @throws Exception	if the time is invalid
	 */
	protected function validateTime($time) {
		if (!preg_match('/(2[0-3]|[01][0-9]).[0-5][0-9]/', $time)) {
			throw new Exception(('Incorrect time format: HH.MM is expected.'));
		}
	}

	/**
	 * Hosts validation.
	 *
	 * @param array $content	import data
	 *
	 * @throws Exception		if structure or values is invalid
	 */
	protected function validateHosts($content) {
		if (array_key_exists('hosts', $content)) {
			if ($content['hosts']) {
				if (!$this->arrayValidator->validate('host', $content['hosts'])) {
					throw new Exception(_s('Cannot parse XML tag "zabbix_export/hosts/host(%1$s)": %2$s.',
						$this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError()
					));
				}

				$hostNumber = 1;
				foreach ($content['hosts'] as $host) {
					$arrayKeys = array('triggers', 'items', 'templates', 'graphs', 'macros');
					$newArray = new CXmlValidatorConverters();
					$host = $newArray->convertEmpStrToArr($arrayKeys, $host);

					$validationRules = array(
						'name' =>			'required|string',
						'proxy_hostid' =>	'required|string',
						'useip' =>			'required|string',
						'dns' =>			'required|string',
						'ip' =>				'required|string',
						'port' =>			'required|string',
						'status' =>			'required|string',
						'useipmi' =>		'required|string',
						'ipmi_ip' =>		'required|string',
						'ipmi_port' =>		'required|string',
						'ipmi_authtype' =>	'required|string',
						'ipmi_privilege' =>	'required|string',
						'ipmi_username' =>	'required|string',
						'ipmi_password' =>	'required|string',
						'groups' =>			'required|array',
						'triggers' =>		'required|array',
						'items' =>			'required|array',
						'templates' =>		'required|array',
						'graphs' =>			'required|array',
						'macros' =>			'required|array'
					);

					$validator = new CNewValidator($host, $validationRules);

					if ($validator->isError()) {
						$errors = $validator->getAllErrors();
						throw new Exception(_s('Cannot parse XML tag "zabbix_export/hosts/host(%1$s)": %2$s',
							$hostNumber, $errors[0]
						));
					}

					// child elements validation
					$this->validateGroups($host, $hostNumber);
					$this->validateItems($host, $hostNumber);
					$this->validateTriggers($host, $hostNumber);
					$this->validateLinkedTemplates($host, $hostNumber);
					$this->validateGraphs($host, $hostNumber);
					$this->validateMacro($host, $hostNumber);

					$hostNumber++;
				}
			}
		}
	}

	/**
	 * Host groups validation.
	 *
	 * @param array $host			host data
	 * @param int	$hostNumber		host number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGroups($host, $hostNumber) {
		if (!$this->arrayValidator->validate('group', $host['groups'])) {
			throw new Exception(_s('Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/groups/group(%2$s)": %3$s.',
				$hostNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError()
			));
		}
	}

	/**
	 * Items validation.
	 *
	 * @param array $host			host data
	 * @param int	$hostNumber		host number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateItems($host, $hostNumber) {
		if ($host['items']) {
			if (!$this->arrayValidator->validate('item', $host['items'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/items/item(%2$s)": %3$s.',
					$hostNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}

			$itemNumber = 1;
			foreach ($host['items'] as $item) {
				$arrayKeys = array('applications');
				$newArray = new CXmlValidatorConverters();
				$item = $newArray->convertEmpStrToArr($arrayKeys, $item);

				$validationRules = array(
					'type' =>					'required|string',
					'key' =>					'required|string',
					'value_type' =>				'required|string',
					'description' =>			'required|string',
					'ipmi_sensor' =>			'required|string',
					'delay' =>					'required|string',
					'history' =>				'required|string',
					'trends' =>					'required|string',
					'status' =>					'required|string',
					'data_type' =>				'required|string',
					'units' =>					'required|string',
					'multiplier' =>				'required|string',
					'delta' =>					'required|string',
					'formula' =>				'required|string',
					'lastlogsize' =>			'required|string',
					'logtimefmt' =>				'required|string',
					'delay_flex' =>				'required|string',
					'authtype' =>				'required|string',
					'username' =>				'required|string',
					'password' =>				'required|string',
					'publickey' =>				'required|string',
					'privatekey' =>				'required|string',
					'params' =>					'required|string',
					'trapper_hosts' =>			'required|string',
					'snmp_community' =>			'required|string',
					'snmp_oid' =>				'required|string',
					'snmp_port' =>				'required|string',
					'snmpv3_securityname' =>	'required|string',
					'snmpv3_securitylevel' =>	'required|string',
					'snmpv3_authpassphrase' =>	'required|string',
					'snmpv3_privpassphrase' =>	'required|string',
					'valuemapid' =>				'required|string',
					'applications' =>			'required|array'
				);

				$validator = new CNewValidator($item, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/items/item(%2$s)": %3$s',
						$hostNumber, $itemNumber, $errors[0]
					));
				}

				// unexpected tag validation
				$arrayDiff = array_diff_key($item, $validator->getValidInput());
				if ($arrayDiff) {
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/items/item(%2$s)": unexpected tag "%3$s".',
						$hostNumber, $itemNumber, key($arrayDiff)
					));
				}

				$this->validateApplications($item, $hostNumber, $itemNumber);

				$itemNumber++;
			}
		}
	}

	/**
	 * Triggers validation.
	 *
	 * @param array $host			host data
	 * @param int	$hostNumber		host number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateTriggers($host, $hostNumber) {
		if ($host['triggers']) {
			if (!$this->arrayValidator->validate('trigger', $host['triggers'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/triggers/trigger(%2$s)": %3$s.',
					$hostNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}

			$triggerNumber = 1;
			foreach ($host['triggers'] as $trigger) {
				$validationRules = array(
					'description' =>	'required|string',
					'type' =>			'required|string',
					'expression' =>		'required|string',
					'url' =>			'required|string',
					'status' =>			'required|string',
					'priority' =>		'required|string',
					'comments' =>		'required|string'
				);

				$validator = new CNewValidator($trigger, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/triggers/trigger(%2$s)": %3$s',
						$hostNumber, $triggerNumber, $errors[0]
					));
				}

				// unexpected tag validation
				$arrayDiff = array_diff_key($trigger, $validator->getValidInput());
				if ($arrayDiff) {
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/triggers/trigger(%2$s)": unexpected tag "%3$s".',
						$hostNumber, $triggerNumber, key($arrayDiff)
					));
				}

				$triggerNumber++;
			}
		}
	}

	/**
	 * Templates validation.
	 *
	 * @param array $host			host data
	 * @param int	$hostNumber		host number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateLinkedTemplates($host, $hostNumber) {
		if ($host['templates']) {
			if (!$this->arrayValidator->validate('template', $host['templates'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/templates/template(%2$s)": %3$s.',
					$hostNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}
		}
	}

	/**
	 * Graphs validation.
	 *
	 * @param array $host			host data
	 * @param int	$hostNumber		host number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGraphs($host, $hostNumber) {
		if ($host['graphs']) {
			if (!$this->arrayValidator->validate('graph', $host['graphs'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)": %3$s.',
					$hostNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}

			$graphNumber = 1;
			foreach ($host['graphs'] as $graph) {
				$validationRules = array(
					'name' =>				'required|string',
					'width' =>				'required|string',
					'height' =>				'required|string',
					'ymin_type' =>			'required|string',
					'ymax_type' =>			'required|string',
					'ymin_item_key' =>		'required|string',
					'ymax_item_key' =>		'required|string',
					'show_work_period' =>	'required|string',
					'show_triggers' =>		'required|string',
					'graphtype' =>			'required|string',
					'yaxismin' =>			'required|string',
					'yaxismax' =>			'required|string',
					'show_legend' =>		'required|string',
					'show_3d' =>			'required|string',
					'percent_left' =>		'required|string',
					'percent_right' =>		'required|string',
					'graph_elements' =>		'required|array'
				);

				$validator = new CNewValidator($graph, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)": %3$s',
						$hostNumber, $graphNumber, $errors[0]
					));
				}

				// unexpected tag validation
				$arrayDiff = array_diff_key($graph, $validator->getValidInput());
				if ($arrayDiff) {
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)": unexpected tag "%3$s".',
						$hostNumber, $graphNumber, key($arrayDiff)
					));
				}

				$this->validateGraphElements($graph, $hostNumber, $graphNumber);

				$graphNumber++;
			}
		}
	}

	/**
	 * Macros validation.
	 *
	 * @param array $host			host data
	 * @param int	$hostNumber		host number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateMacro($host, $hostNumber) {
		if ($host['macros']) {
			if (!$this->arrayValidator->validate('macro', $host['macros'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/macros/macro(%2$s)": %3$s.',
					$hostNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}

			$macroNumber = 1;
			foreach ($host['macros'] as $macro) {
				$validationRules = array(
					'value' =>	'required|string',
					'name' =>	'required|string'
				);

				$validator = new CNewValidator($macro, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/macros/macro(%2$s)": %3$s',
						$hostNumber, $macroNumber, $errors[0]
					));
				}

				// unexpected tag validation
				$arrayDiff = array_diff_key($macro, $validator->getValidInput());
				if ($arrayDiff) {
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/macros/macro(%2$s)": unexpected tag "%3$s".',
						$hostNumber, $macroNumber, key($arrayDiff)
					));
				}

				$macroNumber++;
			}
		}
	}

	/**
	 * Applications validation.
	 *
	 * @param array $item			item data
	 * @param int	$hostNumber		host number
	 * @param int	$itemNumber		item number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateApplications($item, $hostNumber, $itemNumber) {
		if ($item['applications']) {
			if (!$this->arrayValidator->validate('application', $item['applications'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/items/item(%2$s)/applications/application(%3$s)": %4$s.',
					$hostNumber, $itemNumber, $this->arrayValidator->getErrorSeqNum(),
					$this->arrayValidator->getError())
				);
			}
		}
	}

	/**
	 * Graph element validation.
	 *
	 * @param array $graph			graph data
	 * @param int	$hostNumber		host number
	 * @param int	$graphNumber	graph number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGraphElements($graph, $hostNumber, $graphNumber) {
		if (!$this->arrayValidator->validate('graph_element', $graph['graph_elements'])) {
			throw new Exception(_s(
				'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)/graph_elements/graph_element(%3$s)": %4$s.',
				$hostNumber, $graphNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
			);
		}

		$graphElementNumber = 1;
		foreach ($graph['graph_elements'] as $graphElement) {
			$validationRules = array(
				'item' =>			'required|string',
				'drawtype' =>		'required|string',
				'sortorder' =>		'required|string',
				'color' =>			'required|string',
				'yaxisside' =>		'required|string',
				'calc_fnc' =>		'required|string',
				'type' =>			'required|string',
				'periods_cnt' =>	'required|string'
			);

			$validator = new CNewValidator($graphElement, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)/graph_elements/graph_element(%3$s)": %4$s',
					$hostNumber, $graphNumber, $graphElementNumber, $errors[0]
				));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($graphElement, $validator->getValidInput());
			if ($arrayDiff) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)/graph_elements/graph_element(%3$s)": unexpected tag "%3$s".',
					$hostNumber, $graphNumber, $graphElementNumber, key($arrayDiff)
				));
			}

			$graphElementNumber++;
		}
	}

	/**
	 * Trigger dependencies validation.
	 *
	 * @param array $content	import data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateTriggerDependencies($content) {
		if (array_key_exists('dependencies', $content)) {
			if (!$this->arrayValidator->validate('dependency', $content['dependencies'])) {
				throw new Exception(_s('Cannot parse XML tag "zabbix_export/dependencies/dependency(%1$s)": %2$s.',
					$this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError()
				));
			}

			$dependencyNumber = 1;
			foreach ($content['dependencies'] as $dependency) {
				$validationRules = array(
					'description' =>	'required|string',
					'depends' =>		'required'
				);

				$validator = new CNewValidator($dependency, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/dependencies/dependency(%1$s)": %2$s', $dependencyNumber,
						$errors[0]
					));
				}

				// unexpected tag validation
				unset($dependency['description']);

				if (!$this->arrayValidator->validate('depends', $dependency)) {
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/dependencies/dependency(%1$s)/depends(%2$s)": %3$s.',
						$dependencyNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError()
					));
				}

				$dependencyNumber++;
			}
		}
	}

	/**
	 * Main screen validation.
	 *
	 * @param array $content	import data
	 *
	 * @throws Exception	if structure is invalid
	 */
	private function validateScreens($content) {
		if (array_key_exists('screens', $content)) {
			if ($content['screens']) {
				if (!$this->arrayValidator->validate('screen', $content['screens'])) {
					throw new Exception(_s('Cannot parse XML tag "zabbix_export/screens/screen(%1$s)": %2$s.',
						$this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError()
					));
				}

				$screenNumber = 1;
				foreach ($content['screens'] as $screen) {
					$arrayKeys = array('screenitems');
					$newArray = new CXmlValidatorConverters();
					$screen = $newArray->convertEmpStrToArr($arrayKeys, $screen);

					$validationRules = array(
						'name' =>			'required|string',
						'hsize' =>			'required|string',
						'vsize' =>			'required|string',
						'screenitems' =>	'required|array'
					);

					$validator = new CNewValidator($screen, $validationRules);

					if ($validator->isError()) {
						$errors = $validator->getAllErrors();
						throw new Exception(_s('Cannot parse XML tag "zabbix_export/screens/screen(%1$s)": %2$s',
							$screenNumber, $errors[0]
						));
					}

					// unexpected tag validation
					$arrayDiff = array_diff_key($screen, $validator->getValidInput());
					if ($arrayDiff) {
						throw new Exception(_s(
							'Cannot parse XML tag "zabbix_export/screens/screen(%1$s)": unexpected tag "%2$s".',
							$screenNumber, key($arrayDiff)
						));
					}

					// child elements validation
					$this->validateScreenItems($screen, $screenNumber);

					$screenNumber++;
				}
			}
		}
	}

	/**
	 * Screen items validation.
	 *
	 * @param array $screen			screen data
	 * @param int	$screenNumber	screen number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateScreenItems($screen, $screenNumber) {
		if ($screen['screenitems']) {
			if (!$this->arrayValidator->validate('screenitem', $screen['screenitems'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/screens/screen(%1$s)/screenitems/screenitem(%2$s)": %3$s.',
					$screenNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}

			$screenitemNumber = 1;
			foreach ($screen['screenitems'] as $screenitem) {
				$validationRules = array(
					'resourcetype' =>	'required|string',
					'resourceid' =>		'required',
					'width' =>			'required|string',
					'height' =>			'required|string',
					'x' =>				'required|string',
					'y' =>				'required|string',
					'colspan' =>		'required|string',
					'rowspan' =>		'required|string',
					'elements' =>		'required|string',
					'valign' =>			'required|string',
					'halign' =>			'required|string',
					'style' =>			'required|string',
					'dynamic' =>		'required|string',
					'url' =>			''
				);

				$validator = new CNewValidator($screenitem, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/screens/screen(%1$s)/screenitems/screenitem(%2$s)": %3$s',
						$screenNumber, $screenitemNumber, $errors[0]
					));
				}

				$screenitemNumber++;
			}
		}
	}

	/**
	 * Main maps validation.
	 *
	 * @param array $content	import data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateSysmaps($content) {
		if (array_key_exists('sysmaps', $content)) {
			if ($content['sysmaps']) {
				if (!$this->arrayValidator->validate('sysmap', $content['sysmaps'])) {
					throw new Exception(_s('Cannot parse XML tag "zabbix_export/sysmaps/sysmap(%1$s)": %2$s.',
						$this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError()
					));
				}

				$sysmapNumber = 1;
				foreach ($content['sysmaps'] as $sysmap) {
					$arrayKeys = array('selements', 'links');
					$newArray = new CXmlValidatorConverters();
					$sysmap = $newArray->convertEmpStrToArr($arrayKeys, $sysmap);

					$validationRules = array(
						'selements' =>		'required|array',
						'links' =>			'required|array',
						'name' =>			'required|string',
						'width' =>			'required|string',
						'height' =>			'required|string',
						'backgroundid' =>	'',
						'label_type' =>		'required|string',
						'label_location' =>	'required|string',
						'highlight' =>		'required|string',
						'expandproblem' =>	'required|string',
						'markelements' =>	'required|string',
						'show_unack' =>		'required|string'
					);

					$validator = new CNewValidator($sysmap, $validationRules);

					if ($validator->isError()) {
						$errors = $validator->getAllErrors();
						throw new Exception(_s('Cannot parse XML tag "zabbix_export/sysmaps/sysmap(%1$s)": %2$s',
							$sysmapNumber, $errors[0]
						));
					}

					// unexpected tag validation
					$arrayDiff = array_diff_key($sysmap, $validator->getValidInput());
					if ($arrayDiff) {
						throw new Exception(_s(
							'Cannot parse XML tag "zabbix_export/sysmaps/sysmap(%1$s)": unexpected tag "%2$s".',
							$sysmapNumber, key($arrayDiff)
						));
					}

					// child elements validation
					$this->validateSelements($sysmap, $sysmapNumber);

					$sysmapNumber++;
				}
			}
		}
	}

	/**
	 * Map selements validation.
	 *
	 * @param array $sysmap			map data
	 * @param int	$sysmapNumber	map number
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateSelements($sysmap, $sysmapNumber) {
		if ($sysmap['selements']) {
			if (!$this->arrayValidator->validate('selement', $sysmap['selements'])) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/sysmaps/sysmap(%1$s)/selements/selement(%2$s)": %3$s.',
					$sysmapNumber, $this->arrayValidator->getErrorSeqNum(), $this->arrayValidator->getError())
				);
			}

			$selementNumber = 1;
			foreach ($sysmap['selements'] as $selement) {
				$validationRules = array(
					'selementid' =>		'',
					'elementid' =>		'',
					'elementtype' =>	'required|string',
					'iconid_off' =>		'',
					'iconid_unknown' =>	'',
					'label' =>			'required|string',
					'label_location' =>	'',
					'x' =>				'required|string',
					'y' =>				'required|string'
				);

				$validator = new CNewValidator($selement, $validationRules);

				if ($validator->isError()) {
					$errors = $validator->getAllErrors();
					throw new Exception(_s(
						'Cannot parse XML tag "zabbix_export/sysmaps/sysmap(%1$s)/selements/selement(%2$s)": %3$s',
						$sysmapNumber, $selementNumber, $errors[0]
					));
				}

				$selementNumber++;
			}
		}
	}
}
