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


class CControllerPopupLldOverride extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {

		$fields = [
			'no' =>					'int32', // TODO VM: doublecheck validation rule
			'templated' =>			'in 0,1',
			'name' =>				'string|not_empty',
			'old_name' =>			'string',
			'stop' =>				'in 0,1',
			'overrides_evaltype' =>	'in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]),
			'overrides_formula' =>	'string',
			'overrides_filters' =>	'array',
			'operations' =>			'array',
			'overrides_names' =>	'array',
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
			'name' => $this->getInput('name', ''),
			'old_name' => $this->getInput('old_name', ''),
			'stop' => $this->getInput('stop', 0),
			'overrides_evaltype' => $this->getInput('overrides_evaltype', CONDITION_EVAL_TYPE_AND_OR),
			'overrides_formula' => $this->getInput('overrides_formula', ''),
			'overrides_filters' => $this->getInput('overrides_filters', []),
			'operations' => $this->getInput('operations', []),
			'overrides_names' => $this->getInput('overrides_names', [])
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

			// Validate if override names are unique.
			if ($page_options['name'] !== $page_options['old_name']) {
				foreach ($page_options['overrides_names'] as $name) {
					if ($name === $page_options['name']) {
						// TODO VM: new translation string
						error(_s('Override with name "%1$s" already exists.', $name));
					}
				}
			}

			// Return collected error messages.
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}
			else {
				// Return valid response.
				$params = [
					'name' => $page_options['name'],
					'stop' => $page_options['stop'],
					'overrides_evaltype' => $page_options['overrides_evaltype'],
					'overrides_formula' => $page_options['overrides_formula'],
					'overrides_filters' => $page_options['overrides_filters'],
					'operations' => $page_options['operations'],
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
				'title' => _('Override'),
				'options' => $page_options,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
