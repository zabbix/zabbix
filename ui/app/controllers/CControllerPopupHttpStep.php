<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerPopupHttpStep extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {

		$fields = [
			'no' =>					'int32',
			'httpstepid' =>			'db httpstep.httpstepid',
			'name' =>				'string|not_empty',
			'url' =>				'string|not_empty',
			'post_type' =>			'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_FORM]),
			'posts' =>				'string',
			'retrieve_mode' =>		'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'follow_redirects' =>	'in '.implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_ON, HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF]),
			'timeout' =>			'string|not_empty',
			'required' =>			'string',
			'status_codes' =>		'string',
			'templated' =>			'in 0,1',
			'old_name' =>			'string',
			'steps_names' =>		'array',
			'validate' =>			'in 1'
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
		$page_options = [
			'name' => $this->getInput('name', ''),
			'templated' => $this->getInput('templated', 0),
			'post_type' => $this->getInput('post_type', ZBX_POSTTYPE_FORM),
			'posts' => $this->getInput('posts', ''),
			'url' => $this->getInput('url', ''),
			'timeout' => $this->getInput('timeout', DB::getDefault('httpstep', 'timeout')),
			'required' => $this->getInput('required', ''),
			'status_codes' => $this->getInput('status_codes', ''),
			'old_name' => $this->getInput('old_name', ''),
			'httpstepid' => $this->getInput('httpstepid', 0),
			'no' => $this->getInput('no', -1),
			'steps_names' => $this->getInput('steps_names', [])
		];

		$page_options['follow_redirects'] = $this->getInput('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF);
		$page_options['retrieve_mode'] = $this->getInput('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT);

		if ($this->hasInput('validate')) {
			$output = [];

			// Validate "Timeout" field manually, since it cannot be properly added into MVC validation rules.
			$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);

			if ($simple_interval_parser->parse($page_options['timeout']) != CParser::PARSE_SUCCESS) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'timeout', _('a time unit is expected')));
			}
			elseif ($page_options['timeout'][0] !== '{') {
				$seconds = timeUnitToSeconds($page_options['timeout']);

				if ($seconds < 1 || $seconds > SEC_PER_HOUR) {
					error(_s('Invalid parameter "%1$s": %2$s.', 'timeout',
						_s('value must be one of %1$s', '1-'.SEC_PER_HOUR)
					));
				}
			}

			// Validate if step names are unique.
			if ($page_options['name'] !== $page_options['old_name']) {
				foreach ($page_options['steps_names'] as $name) {
					if ($name === $page_options['name']) {
						error(_s('Step with name "%1$s" already exists.', $name));
					}
				}
			}

			// Return collected error messages.
			if ($messages = get_and_clear_messages()) {
				$output['error']['messages'] = array_column($messages, 'message');
			}
			else {
				// Return valid response.
				$params = [
					'name' => $page_options['name'],
					'timeout' => $page_options['timeout'],
					'url' => $page_options['url'],
					'post_type' => $page_options['post_type'],
					'posts' => $page_options['posts'],
					'required' => $page_options['required'],
					'status_codes' => $page_options['status_codes'],
					'follow_redirects' => $page_options['follow_redirects'],
					'retrieve_mode' => $page_options['retrieve_mode'],
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
				'title' => _('Step of web scenario'),
				'options' => $page_options,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
