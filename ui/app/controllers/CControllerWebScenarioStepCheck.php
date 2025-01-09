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


class CControllerWebScenarioStepCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'httpstepid' =>			'db httpstep.httpstepid',
			'name' =>				'required|not_empty|db httpstep.name',
			'old_name' =>			'string',
			'names' =>				'array',
			'url' =>				'required|not_empty|db httpstep.url',
			'timeout' =>			'required|not_empty|db httpstep.timeout',
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

		$ret = $this->validateInput($fields) && $this->validateFields();

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => $this->getInput('old_name', '') === ''
							? _('Cannot create web scenario step')
							: _('Cannot update web scenario step'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	private function validateFields(): bool {
		$ret = true;

		$name = $this->getInput('name');

		if ($name !== $this->getInput('old_name', '') && in_array($name, $this->getInput('names', []))) {
			error(_s('Step with name "%1$s" already exists.', $name));

			$ret = false;
		}

		if (CHtmlUrlValidator::validate($this->getInput('url')) === false) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'url', _('unacceptable URL')));

			$ret = false;
		}

		$timeout = $this->getInput('timeout');
		$simple_interval_parser = new CSimpleIntervalParser(['usermacros' => true]);

		if ($simple_interval_parser->parse($timeout) != CParser::PARSE_SUCCESS) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'timeout', _('a time unit is expected')));

			$ret = false;
		}
		elseif ($timeout[0] !== '{') {
			$seconds = timeUnitToSeconds($timeout);

			if ($seconds < 1 || $seconds > SEC_PER_HOUR) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'timeout',
					_s('value must be one of %1$s', '1-'.SEC_PER_HOUR)
				));

				$ret = false;
			}
		}

		if ($this->hasInput('status_codes')) {
			$status_codes = $this->getInput('status_codes');

			if ($status_codes !== ''
					&& (new CRangesParser(['usermacros' => true]))->parse($status_codes) != CParser::PARSE_SUCCESS) {
				error(_s('Invalid response code "%1$s".', $status_codes));

				$ret = false;
			}
		}

		$unique_variables = [];

		foreach (['query_fields', 'post_fields', 'variables', 'headers'] as $field) {
			foreach ($this->getInput($field, []) as $i => $pair) {
				if ($pair['name'] === '' && $pair['value'] !== '') {
					error(_s('Incorrect value for field "%1$s": %2$s.', $field.'/'.($i + 1).'/name',
						_('cannot be empty'))
					);

					$ret = false;
				}

				if ($field === 'variables' && $pair['name'] !== '') {
					if (preg_match('/^{[^{}]+}$/', $pair['name']) !== 1) {
						error(_s('Incorrect value for field "%1$s": %2$s.', $field.'/'.($i + 1).'/name',
							_('is not enclosed in {} or is malformed')
						));

						$ret = false;
					}

					if (array_key_exists($pair['name'], $unique_variables)) {
						error(_s('Incorrect value for field "%1$s": %2$s.', $field.'/'.($i + 1),
							_s('value %1$s already exists', '(name)=('.$pair['name'].')')
						));

						$ret = false;
					}

					$unique_variables[$pair['name']] = true;
				}
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		$db_defaults = DB::getDefaults('httpstep');

		$data = [
			'body' => [
				'httpstepid' => $this->getInput('httpstepid', 0),
				'name' => $this->getInput('name'),
				'url' => $this->getInput('url'),
				'timeout' => $this->getInput('timeout'),
				'posts' => $db_defaults['posts'],
				'required' => $this->getInput('required', $db_defaults['required']),
				'status_codes' => $this->getInput('status_codes', $db_defaults['status_codes']),
				'follow_redirects' => $this->getInput('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF),
				'retrieve_mode' => $this->getInput('retrieve_mode', $db_defaults['retrieve_mode']),
				'post_type' => $this->getInput('post_type', ZBX_POSTTYPE_FORM),
				'query_fields' => [],
				'post_fields' => [],
				'variables' => [],
				'headers' => []
			]
		];

		if ($data['body']['retrieve_mode'] == HTTPTEST_STEP_RETRIEVE_MODE_HEADERS) {
			$data['body']['posts'] = $db_defaults['posts'];
			$data['body']['post_fields'] = [];
		}
		elseif ($data['body']['post_type'] == ZBX_POSTTYPE_FORM) {
			$data['body']['posts'] = $db_defaults['posts'];
			$data['body']['post_fields'] = [];

			foreach ($this->getInput('post_fields', []) as $pair) {
				if ($pair['name'] === '' && $pair['value'] === '') {
					continue;
				}

				$data['body']['post_fields'][] = $pair;
			}
		}
		else {
			$data['body']['posts'] = $this->getInput('posts', $db_defaults['posts']);
			$data['body']['post_fields'] = [];
		}

		foreach (['query_fields', 'variables', 'headers'] as $field) {
			foreach ($this->getInput($field, []) as $pair) {
				if ($pair['name'] === '' && $pair['value'] === '') {
					continue;
				}

				$data['body'][$field][] = $pair;
			}
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
