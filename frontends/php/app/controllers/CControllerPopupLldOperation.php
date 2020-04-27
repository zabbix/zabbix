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
		$page_options = [
			'no' => $this->getInput('no', -1),
			'templated' => $this->getInput('templated', 0),
			'operationobject' => $this->getInput('operationobject', OPERATION_OBJECT_ITEM_PROTOTYPE),
			'operator' => $this->getInput('operator', CONDITION_OPERATOR_EQUAL),
			'value' => $this->getInput('value', ''),
			'opstatus' => $this->getInput('opstatus', []),
			'opperiod' => $this->getInput('opperiod', []),
			'ophistory' => $this->getInput('ophistory', []),
			'optrends' => $this->getInput('optrends', []),
			'opseverity' => $this->getInput('opseverity', []),
			'optag' => $this->getInput('optag', []),
			'optemplate' => $this->getInput('optemplate', []),
			'opinventory' => $this->getInput('opinventory', []),
//			'overrides_names' => $this->getInput('overrides_names', [])
		];

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
					'opstatus' => $page_options['opstatus'],
					'opperiod' => $page_options['opperiod'],
					'ophistory' => $page_options['ophistory'],
					'optrends' => $page_options['optrends'],
					'opseverity' => $page_options['opseverity'],
					'optag' => $page_options['optag'],
					'optemplate' => $page_options['optemplate'],
					'opinventory' => $page_options['opinventory'],
					'no' => $page_options['no']
				];

				$output = [
					'params' => $params
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$data = [
				// TODO VM: is this check working?
				'title' => ($page_options['no'] > 0) ? _('Edit Operation') : _('New Operation'), // TODO VM: are these final translations?
				'options' => $page_options,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
