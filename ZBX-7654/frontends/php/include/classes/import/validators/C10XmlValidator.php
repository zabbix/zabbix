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
	 * Base validation function.
	 *
	 * @param array $content	import data
	 */
	public function validate($content) {
		$this->validateDateTimeFormat($content['date'], $content['time']);

		// prepare content
		$arrayKeys = array('hosts', 'dependencies');
		$newArray = new CXmlValidatorConverters();
		$content = $newArray->convertEmpStrToArr($arrayKeys, $content);

		$this->validateHosts($content);
//		$this->validateSysmaps($content);
//		$this->validateScreens($content);
	}

	/**
	 * Validate date and time formats.
	 *
	 * @param string $date	export date
	 * @param string $time	export time
	 *
	 * @throws Exception	if the date or time is invalid
	 */
	protected function validateDateTimeFormat($date, $time) {
		if (!preg_match('/^(0[1-9]|[1-2][0-9]|3[0-1]).(0[1-9]|1[0-2]).[0-9]{2}$/', $date)) {
			throw new Exception(('Incorrect date format, supported only DD.MM.YY.'));
		}
		if (!preg_match('/(2[0-3]|[01][0-9]).[0-5][0-9]/', $time)) {
			throw new Exception(('Incorrect time format, supported only HH.MM.'));
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
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('host', $content['hosts']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Cannot parse XML tag "zabbix_export/hosts/host(%1$s)": %2$s.',
						$arrayValidator->getErrorSeqNum(), $arrayValidator->getError()
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
						'macros' =>			'required|array',
						'dependencies' =>	''
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

//				$this->validateTriggerDependencies($content);
			}
			else {
				throw new Exception(_('Incorrect "hosts" definition.'));
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
		$arrayValidator = new CXmlArrayValidator();
		$arrayValidator->validate('group', $host['groups']);

		if ($arrayValidator->getError()) {
			throw new Exception(_s('Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/groups/group(%2$s)": %3$s.',
				$hostNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError()
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
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('item', $host['items']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/items/item(%2$s)": %3$s.',
					$hostNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('trigger', $host['triggers']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/triggers/trigger(%2$s)": %3$s.',
					$hostNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('template', $host['templates']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/templates/template(%2$s)": %3$s.',
					$hostNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('graph', $host['graphs']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)": %3$s.',
					$hostNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('macro', $host['macros']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/macros/macro(%2$s)": %3$s.',
					$hostNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('application', $item['applications']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s(
					'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/items/item(%2$s)/applications/application(%3$s)": %4$s.',
					$hostNumber, $itemNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
		$arrayValidator = new CXmlArrayValidator();
		$arrayValidator->validate('graph_element', $graph['graph_elements']);

		if ($arrayValidator->getError()) {
			throw new Exception(_s(
				'Cannot parse XML tag "zabbix_export/hosts/host(%1$s)/graphs/graph(%2$s)/graph_elements/graph_element(%3$s)": %4$s.',
				$hostNumber, $graphNumber, $arrayValidator->getErrorSeqNum(), $arrayValidator->getError())
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
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateTriggerDependencies($content) {
		if (isset($content['dependencies'])) {
			if ((is_array($content['dependencies']) && $content['dependencies']) || $content['dependencies'] === '') {
				if ($content['dependencies'] !== '') {
					$arrayValidator = new CXmlArrayValidator();
					$arrayValidator->validate('dependency', $content['dependencies']);

					if ($arrayValidator->getError()) {
						throw new Exception(_s('Incorrect "trigger dedependencies" definition: %1$s',
							$arrayValidator->getError()
						));
					}

					foreach ($content['dependencies'] as $dependency) {
						$validationRules = array(
							'description' => 'required|string'
						);

						$validator = new CNewValidator($dependency, $validationRules);

						foreach ($validator->getAllErrors() as $error) {
							throw new Exception(_s('Incorrect "trigger dedependencies" definition: %2$s', $error));
						}
					}
				}
			}
			else {
				throw new Exception(_('Incorrect "trigger dedependencies" definition.'));
			}
		}
	}
}
