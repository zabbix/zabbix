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
			$this->validateDateTime($zabbix_export['date']);
		}

		$fields = array(
			'groups' =>		'array',
			'hosts' =>		'array',
			'templates' =>	'array',
			'triggers' =>	'array',
			'graphs' =>		'array',
			'screens' =>	'array',
			'images' =>		'array',
			'maps' =>		'array'
		);

		$validator = new CNewValidator($zabbix_export, $fields);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "zabbix_export": %1$s', $errors[0]));
		}

		if (array_key_exists('groups', $zabbix_export)) {
			$this->validateGroups($zabbix_export['groups'], $path.'/groups');
		}
		if (array_key_exists('hosts', $zabbix_export)) {
			$this->validateHosts($zabbix_export['hosts'], $path.'/hosts');
		}
		if (array_key_exists('templates', $zabbix_export)) {
			$this->validateTemplates($zabbix_export['templates'], $path.'/templates');
		}
		if (array_key_exists('triggers', $zabbix_export)) {
			$this->validateTriggers($zabbix_export['triggers'], $path.'/triggers');
		}
		if (array_key_exists('graphs', $zabbix_export)) {
			$this->validateGraphs($zabbix_export['graphs'], $path.'/graphs');
		}
		if (array_key_exists('screens', $zabbix_export)) {
			$this->validateScreens($zabbix_export['screens'], $path.'/screens');
		}
		if (array_key_exists('images', $zabbix_export)) {
			$this->validateImages($zabbix_export['images'], $path.'/images');
		}
		if (array_key_exists('maps', $zabbix_export)) {
			$this->validateMaps($zabbix_export['maps'], $path.'/maps');
		}
	}

	/**
	 * Validate date and time format.
	 *
	 * @param string $date	export date and time
	 *
	 * @throws Exception	if the date or time is invalid
	 */
	protected function validateDateTime($date) {
		if (!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[01])T(2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]Z$/', $date)) {
			throw new Exception(_('Incorrect date and time format: YYYY-MM-DDThh:mm:ssZ is expected.'));
		}
	}

	/**
	 * Groups validation.
	 *
	 * @param array $groups	import data
	 *
	 * @throws Exception	if structure or values is invalid
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

			$validator = new CNewValidator($groups, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateGroup($group, $subpath);
		}
	}

	/**
	 * Group validation.
	 *
	 * @param array $groups	import data
	 *
	 * @throws Exception	if structure or values is invalid
	 */
	protected function validateGroup(array $group, $path) {
		$validationRules = array('name' => 'required|string');

		$validator = new CNewValidator($group, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
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
			'host' =>				'required|string',
			'name' =>				'required|string',
			'description' =>		'string',
			'proxy' =>				'string',
			'status' =>				'required|string',
			'ipmi_authtype' =>		'required|string',
			'ipmi_privilege' =>		'required|string',
			'ipmi_username' =>		'required|string',
			'ipmi_password' =>		'required|string',
			'templates' =>			'array',
			'groups' =>				'required|array',
			'interfaces' =>			'array',
			'applications' =>		'array',
			'items' =>				'array',
			'discovery_rules' =>	'array',
			'macros' =>				'array',
			'inventory' =>			'array'
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
			$this->validateLinkedTemplates($host['templates'], $path.'/templates');
		}
		if (array_key_exists('graphs', $host)) {
			$this->validateGraphs($host['graphs'], $path.'/graphs');
		}
		if (array_key_exists('macros', $host)) {
			$this->validateMacros($host['macros'], $path.'/macros');
		}
		if (array_key_exists('applications', $host)) {
			$this->validateApplications($host['applications'], $path.'/applications');
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
			'name' =>					'required|string',
			'type' =>					'required|string',
			'snmp_community' =>			'required|string',
			'multiplier' =>				'required|string',
			'snmp_oid' =>				'required|string',
			'key' =>					'required|string',
			'delay' =>					'required|string',
			'history' =>				'required|string',
			'trends' =>					'required|string',
			'status' =>					'required|string',
			'value_type' =>				'required|string',
			'allowed_hosts' =>			'',
			'units' =>					'required|string',
			'delta' =>					'required|string',
			'snmpv3_contextname' =>		'string',
			'snmpv3_securityname' =>	'required|string',
			'snmpv3_securitylevel' =>	'required|string',
			'snmpv3_authprotocol' =>	'string',
			'snmpv3_authpassphrase' =>	'required|string',
			'snmpv3_privprotocol' =>	'',
			'snmpv3_privpassphrase' =>	'required|string',
			'formula' =>				'required|string',
			'delay_flex' =>				'required|string',
			'params' =>					'required|string',
			'ipmi_sensor' =>			'required|string',
			'data_type' =>				'required|string',
			'authtype' =>				'required|string',
			'username' =>				'required|string',
			'password' =>				'required|string',
			'publickey' =>				'required|string',
			'privatekey' =>				'required|string',
			'port' =>					'required|string',
			'description' =>			'required|string',
			'inventory_link' =>			'required|string',
			'applications' =>			'array',
			'valuemap' =>				'array',
			'logtimefmt' =>				'string',
			'interface_ref' =>			'string'
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
	 * Templates validation.
	 *
	 * @param array $templates	import data
	 *
	 * @throws Exception	if structure or values is invalid
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

			$validator = new CNewValidator($templates, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateTemplate($template, $subpath);
		}
	}

	/**
	 * Template validation.
	 *
	 * @param array  $template	import data
	 * @param string $path		XML path
	 *
	 * @throws Exception	if structure or values is invalid
	 */
	protected function validateTemplate(array $template, $path) {
		$validationRules = array(
			'template' =>			'required|string',
			'name' =>				'required|string',
			'description' =>		'string',
			'templates' =>			'array',
			'groups' =>				'required|array',
			'applications' =>		'array',
			'items' =>				'array',
			'discovery_rules' =>	'array',
			'macros' =>				'array',
			'screens' =>			'array'
		);

		$validator = new CNewValidator($template, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// child elements validation
		if (array_key_exists('groups', $template)) {
			$this->validateGroups($template['groups'], $path.'/groups');
		}
		if (array_key_exists('items', $template)) {
			$this->validateItems($template['items'], $path.'/items');
		}
		if (array_key_exists('triggers', $template)) {
			$this->validateTriggers($template['triggers'], $path.'/triggers');
		}
		if (array_key_exists('templates', $template)) {
			$this->validateLinkedTemplates($template['templates'], $path.'/templates');
		}
		if (array_key_exists('graphs', $template)) {
			$this->validateGraphs($template['graphs'], $path.'/graphs');
		}
		if (array_key_exists('macros', $template)) {
			$this->validateMacros($template['macros'], $path.'/macros');
		}
		if (array_key_exists('screens', $template)) {
			$this->validateScreens($template['screens'], $path.'/screens');
		}
		if (array_key_exists('applications', $template)) {
			$this->validateApplications($template['applications'], $path.'/applications');
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
			'expression' =>		'required|string',
			'name' =>			'required|string',
			'url' =>			'required|string',
			'status' =>			'required|string',
			'priority' =>		'required|string',
			'description' =>	'required|string',
			'type' =>			'required|string',
			'dependencies' =>	'required|array'
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
			'yaxismin' =>			'required|string',
			'yaxismax' =>			'required|string',
			'show_work_period' =>	'required|string',
			'show_triggers' =>		'required|string',
			'type' =>				'required|string',
			'show_legend' =>		'required|string',
			'show_3d' =>			'required|string',
			'percent_left' =>		'required|string',
			'percent_right' =>		'required|string',
			'ymin_item_1' =>		'',
			'ymin_type_1' =>		'required|string',
			'ymax_item_1' =>		'',
			'ymax_type_1' =>		'required|string',
			'graph_items' =>		'array'
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

		if (array_key_exists('graph_items', $graph)) {
			$this->validateGraphItems($graph['graph_items'], $path.'/graph_items');
		}
	}

	/**
	 * Graph items validation.
	 *
	 * @param array  $graph_items		graph_items data
	 * @param string $path				XML path
	 *
	 * @throws Exception				if structure is invalid
	 */
	protected function validateGraphItems(array $graph_items, $path) {
		if (!$this->arrayValidator->validate('graph_item', $graph_items)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/graph_item('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$graphElementNumber = 1;
		foreach ($graph_items as $key => $graph_item) {
			$subpath = $path.'/graph_item('.$graphElementNumber++.')';

			$validator = new CNewValidator($graph_items, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateGraphItem($graph_item, $subpath);
		}
	}

	/**
	 * Graph item validation.
	 *
	 * @param array  $graph_item	graph_item data
	 * @param string $path			XML path
	 *
	 * @throws Exception			if structure is invalid
	 */
	protected function validateGraphItem(array $graph_item, $path) {
		$validationRules = array(
			'sortorder' =>	'required|string',
			'drawtype' =>	'required|string',
			'color' =>		'required|string',
			'yaxisside' =>	'required|string',
			'calc_fnc' =>	'required|string',
			'type' =>		'required|string',
			'item' =>		'required|array'
		);

		$validator = new CNewValidator($graph_item, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($graph_item, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}

		// child elements validation
		if (array_key_exists('item', $graph_item)) {
			$this->validateGraphItemData($graph_item['item'], $path.'/item');
		}
	}

	/**
	 * Graph item data validation.
	 *
	 * @param array  $item	$item data
	 * @param string $path	XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateGraphItemData(array $item, $path) {
		$validationRules = array(
			'host' =>	'required|string',
			'key' =>	'required|string'
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
				'screen_items' =>	'array'
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
			if (array_key_exists('screen_items', $screen)) {
				$this->validateScreenItems($screen['screen_items'], $subpath.'/screenitems');
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
		if (!$this->arrayValidator->validate('screen_item', $screenitems)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/screenitem('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$screenitemNumber = 1;
		foreach ($screenitems as $screenitem) {
			$subpath = $path.'/screenitem('.$screenitemNumber++.')';

			$validationRules = array(
				'resourcetype' =>	'required|string',
				'resource' =>		'required',
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
				'url' =>			'string',
				'application' =>	'string',
				'max_columns' =>	'string'
			);

			$validator = new CNewValidator($screenitem, $validationRules);

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $subpath, $errors[0]));
			}
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

	/**
	 * Maps validation.
	 *
	 * @param array $maps	import data
	 *
	 * @throws Exception	if structure or values is invalid
	 */
	protected function validateMaps(array $maps) {
		
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
			'macro' =>	'required|string',
			'value' =>	'required|string'
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

			$validator = new CNewValidator($applications, array($key => 'array'));

			if ($validator->isError()) {
				$errors = $validator->getAllErrors();
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
					$subpath, _('a character string is expected')
				));
			}

			$this->validateApplication($application, $subpath);
		}
	}

	/**
	 * Application validation.
	 *
	 * @param array  $application	application data
	 * @param string $path		XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateApplication(array $application, $path) {
		$validationRules = array(
			'name' =>	'required|string'
		);

		$validator = new CNewValidator($application, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($application, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}

	/**
	 * Linked templates validation.
	 *
	 * @param array  $templates	linked templates data
	 * @param string $path		XML path
	 *
	 * @throws Exception		if structure is invalid
	 */
	protected function validateLinkedTemplates(array $templates, $path) {
		if (!$this->arrayValidator->validate('template', $templates)) {
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.',
				$path.'/template('.$this->arrayValidator->getErrorSeqNum().')', $this->arrayValidator->getError()
			));
		}

		$templateNumber = 1;
		foreach ($templates as $key => $template) {
			$subpath = $path.'/template('.$templateNumber++.')';

			$validator = new CNewValidator($templates, array($key => 'array'));

			if ($validator->isError()) {
				throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $subpath, _('an array is expected')));
			}

			$this->validateLinkedTemplate($template, $subpath);
		}
	}

	/**
	 * Linked template validation.
	 *
	 * @param array  $template	linked template data
	 * @param string $path		XML path
	 *
	 * @throws Exception	if structure is invalid
	 */
	protected function validateLinkedTemplate(array $template, $path) {
		$validationRules = array(
			'name' =>	'required|string'
		);

		$validator = new CNewValidator($template, $validationRules);

		if ($validator->isError()) {
			$errors = $validator->getAllErrors();
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s', $path, $errors[0]));
		}

		// unexpected tag validation
		$arrayDiff = array_diff_key($template, $validator->getValidInput());
		if ($arrayDiff) {
			$error = _s('unexpected tag "%1$s"', key($arrayDiff));
			throw new Exception(_s('Cannot parse XML tag "%1$s": %2$s.', $path, $error));
		}
	}
}
