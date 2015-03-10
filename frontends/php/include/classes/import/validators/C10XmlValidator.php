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
		if (isset($content['hosts'])) {
			if (is_array($content['hosts']) && $content['hosts']) {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('host', $content['hosts']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "host" definition: %1$s.', $arrayValidator->getError()));
				}

				foreach ($content['hosts'] as $host) {
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
						'triggers' =>		'required',
						'items' =>			'required',
						'templates' =>		'required',
						'graphs' =>			'required',
						'macros' =>			'required',
						'dependencies' =>	''
					);

					$validator = new CNewValidator($host, $validationRules);

					foreach ($validator->getAllErrors() as $error) {
						throw new Exception($error);
					}

					// child elements validation
					$this->validateGroups($host);
					$this->validateItems($host);
					$this->validateTriggers($host);
					$this->validateLinkedTemplates($host);
					$this->validateGraphs($host);
					$this->validateMacro($host);
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
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGroups($host) {
		if (is_array($host['groups']) && $host['groups']) {
			$arrayValidator = new CXmlArrayValidator();
			$arrayValidator->validate('group', $host['groups']);

			if ($arrayValidator->getError()) {
				throw new Exception(_s('Incorrect "groups" definition for host "%1$s": %2$s', $host['name'],
					$arrayValidator->getError())
				);
			}
		}
		else {
			throw new Exception(_s('Incorrect "groups" definition for host "%1$s".', $host['name']));
		}
	}

	/**
	 * Items validation.
	 *
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateItems($host) {
		if ((is_array($host['items']) && $host['items']) || $host['items'] === '') {
			if ($host['items'] !== '') {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('item', $host['items']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "items" definition for host "%1$s": %2$s.', $host['name'],
						$arrayValidator->getError())
					);
				}

				foreach ($host['items'] as $item) {
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
						'applications' =>			'required'
					);

					$validator = new CNewValidator($item, $validationRules);

					foreach ($validator->getAllErrors() as $error) {
						throw new Exception(_s('Incorrect "items" definition for host "%1$s": %2$s', $host['name'],
							$error)
						);
					}

					// unexpected tag validation
					$arrayDiff = array_diff_key($item, $validator->getValidInput());
					foreach ($arrayDiff as $key => $value) {
						throw new Exception(
							_s('Incorrect item "%1$s" definition for host "%2$s": unexpected tag "%3$s".',
								$item['description'], $host['name'], $key
							)
						);
					}

					$this->validateApplications($item, $host['name']);
				}
			}
		}
		else {
			throw new Exception(_s('Incorrect "items" definition for host "%1$s".', $host['name']));
		}
	}

	/**
	 * Triggers validation.
	 *
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateTriggers($host) {
		if ((is_array($host['triggers']) && $host['triggers']) || $host['triggers'] === '') {
			if ($host['triggers'] !== '') {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('trigger', $host['triggers']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "triggers" definition for host "%1$s": %2$s.', $host['name'],
						$arrayValidator->getError())
					);
				}

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

					foreach ($validator->getAllErrors() as $error) {
						throw new Exception(_s('Incorrect "triggers" definition for host "%1$s": %2$s', $host['name'],
							$error)
						);
					}

					// unexpected tag validation
					$arrayDiff = array_diff_key($trigger, $validator->getValidInput());
					foreach ($arrayDiff as $key => $value) {
						throw new Exception(
							_s('Incorrect trigger "%1$s" definition for host "%2$s": unexpected tag "%3$s".',
								$trigger['description'], $host['name'], $key
							)
						);
					}
				}
			}
		}
		else {
			throw new Exception(_s('Incorrect "triggers" definition for host "%1$s".', $host['name']));
		}
	}

	/**
	 * Templates validation.
	 *
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateLinkedTemplates($host) {
		if ((is_array($host['templates']) && $host['templates']) || $host['templates'] === '') {
			if ($host['templates'] !== '') {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('template', $host['templates']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "templates" definition for host "%1$s": %2$s.', $host['name'],
						$arrayValidator->getError())
					);
				}
			}
		}
		else {
			throw new Exception(_s('Incorrect "templates" definition for host "%1$s".', $host['name']));
		}
	}

	/**
	 * Graphs validation.
	 *
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGraphs($host) {
		if ((is_array($host['graphs']) && $host['graphs']) || $host['graphs'] === '') {
			if ($host['graphs'] !== '') {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('graph', $host['graphs']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "graphs" definition for host "%1$s": %2$s.', $host['name'],
						$arrayValidator->getError())
					);
				}

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
						'graph_elements' =>		'required'
					);

					$validator = new CNewValidator($graph, $validationRules);

					foreach ($validator->getAllErrors() as $error) {
						throw new Exception(_s('Incorrect "graphs" definition for host "%1$s": %2$s', $host['name'],
							$error)
						);
					}

					// unexpected tag validation
					$arrayDiff = array_diff_key($graph, $validator->getValidInput());
					foreach ($arrayDiff as $key => $value) {
						throw new Exception(
							_s('Incorrect graph "%1$s" definition for host "%2$s": unexpected tag "%3$s".',
								$graph['name'], $host['name'], $key
							)
						);
					}
				}
			}
		}
		else {
			throw new Exception(_s('Incorrect "graphs" definition for host "%1$s".', $host['name']));
		}
	}

	/**
	 * Macros validation.
	 *
	 * @param array $host	host data
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateMacro($host) {
		if ((is_array($host['macros']) && $host['macros']) || $host['macros'] === '') {
			if ($host['macros'] !== '') {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('macro', $host['macros']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "macros" definition for host "%1$s": %2$s.', $host['name'],
						$arrayValidator->getError())
					);
				}

				foreach ($host['macros'] as $macro) {
					$validationRules = array(
						'value' =>	'required|string',
						'name' =>	'required|string'
					);

					$validator = new CNewValidator($macro, $validationRules);

					foreach ($validator->getAllErrors() as $error) {
						throw new Exception(_s('Incorrect "macros" definition for host "%1$s": %2$s', $host['name'],
							$error)
						);
					}

					// unexpected tag validation
					$arrayDiff = array_diff_key($macro, $validator->getValidInput());
					foreach ($arrayDiff as $key => $value) {
						throw new Exception(
							_s('Incorrect macro "%1$s" definition for host "%2$s": unexpected tag "%3$s".',
								$macro['name'], $host['name'], $key
							)
						);
					}
				}
			}
		}
		else {
			throw new Exception(_s('Incorrect "macros" definition for host "%1$s".', $host['name']));
		}
	}

	/**
	 * Applications validation.
	 *
	 * @param array $item	item data
	 * @param string $host	host name
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateApplications($item, $host) {
		if ((is_array($item['applications']) && $item['applications']) || $item['applications'] === '') {
			if ($item['applications'] !== '') {
				$arrayValidator = new CXmlArrayValidator();
				$arrayValidator->validate('application', $item['applications']);

				if ($arrayValidator->getError()) {
					throw new Exception(_s('Incorrect "applications" definition for host "%1$s": %2$s.', $host,
						$arrayValidator->getError())
					);
				}
			}
		}
		else {
			throw new Exception(_s('Incorrect "applications" definition for host "%1$s".', $host));
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
