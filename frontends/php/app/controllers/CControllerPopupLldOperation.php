<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupLldOperation extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {

		$fields = [
			'no' =>					'int32',
			'templated' =>			'in 0,1',
			'operationobject' =>	'in '.implode(',', [OPERATION_OBJECT_ITEM_PROTOTYPE, OPERATION_OBJECT_TRIGGER_PROTOTYPE, OPERATION_OBJECT_GRAPH_PROTOTYPE, OPERATION_OBJECT_HOST_PROTOTYPE]),
			'operator' =>			'in '.implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP]),
			'value' =>				'string',
			'opstatus' =>			'array',
			'opdiscover' =>			'array',
			'opperiod' =>			'array',
			'ophistory' =>			'array',
			'optrends' =>			'array',
			'opseverity' =>			'array',
			'optag' =>				'array',
			'optemplate' =>			'array',
			'opinventory' =>		'array',
//			'overrides_names' =>	'array',
			'validate' =>			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$actions = ['opstatus', 'opdiscover', 'opperiod', 'ophistory', 'optrends', 'opseverity', 'optag', 'optemplate',
			'opinventory'
		];
		$defaults = [
			'opstatus' => [
				'status' => 0 // TODO VM: use define
			],
			'opdiscover' => [
				'discover' => 1 // TODO VM: use define
			],
			'opperiod' => [
				'delay' => ''
			],
			'ophistory' => [
				'history' => ''
			],
			'optrends' => [
				'trends' => ''
			],
			'opseverity' => [
				'severity' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			'optag' => [],
			'optemplate' => [],
			'opinventory' => [
				'inventory_mode' => HOST_INVENTORY_MANUAL // TODO VM: are we sure this should be the default for new override action?
			],
		];

		$page_options = [
			'no' => $this->getInput('no', -1),
			'templated' => $this->getInput('templated', 0),
			'operationobject' => $this->getInput('operationobject', OPERATION_OBJECT_ITEM_PROTOTYPE),
			'operator' => $this->getInput('operator', CONDITION_OPERATOR_EQUAL),
			'value' => $this->getInput('value', ''),
//			'overrides_names' => $this->getInput('overrides_names', []) // TODO VM: replace by list of all conditions
		];

		foreach ($actions as $action) {
			if ($this->hasInput($action)) {
				$page_options[$action] = $this->getInput($action);
			}
		}

//		$page_options['follow_redirects'] = $this->getInput('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF);
//		$page_options['retrieve_mode'] = $this->getInput('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT);

		if ($this->hasInput('validate')) {
//			// Validate "Timeout" field manually, since it cannot be properly added into MVC validation rules.
//			$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);

//			if ($simple_interval_parser->parse($page_options['timeout']) != CParser::PARSE_SUCCESS) {
//				error(_s('Incorrect value for field "%1$s": %2$s.', 'timeout', _('a time unit is expected')));
//			}
//			elseif ($page_options['timeout'][0] !== '{') {
//				$seconds = timeUnitToSeconds($page_options['timeout']);

//				if ($seconds < 1 || $seconds > SEC_PER_HOUR) {
//					error(_s('Invalid parameter "%1$s": %2$s.', 'timeout',
//						_s('value must be one of %1$s', '1-'.SEC_PER_HOUR)
//					));
//				}
//			}

			/*
			 * "delay_flex" is a temporary field that collects flexible and scheduling intervals separated by a semicolon.
			 * In the end, custom intervals together with "delay" are stored in the "delay" variable.
			 */
			if (array_key_exists('opperiod', $page_options) && array_key_exists('delay_flex', $page_options['opperiod'])) {
				$intervals = [];
				$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);
				$time_period_parser = new CTimePeriodParser(['usermacros' => true]);
				$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true]);

				foreach ($page_options['opperiod']['delay_flex'] as $interval) {
					if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
						if ($interval['delay'] === '' && $interval['period'] === '') {
							continue;
						}

						if ($simple_interval_parser->parse($interval['delay']) != CParser::PARSE_SUCCESS) {
							$result = false;
							error(_s('Invalid interval "%1$s".', $interval['delay']));
							break;
						}
						elseif ($time_period_parser->parse($interval['period']) != CParser::PARSE_SUCCESS) {
							$result = false;
							error(_s('Invalid interval "%1$s".', $interval['period']));
							break;
						}

						$intervals[] = $interval['delay'].'/'.$interval['period'];
					}
					else {
						if ($interval['schedule'] === '') {
							continue;
						}

						if ($scheduling_interval_parser->parse($interval['schedule']) != CParser::PARSE_SUCCESS) {
							$result = false;
							error(_s('Invalid interval "%1$s".', $interval['schedule']));
							break;
						}

						$intervals[] = $interval['schedule'];
					}
				}

				if ($intervals) {
					// TODO VM: check, if delay is empty, return error(?)
					$page_options['opperiod']['delay'] .= ';'.implode(';', $intervals);
				}
			}

			// Return collected error messages.
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}
			else {
				// Return valid response.
				$params = [
					'operationobject' => $page_options['operationobject'],
					'operator' => $page_options['operator'],
					'value' => $page_options['value'],
					'no' => $page_options['no']
				];

				foreach ($page_options as $action => $values) {
					if ($action === 'opperiod') {
						$params['opperiod'] = [
							'delay' => $values['delay']
						];
					}
					elseif ($action === 'ophistory') {
						$params['ophistory'] = [
							'history' => ($values['history_mode'] == ITEM_STORAGE_OFF) ? '' : $values['history']
						];
					}
					elseif ($action === 'optrends') {
						$params['optrends'] = [
							'trends' => ($values['trends_mode'] == ITEM_STORAGE_OFF) ? '' : $values['trends']
						];
					}
					elseif ($action === 'optemplate') {
						// TODO VM: validate permissions to these templateids
						$params['optemplate'] = [];
						foreach ($values as $template) {
							$params['optemplate'][] = [
								'templateid' => $template
							];
						}
					}
					else {
						$params[$action] = $values;
					}
				}

				$output = [
					'params' => $params
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			// Combines received values and default, to use as values for all action fields.
			$field_values = [];
			foreach ($actions as $action) {
				if ($this->hasInput($action)) {
					$field_values[$action] = $page_options[$action];
				}
				else {
					$field_values[$action] = $defaults[$action];
				}
			}

			if (!array_key_exists('history_mode', $field_values['ophistory'])) {
				$field_values['ophistory']['history_mode'] = ($field_values['ophistory']['history'] === '')
					? ITEM_STORAGE_OFF
					: ITEM_STORAGE_CUSTOM;
			}
			if (!array_key_exists('trends_mode', $field_values['optrends'])) {
				$field_values['optrends']['trends_mode'] = ($field_values['optrends']['trends'] === '')
					? ITEM_STORAGE_OFF
					: ITEM_STORAGE_CUSTOM;
			}

			// TODO VM: strange solution
			if ($field_values['ophistory']['history_mode'] === ITEM_STORAGE_OFF
					&& $field_values['ophistory']['history'] === '') {
				$field_values['ophistory']['history'] = DB::getDefault('items', 'history');
			}
			if ($field_values['optrends']['trends_mode'] === ITEM_STORAGE_OFF
					&& $field_values['optrends']['trends'] === '') {
				$field_values['optrends']['trends'] = DB::getDefault('items', 'trends');
			}

			/* Delay calculation */
			$update_interval_parser = new CUpdateIntervalParser([
				'usermacros' => true,
				'lldmacros' => true
			]);
			$field_values['opperiod']['delay_flex'] = [];

			if ($update_interval_parser->parse($field_values['opperiod']['delay']) == CParser::PARSE_SUCCESS) {
				$field_values['opperiod']['delay'] = $update_interval_parser->getDelay();

				foreach ($update_interval_parser->getIntervals() as $interval) {
					if ($interval['type'] == ITEM_DELAY_FLEXIBLE) {
						$field_values['opperiod']['delay_flex'][] = [
							'delay' => $interval['update_interval'],
							'period' => $interval['time_period'],
							'type' => ITEM_DELAY_FLEXIBLE
						];
					}
					else {
						$field_values['opperiod']['delay_flex'][] = [
							'schedule' => $interval['interval'],
							'type' => ITEM_DELAY_SCHEDULING
						];
					}
				}
			}
			else {
				$field_values['opperiod']['delay'] = ZBX_ITEM_DELAY_DEFAULT;
			}
			/* EOF Delay calculation */ // TODO VM: Remove

			$field_values['optemplate'] = $field_values['optemplate']
				? CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => array_column($field_values['optemplate'], 'templateid')
				]), ['templateid' => 'id'])
				: [];

			$data = [
				// TODO VM: is this check working in all cases?
				'title' => ($page_options['no'] > 0) ? _('Edit Operation') : _('New Operation'), // TODO VM: are these final translations?
				'options' => $page_options,
				'field_values' => $field_values,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
