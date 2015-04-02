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
	 * @param array  $zabbix_export	import data
	 * @param string $path			XML path
	 */
	public function validate(array $zabbix_export, $path) {
		$this->arrayValidator = new CXmlArrayValidator();

		if (array_key_exists('date', $zabbix_export)) {
			$this->validateDate($zabbix_export['date']);
		}
		if (array_key_exists('time', $zabbix_export)) {
			$this->validateTime($zabbix_export['time']);
		}

		$fields = array(
			'hosts' =>			'array',
			'dependencies' =>	'array',
			'sysmaps' =>		'array',
			'screens' =>		'array',
			'images' =>			'array'
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
		if (array_key_exists('images', $zabbix_export)) {
			$this->validateImages($zabbix_export['images'], $path.'/images');
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
			throw new Exception(_s('Incorrect time format: %1$s.', _('hh.mm is expected')));
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

			$validator = new CNewValidator($hosts, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateHost($host, $subpath);
		}
	}

	/**
	 * Host validation.
	 *
	 * @param array  $host	import data
	 * @param string $path	XML path
	 *
	 * @throws Exception	if structure or values is invalid
	 */
	protected function validateHost(array $host, $path) {
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// child elements validation

		if (array_key_exists('groups', $host)) {
			$this->validateGroups($host['groups'], $path.'/groups');
		}
		if (array_key_exists('items', $host)) {
			$this->validateItems($host['items'], $path.'/items');
		}
		if (array_key_exists('triggers', $host)) {
			$this->validateTriggers($host['triggers'], $path.'/triggers');
		}
		if (array_key_exists('templates', $host)) {
			$this->validateTemplates($host['templates'], $path.'/templates');
		}
		if (array_key_exists('graphs', $host)) {
			$this->validateGraphs($host['graphs'], $path.'/graphs');
		}
		if (array_key_exists('macros', $host)) {
			$this->validateMacros($host['macros'], $path.'/macros');
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

		$groupNumber = 1;
		foreach ($groups as $key => $group) {
			$subpath = $path.'/group('.$groupNumber++.')';

			$validator = new CNewValidator($groups, array($key => 'string'));

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
					$subpath, _('a character string is expected')
				));
			}
		}
	}

	/**
	 * Items validation.
	 *
	 * @param array  $items	items data
	 * @param string $path	XML path
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
		foreach ($items as $key => $item) {
			$subpath = $path.'/item('.$itemNumber++.')';

			$validator = new CNewValidator($items, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateItem($item, $subpath);
		}
	}

	/**
	 * Item validation.
	 *
	 * @param array  $item	item data
	 * @param string $path	XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateItem(array $item, $path) {
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($item, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}

		if (array_key_exists('applications', $item)) {
			$this->validateApplications($item['applications'], $path.'/applications');
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
		foreach ($triggers as $key => $trigger) {
			$subpath = $path.'/trigger('.$triggerNumber++.')';

			$validator = new CNewValidator($triggers, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateTrigger($trigger, $subpath);
		}
	}

	/**
	 * Trigger validation.
	 *
	 * @param array  $trigger	trigger data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateTrigger(array $trigger, $path) {
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($trigger, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
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

		$templateNumber = 1;
		foreach ($templates as $key => $template) {
			$subpath = $path.'/template('.$templateNumber++.')';

			$validator = new CNewValidator($templates, array($key => 'string'));

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
					$subpath, _('a character string is expected')
				));
			}
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
		foreach ($graphs as $key => $graph) {
			$subpath = $path.'/graph('.$graphNumber++.')';

			$validator = new CNewValidator($graphs, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateGraph($graph, $subpath);
		}
	}

	/**
	 * Graph validation.
	 *
	 * @param array  $graph	graph data
	 * @param string $path	XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGraph(array $graph, $path) {
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($graph, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}

		if (array_key_exists('graph_elements', $graph)) {
			$this->validateGraphElements($graph['graph_elements'], $path.'/graph_elements');
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
		foreach ($macros as $key => $macro) {
			$subpath = $path.'/macro('.$macroNumber++.')';

			$validator = new CNewValidator($macros, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateMacro($macro, $subpath);
		}
	}

	/**
	 * Macro validation.
	 *
	 * @param array  $macro	macro data
	 * @param string $path	XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateMacro(array $macro, $path) {
		$validationRules = array(
			'value' =>	'required|string',
			'name' =>	'required|string'
		);

		$validator = new CNewValidator($macro, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($macro, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
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

		$applicationNumber = 1;
		foreach ($applications as $key => $application) {
			$subpath = $path.'/application('.$applicationNumber++.')';

			$validator = new CNewValidator($applications, array($key => 'string'));

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
					$subpath, _('a character string is expected')
				));
			}
		}
	}

	/**
	 * Graph elements validation.
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
		foreach ($graph_elements as $key => $graph_element) {
			$subpath = $path.'graph_element('.$graphElementNumber++.')';

			$validator = new CNewValidator($graph_elements, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateGraphElement($graph_element, $subpath);
		}
	}

	/**
	 * Graph element validation.
	 *
	 * @param array  $graph_element	graph_element data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if structure is invalid
	 */
	protected function validateGraphElement(array $graph_element, $path) {
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($graph_element, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Trigger dependencies validation.
	 *
	 * @param array  $dependencies	trigger dependencies data
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
		foreach ($dependencies as $key => $dependency) {
			$subpath = $path.'/dependency('.$dependencyNumber++.')';

			$validator = new CNewValidator($dependencies, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateDependency($dependency, $subpath);
		}
	}

	/**
	 * Trigger dependency validation.
	 *
	 * @param array  $dependency	trigger dependency data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if structure is invalid
	 */
	protected function validateDependency(array $dependency, $path) {
		$validationRules = array('description' => 'required|string');

		$validator = new CNewValidator($dependency, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		$depends = $dependency;

		// unexpected tag validation
		unset($depends['description']);

		$this->validateDepends($depends, $path);
	}

	/**
	 * Trigger depends validation.
	 *
	 * @param array  $depends	trigger depends data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateDepends(array $depends, $path) {
		if (!$this->arrayValidator->validate('depends', $depends)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/depends('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$dependNumber = 1;
		foreach ($depends as $key => $depend) {
			$subpath = $path.'/depends('.$dependNumber++.')';

			$validator = new CNewValidator($depends, array($key => 'string'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
					$subpath, _('a character string is expected')
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
				'url' =>			'string'
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
		foreach ($sysmaps as $key => $sysmap) {
			$subpath = $path.'/sysmap('.$sysmapNumber++.')';

			$validator = new CNewValidator($sysmaps, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateSysmap($sysmap, $subpath);
		}
	}

	/**
	 * Map validation.
	 *
	 * @param array  $sysmap	import data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateSysmap(array $sysmap, $path) {
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
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($sysmap, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}

		// child elements validation
		if (array_key_exists('selements', $sysmap)) {
			$this->validateSelements($sysmap['selements'], $path.'/selements');
		}
		if (array_key_exists('backgroundid', $sysmap)) {
			$this->validateBackgroundId($sysmap['backgroundid'], $path.'/backgroundid');
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
		foreach ($selements as $key => $selement) {
			$subpath = $path.'/selement('.$selementNumber++.')';

			$validator = new CNewValidator($selements, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateSelement($selement, $subpath);
		}
	}

	/**
	 * Map selement validation.
	 *
	 * @param array  $selement	selement data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateSelement(array $selement, $path) {
		$validationRules = array(
			'selementid' =>			'',
			'elementid' =>			'',
			'elementtype' =>		'required|string',
			'iconid_on' =>			'array',
			'iconid_off' =>			'array',
			'iconid_unknown' =>		'array',
			'iconid_disabled' =>	'array',
			'icon_maintenance' =>	'array',
			'label' =>				'required|string',
			'label_location' =>		'string',
			'x' =>					'required|string',
			'y' =>					'required|string'
		);

		$validator = new CNewValidator($selement, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($selement, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}

		// child elements validation
		if (array_key_exists('iconid_on', $selement)) {
			$this->validateIconIdOn($selement['iconid_on'], $path.'/iconid_on');
		}
		if (array_key_exists('iconid_off', $selement)) {
			$this->validateIconIdOff($selement['iconid_off'], $path.'/iconid_off');
		}
		if (array_key_exists('iconid_unknown', $selement)) {
			$this->validateIconIdUnknown($selement['iconid_unknown'], $path.'/iconid_unknown');
		}
		if (array_key_exists('iconid_disabled', $selement)) {
			$this->validateIconIdDisabled($selement['iconid_disabled'], $path.'/iconid_disabled');
		}
		if (array_key_exists('iconid_maintenance', $selement)) {
			$this->validateIconIdMaintenance($selement['iconid_maintenance'], $path.'/iconid_maintenance');
		}
	}

	/**
	 * Map Background validation.
	 *
	 * @param array  $background	background data
	 * @param string $path			XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateBackgroundId(array $background, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($background, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($background, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Selement IconIdOn validation.
	 *
	 * @param array  $iconIdOn		iconIdOn data
	 * @param string $path			XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateIconIdOn(array $iconIdOn, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($iconIdOn, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($iconIdOn, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Selement IconIdOff validation.
	 *
	 * @param array  $iconIdOff		iconIdOff data
	 * @param string $path			XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateIconIdOff(array $iconIdOff, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($iconIdOff, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($iconIdOff, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Selement IconIdUnknown validation.
	 *
	 * @param array  $iconIdUnknown		iconIdUnknown data
	 * @param string $path				XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateIconIdUnknown(array $iconIdUnknown, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($iconIdUnknown, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($iconIdUnknown, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Selement IconIdDisabled validation.
	 *
	 * @param array  $iconIdDisabled	iconIdDisabled data
	 * @param string $path				XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateIconIdDisabled(array $iconIdDisabled, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($iconIdDisabled, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($iconIdDisabled, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Selement IconIdMaintenance validation.
	 *
	 * @param array  $iconIdMaintenance		iconIdMaintenance data
	 * @param string $path					XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateIconIdMaintenance(array $iconIdMaintenance, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($iconIdMaintenance, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($iconIdMaintenance, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Main images validation.
	 *
	 * @param array  $images	images data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateImages(array $images, $path) {
		if (!$this->arrayValidator->validate('image', $images)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/image('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$imageNumber = 1;
		foreach ($images as $key => $image) {
			$subpath = $path.'/image('.$imageNumber++.')';

			$validator = new CNewValidator($images, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateImage($image, $subpath);
		}
	}

	/**
	 * Image validation.
	 *
	 * @param array  $image		image data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateImage(array $image, $path) {
		$validationRules = array(
			'name' =>			'required|string',
			'imagetype' =>		'required|string',
			'encodedImage' =>	'required|string'
		);

		$validator = new CNewValidator($image, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($image, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}
}
