<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupLldOperation extends CController {

	protected function init() {
		$this->disableCsrfValidation();
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
			'validate' =>			'in 1',
			'visible' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
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
				'status' => ZBX_PROTOTYPE_STATUS_ENABLED
			],
			'opdiscover' => [
				'discover' => ZBX_PROTOTYPE_NO_DISCOVER
			],
			'opperiod' => [
				'delay' => ''
			],
			'ophistory' => [
				'history' => '0'
			],
			'optrends' => [
				'trends' => '0'
			],
			'opseverity' => [
				'severity' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			'optag' => [],
			'optemplate' => [],
			'opinventory' => [
				'inventory_mode' => HOST_INVENTORY_MANUAL
			]
		];

		$page_options = [
			'no' => $this->getInput('no', -1),
			'templated' => $this->getInput('templated', 0),
			'operationobject' => $this->getInput('operationobject', OPERATION_OBJECT_ITEM_PROTOTYPE),
			'operator' => $this->getInput('operator', CONDITION_OPERATOR_EQUAL),
			'value' => $this->getInput('value', '')
		];

		$visible = $this->getInput('visible', []);

		foreach ($actions as $action) {
			if ($this->hasInput($action)) {
				$page_options[$action] = $this->getInput($action);
			}
			elseif (array_key_exists($action, $visible)) {
				// Some actions can not have relevant input fields, if override intent was to clear them.
				$page_options[$action] = $defaults[$action];
			}
		}

		if ($this->hasInput('validate')) {
			if (!$visible) {
				error(_('At least one action is mandatory.'));
			}

			if (array_key_exists('optag', $page_options)) {
				foreach ($page_options['optag'] as $i => $optag) {
					if ($optag['tag'] === '' && $optag['value'] === '') {
						unset($page_options['optag'][$i]);
					}
					elseif ($optag['tag'] === '') {
						error(_s('Incorrect value for field "%1$s": %2$s.', _('Tag'), _('cannot be empty')));
					}
				}

				$page_options['optag'] = array_values($page_options['optag']);

				if (!$page_options['optag']) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Tags'), _('cannot be empty')));
				}
			}

			if (array_key_exists('optemplate', $page_options) && !$page_options['optemplate']) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Link templates'), _('cannot be empty')));
			}

			/*
			 * $page_options['opperiod']['delay_flex'] is a temporary field that collects flexible and scheduling
			 * intervals separated by a semicolon. In the end, custom intervals together with
			 * $page_options['opperiod']['delay'] are stored in the $page_options['opperiod']['delay'] variable.
			 */
			if (array_key_exists('opperiod', $page_options)) {
				if (!array_key_exists('delay', $page_options['opperiod'])) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Update interval'),
						_('a time unit is expected'))
					);
				}

				$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true, 'lldmacros' => true]);
				$result = true;

				if (array_key_exists('delay_flex', $page_options['opperiod'])) {
					$intervals = [];
					$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true, 'lldmacros' => true]);
					$time_period_parser = new CTimePeriodParser(['usermacros' => true, 'lldmacros' => true]);
					$scheduling_interval_parser = new CSchedulingIntervalParser(['usermacros' => true,
						'lldmacros' => true]
					);

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
						$page_options['opperiod']['delay'] .= ';'.implode(';', $intervals);
					}
				}

				if ($result && !validateDelay($update_interval_parser, _('Update interval'),
						$page_options['opperiod']['delay'], $error)) {
					error($error);
				}
			}

			if (array_key_exists('ophistory', $page_options)
					&& array_key_exists('history_mode', $page_options['ophistory'])
					&& $page_options['ophistory']['history_mode'] == ITEM_STORAGE_CUSTOM
					&& !validateTimeUnit($page_options['ophistory']['history'], SEC_PER_HOUR, 25 * SEC_PER_YEAR, true,
						$error, ['usermacros' => true, 'lldmacros' => true])) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('History'), $error));
			}

			if (array_key_exists('optrends', $page_options)
					&& array_key_exists('trends_mode', $page_options['optrends'])
					&& $page_options['optrends']['trends_mode'] == ITEM_STORAGE_CUSTOM
					&& !validateTimeUnit($page_options['optrends']['trends'], SEC_PER_DAY, 25 * SEC_PER_YEAR, true,
						$error, ['usermacros' => true, 'lldmacros' => true])) {
				error(_s('Incorrect value for field "%1$s": %2$s.', _('Trends'), $error));
			}

			// Return collected error messages.
			if ($messages = get_and_clear_messages()) {
				$output['error']['messages'] = array_column($messages, 'message');
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
							'history' => ($values['history_mode'] == ITEM_STORAGE_OFF) ? '0' : $values['history']
						];
					}
					elseif ($action === 'optrends') {
						$params['optrends'] = [
							'trends' => ($values['trends_mode'] == ITEM_STORAGE_OFF) ? '0' : $values['trends']
						];
					}
					elseif ($action === 'optemplate') {
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
			// Combines received and default values to use as values for all action fields.
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
				$field_values['ophistory']['history_mode'] = ($field_values['ophistory']['history'] === '0')
					? ITEM_STORAGE_OFF
					: ITEM_STORAGE_CUSTOM;
			}
			if (!array_key_exists('trends_mode', $field_values['optrends'])) {
				$field_values['optrends']['trends_mode'] = ($field_values['optrends']['trends'] === '0')
					? ITEM_STORAGE_OFF
					: ITEM_STORAGE_CUSTOM;
			}

			if ($field_values['ophistory']['history_mode'] === ITEM_STORAGE_OFF
					&& $field_values['ophistory']['history'] === '0') {
				$field_values['ophistory']['history'] = DB::getDefault('items', 'history');
			}
			if ($field_values['optrends']['trends_mode'] === ITEM_STORAGE_OFF
					&& $field_values['optrends']['trends'] === '0') {
				$field_values['optrends']['trends'] = DB::getDefault('items', 'trends');
			}

			// Delay calculation.
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

			$field_values['optemplate'] = $field_values['optemplate']
				? CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => array_column($field_values['optemplate'], 'templateid')
				]), ['templateid' => 'id'])
				: [];

			$data = [
				'title' => ($page_options['no'] > 0) ? _('Edit operation') : _('New operation'),
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
