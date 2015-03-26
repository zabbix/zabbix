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
	 * @var CXmlValidatorConverters
	 */
	protected $validatorConverters;

	/**
	 * Base validation function.
	 *
	 * @param array  $zabbix_export	import data
	 * @param string $path			XML path
	 */
	public function validate(array $zabbix_export, $path) {
		$this->arrayValidator = new CXmlArrayValidator();
		$this->validatorConverters = new CXmlValidatorConverters();

		$this->validateDate($zabbix_export['date']);
		$this->validateTime($zabbix_export['time']);

		// prepare content
		$zabbix_export = $this->validatorConverters->convertEmpStrToArr(
			array('hosts', 'dependencies', 'sysmaps', 'screens'), $zabbix_export
		);

		$fields = array(
			'hosts' =>			'array',
			'dependencies' =>	'array',
			'sysmaps' =>		'array',
			'screens' =>		'array'
		);

		$validator = new CNewValidator($zabbix_export, $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		if (array_key_exists('hosts', $zabbix_export)) {
			$this->validateHosts($zabbix_export['hosts'], $path.'/hosts');
		}
		if (array_key_exists('dependencies', $zabbix_export)) {
			$this->validateDependencies($zabbix_export['dependencies'], $path.'/dependencies');
		}
		if (array_key_exists('sysmaps', $zabbix_export)) {
			$this->validateSysmaps($zabbix_export['sysmaps'], $path.'/sysmaps');
		}
		if (array_key_exists('screens', $zabbix_export)) {
			$this->validateScreens($zabbix_export['screens'], $path.'/screens');
		}
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
			throw new Exception(_s('Incorrect date format: %1$s.', _('DD.MM.YY is expected')));
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
			throw new Exception(_s('Incorrect time format: %1$s.', _('HH.MM is expected')));
		}
	}

	/**
	 * Hosts validation.
	 *
	 * @param array  $hosts	import data
	 * @param string $path	XML path
	 *
	 * @throws Exception	if structure or values is invalid
	 */
	protected function validateHosts(array $hosts, $path) {
		if (!$this->arrayValidator->validate('host', $hosts)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/host('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$hostNumber = 1;
		foreach ($hosts as $key => $host) {
			$subpath = $path.'/host('.$hostNumber++.')';

			$fields = array($key => 'array');

			$validator = new CNewValidator($hosts, $fields);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			$host = $this->validatorConverters->convertEmpStrToArr(
				array('groups', 'triggers', 'items', 'templates', 'graphs', 'macros'), $host
			);

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
				'groups' =>			'array',
				'items' =>			'array',
				'triggers' =>		'array',
				'templates' =>		'array',
				'graphs' =>			'array',
				'macros' =>			'array'
			);

			$validator = new CNewValidator($host, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// child elements validation

			if (array_key_exists('groups', $host)) {
				$this->validateGroups($host['groups'], $subpath.'/groups');
			}
			if (array_key_exists('items', $host)) {
				$this->validateItems($host['items'], $subpath.'/items');
			}
			if (array_key_exists('triggers', $host)) {
				$this->validateTriggers($host['triggers'], $subpath.'/triggers');
			}
			if (array_key_exists('templates', $host)) {
				$this->validateTemplates($host['templates'], $subpath.'/templates');
			}
			if (array_key_exists('graphs', $host)) {
				$this->validateGraphs($host['graphs'], $subpath.'/graphs');
			}
			if (array_key_exists('macros', $host)) {
				$this->validateMacros($host['macros'], $subpath.'/macros');
			}
		}
	}

	/**
	 * Host groups validation.
	 *
	 * @param array  $groups	groups data
	 * @param string $path		XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGroups(array $groups, $path) {
		if (!$this->arrayValidator->validate('group', $groups)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/group('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}
	}

	/**
	 * Items validation.
	 *
	 * @param array  $items		items data
	 * @param string $path		XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateItems(array $items, $path) {
		if (!$this->arrayValidator->validate('item', $items)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/item('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$itemNumber = 1;
		foreach ($items as $item) {
			$subpath = $path.'/item('.$itemNumber++.')';

			$item = $this->validatorConverters->convertEmpStrToArr(array('applications'), $item);

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
				'applications' =>			'array'
			);

			$validator = new CNewValidator($item, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($item, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}

			if (array_key_exists('applications', $item)) {
				$this->validateApplications($item['applications'], $subpath.'/applications');
			}
		}
	}

	/**
	 * Triggers validation.
	 *
	 * @param array  $triggers	triggers data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateTriggers(array $triggers, $path) {
		if (!$this->arrayValidator->validate('trigger', $triggers)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/trigger('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$triggerNumber = 1;
		foreach ($triggers as $trigger) {
			$subpath = $path.'/trigger('.$triggerNumber++.')';

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
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($trigger, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}
		}
	}

	/**
	 * Templates validation.
	 *
	 * @param array  $templates	linked templates data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateTemplates(array $templates, $path) {
		if (!$this->arrayValidator->validate('template', $templates)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/template('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}
	}

	/**
	 * Graphs validation.
	 *
	 * @param array  $graphs	graphs data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateGraphs(array $graphs, $path) {
		if (!$this->arrayValidator->validate('graph', $graphs)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/graph('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$graphNumber = 1;
		foreach ($graphs as $graph) {
			$subpath = $path.'/graph('.$graphNumber++.')';

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
				'graph_elements' =>		'array'
			);

			$validator = new CNewValidator($graph, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($graph, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}

			if (array_key_exists('graph_elements', $graph)) {
				$this->validateGraphElements($graph['graph_elements'], $subpath.'/graph_elements');
			}
		}
	}

	/**
	 * Macros validation.
	 *
	 * @param array  $macros	macros data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateMacros(array $macros, $path) {
		if (!$this->arrayValidator->validate('macro', $macros)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/macro('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$macroNumber = 1;
		foreach ($macros as $macro) {
			$subpath = $path.'/macro('.$macroNumber++.')';

			$validationRules = array(
				'value' =>	'required|string',
				'name' =>	'required|string'
			);

			$validator = new CNewValidator($macro, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($macro, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}
		}
	}

	/**
	 * Applications validation.
	 *
	 * @param array  $applications	applications data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if structure is invalid
	 */
	protected function validateApplications(array $applications, $path) {
		if (!$this->arrayValidator->validate('application', $applications)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/application('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}
	}

	/**
	 * Graph element validation.
	 *
	 * @param array  $graph_elements	graph_elements data
	 * @param string $path				XML path
	 *
	 * @throws Exception				if structure is invalid
	 */
	protected function validateGraphElements(array $graph_elements, $path) {
		if (!$this->arrayValidator->validate('graph_element', $graph_elements)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/graph_element('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$graphElementNumber = 1;
		foreach ($graph_elements as $graph_element) {
			$subpath = $path.'graph_element('.$graphElementNumber++.')';

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

			$validator = new CNewValidator($graph_element, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($graph_element, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}
		}
	}

	/**
	 * Trigger dependencies validation.
	 *
	 * @param array  $dependencies	import data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if structure is invalid
	 */
	protected function validateDependencies(array $dependencies, $path) {
		if (!$this->arrayValidator->validate('dependency', $dependencies)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/dependency('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$dependencyNumber = 1;
		foreach ($dependencies as $dependency) {
			$subpath = $path.'/dependency('.$dependencyNumber++.')';

			$validationRules = array(
				'description' =>	'required|string',
				'depends' =>		'required'
			);

			$validator = new CNewValidator($dependency, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			unset($dependency['description']);

			if (!$this->arrayValidator->validate('depends', $dependency)) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
					$subpath.'/depends('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
				));
			}
		}
	}

	/**
	 * Main screen validation.
	 *
	 * @param array  $screens	import data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	private function validateScreens(array $screens, $path) {
		if (!$this->arrayValidator->validate('screen', $screens)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/screen('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$screenNumber = 1;
		foreach ($screens as $screen) {
			$subpath = $path.'/screen('.$screenNumber++.')';

			$screen = $this->validatorConverters->convertEmpStrToArr(array('screenitems'), $screen);

			$validationRules = array(
				'name' =>			'required|string',
				'hsize' =>			'required|string',
				'vsize' =>			'required|string',
				'screenitems' =>	'required|array'
			);

			$validator = new CNewValidator($screen, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($screen, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}

			// child elements validation
			if (array_key_exists('screenitems', $screen)) {
				$this->validateScreenItems($screen['screenitems'], $subpath.'/screenitems');
			}
		}
	}

	/**
	 * Screen items validation.
	 *
	 * @param array  $screenitems	screenitems data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if structure is invalid
	 */
	protected function validateScreenItems(array $screenitems, $path) {
		if (!$this->arrayValidator->validate('screenitem', $screenitems)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/screenitem('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$screenitemNumber = 1;
		foreach ($screenitems as $screenitem) {
			$subpath = $path.'/screenitem('.$screenitemNumber++.')';

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
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}
		}
	}

	/**
	 * Main maps validation.
	 *
	 * @param array  $sysmaps	import data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateSysmaps(array $sysmaps, $path) {
		if (!$this->arrayValidator->validate('sysmap', $sysmaps)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/sysmap('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$sysmapNumber = 1;
		foreach ($sysmaps as $sysmap) {
			$subpath = $path.'/sysmap('.$sysmapNumber++.')';

			$sysmap = $this->validatorConverters->convertEmpStrToArr(array('selements', 'links'), $sysmap);

			$validationRules = array(
				'selements' =>		'array',
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
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}

			// unexpected tag validation
			$arrayDiff = array_diff_key($sysmap, $validator->getValidInput());
			if ($arrayDiff) {
				$error = _s('unexpected tag "%1$s"', key($arrayDiff));
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, $error));
			}

			// child elements validation
			if (array_key_exists('selements', $sysmap)) {
				$this->validateSelements($sysmap['selements'], $subpath.'/selements');
			}
		}
	}

	/**
	 * Map selements validation.
	 *
	 * @param array  $selements	selements data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateSelements(array $selements, $path) {
		if (!$this->arrayValidator->validate('selement', $selements)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/selement('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$selementNumber = 1;
		foreach ($selements as $selement) {
			$subpath = $path.'/selement('.$selementNumber++.')';

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
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}
		}
	}
}
