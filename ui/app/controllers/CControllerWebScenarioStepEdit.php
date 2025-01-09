<?php declare(strict_types = 0);
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


class CControllerWebScenarioStepEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'edit' => 				'in 1',
			'templated' =>			'in 0,1',
			'httpstepid' =>			'db httpstep.httpstepid',
			'name' =>				'db httpstep.name',
			'names' =>				'array',
			'url' =>				'db httpstep.url',
			'timeout' =>			'db httpstep.timeout',
			'posts' =>				'db httpstep.posts',
			'required' =>			'db httpstep.required',
			'status_codes' =>		'db httpstep.status_codes',
			'follow_redirects' =>	'db httpstep.follow_redirects|in '.implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF, HTTPTEST_STEP_FOLLOW_REDIRECTS_ON]),
			'retrieve_mode' =>		'db httpstep.retrieve_mode|in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS, HTTPTEST_STEP_RETRIEVE_MODE_BOTH]),
			'post_type' =>			'db httpstep.post_type|in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_FORM]),
			'query_fields' =>		'array',
			'post_fields' =>		'array',
			'variables' =>			'array',
			'headers' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('edit')) {
			$fields = [
				'name' =>		'required',
				'url' =>		'required',
				'timeout' =>	'required'
			];

			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
		}

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

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		$db_defaults = DB::getDefaults('httpstep');

		if ($this->hasInput('edit')) {
			$form = [
				'name' => $this->getInput('name'),
				'url' => $this->getInput('url'),
				'timeout' => $this->getInput('timeout', $db_defaults['timeout']),
				'posts' => $this->getInput('posts', $db_defaults['posts']),
				'required' => $this->getInput('required', $db_defaults['required']),
				'status_codes' => $this->getInput('status_codes', $db_defaults['status_codes']),
				'follow_redirects' => (int) $this->getInput('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF),
				'retrieve_mode' => (int) $this->getInput('retrieve_mode', $db_defaults['retrieve_mode']),
				'post_type' => (int) $this->getInput('post_type', ZBX_POSTTYPE_FORM),
				'query_fields' => $this->getInput('query_fields', []),
				'post_fields' => $this->getInput('post_fields', []),
				'variables' => $this->getInput('variables', []),
				'headers' => $this->getInput('headers', [])
			];
		}
		else {
			$form = [
				'name' => $db_defaults['name'],
				'url' => $db_defaults['url'],
				'timeout' => $db_defaults['timeout'],
				'posts' => $db_defaults['posts'],
				'required' => $db_defaults['required'],
				'status_codes' => $db_defaults['status_codes'],
				'follow_redirects' => HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF,
				'retrieve_mode' => (int) $db_defaults['retrieve_mode'],
				'post_type' => ZBX_POSTTYPE_FORM,
				'query_fields' => [],
				'post_fields' => [],
				'variables' => [],
				'headers' => []
			];
		}

		foreach (['query_fields', 'variables', 'headers'] as $field) {
			if (!$form[$field]) {
				$form[$field] = [['name' => '', 'value' => '']];
			}
		}

		if ($form['post_type'] == ZBX_POSTTYPE_FORM && !$form['post_fields']) {
			$form['post_fields'] = [['name' => '', 'value' => '']];
		}

		$data = [
			'is_edit' => $this->hasInput('edit'),
			'templated' => (bool) $this->getInput('templated', 0),
			'httpstepid' => $this->getInput('httpstepid', 0),
			'names' => $this->getInput('names', []),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
