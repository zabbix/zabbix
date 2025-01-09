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


class CControllerConnectorCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>				'required|not_empty|db connector.name',
			'protocol' =>			'db connector.protocol|in '.ZBX_STREAMING_PROTOCOL_V1,
			'data_type' =>			'db connector.data_type|in '.implode(',', [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS]),
			'url' =>				'required|not_empty|db connector.url',
			'item_value_types' =>	'array',
			'authtype' =>			'db connector.authtype|in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER]),
			'username' =>			'db connector.username',
			'password' =>			'db connector.password',
			'token' =>				'db connector.token',
			'http_proxy' =>			'db connector.http_proxy',
			'max_records_mode' =>	'in 0,1',
			'max_records' =>		'db connector.max_records|ge 0',
			'max_senders' =>		'db connector.max_senders|ge 1|le 100',
			'max_attempts' =>		'db connector.max_attempts|ge 1|le 5',
			'attempt_interval' =>	'db connector.attempt_interval',
			'timeout' =>			'db connector.timeout',
			'verify_peer' =>		'db connector.verify_peer|in '.implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]),
			'verify_host' =>		'db connector.verify_host|in '.implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
			'ssl_cert_file' =>		'db connector.ssl_cert_file',
			'ssl_key_file' =>		'db connector.ssl_key_file',
			'ssl_key_password' =>	'db connector.ssl_key_password',
			'description' =>		'db connector.description',
			'status' =>				'db connector.status|in '.implode(',', [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED]),
			'tags_evaltype' =>		'db connector.tags_evaltype|in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]),
			'tags' =>				'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = [
				'max_records' =>	'required',
				'max_senders' =>	'required',
				'max_attempts' =>	'required',
				'timeout' =>		'required|not_empty'
			];

			$data_type = $this->getInput('data_type', ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES);

			if ($data_type == ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES) {
				$fields['item_value_types'] = 'required';
			}

			if ($this->getInput('authtype', ZBX_HTTP_AUTH_NONE) == ZBX_HTTP_AUTH_BEARER) {
				$fields['token'] = 'required|not_empty';
			}

			if ($this->getInput('max_attempts', DB::getDefault('connector', 'max_attempts')) > 1) {
				$fields['attempt_interval'] = 'required|not_empty';
			}

			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

			foreach ($validator->getAllErrors() as $error) {
				info($error);
			}

			$ret = !$validator->isErrorFatal() && !$validator->isError();
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot create connector'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$db_defaults = DB::getDefaults('connector');

		$connector = [
			'data_type' => $this->getInput('data_type', $db_defaults['data_type']),
			'authtype' => $this->getInput('authtype', $db_defaults['authtype']),
			'max_records' => $this->getInput('max_records_mode', 0) == 1
				? $this->getInput('max_records')
				: $db_defaults['max_records'],
			'max_attempts' => $this->getInput('max_attempts', $db_defaults['max_attempts']),
			'verify_peer' => $this->getInput('verify_peer', ZBX_HTTP_VERIFY_PEER_OFF),
			'verify_host' => $this->getInput('verify_host', ZBX_HTTP_VERIFY_HOST_OFF),
			'status' => $this->getInput('status', ZBX_CONNECTOR_STATUS_DISABLED),
			'tags' => []
		];

		$this->getInputs($connector, ['name', 'protocol', 'url', 'max_senders', 'timeout', 'http_proxy',
			'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'description', 'tags_evaltype'
		]);

		if ($connector['data_type'] == ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES) {
			$connector['item_value_type'] = array_sum($this->getInput('item_value_types'));
		}

		switch ($connector['authtype']) {
			case ZBX_HTTP_AUTH_BASIC:
			case ZBX_HTTP_AUTH_NTLM:
			case ZBX_HTTP_AUTH_KERBEROS:
			case ZBX_HTTP_AUTH_DIGEST:
				$this->getInputs($connector, ['username', 'password']);
				break;

			case ZBX_HTTP_AUTH_BEARER:
				$connector['token'] = $this->getInput('token');
		}

		if ($connector['max_attempts'] > 1) {
			$connector['attempt_interval'] = $this->getInput('attempt_interval');
		}

		foreach ($this->getInput('tags', []) as $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				continue;
			}

			if ($tag['operator'] == CONDITION_OPERATOR_EXISTS || $tag['operator'] == CONDITION_OPERATOR_NOT_EXISTS) {
				if ($tag['tag'] === '') {
					continue;
				}

				unset($tag['value']);
			}

			$connector['tags'][] = $tag;
		}

		$result = API::Connector()->create($connector);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Connector created');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot create connector'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
