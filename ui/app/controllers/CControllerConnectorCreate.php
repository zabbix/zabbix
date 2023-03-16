<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerConnectorCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>					'required|not_empty|db connector.name',
			'protocol' =>				'db connector.protocol|in '.ZBX_STREAMING_PROTOCOL_V1,
			'data_type' =>				'db connector.data_type|in '.implode(',', [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS]),
			'url' =>					'required|not_empty|db connector.url',
			'authtype' =>				'db connector.authtype|in '.implode(',', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER]),
			'username' =>				'db connector.username',
			'password' =>				'db connector.password',
			'token' =>					'db connector.token',
			'advanced_configuration' =>	'in 1',
			'http_proxy' =>				'db connector.http_proxy',
			'max_records_mode' =>		'in 0,1',
			'max_records' =>			'db connector.max_records|ge 0',
			'max_senders' =>			'db connector.max_senders|ge 1|le 100',
			'max_attempts' =>			'db connector.max_attempts|ge 1|le 5',
			'timeout' =>				'db connector.timeout',
			'verify_peer' =>			'db connector.verify_peer|in '.implode(',', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]),
			'verify_host' =>			'db connector.verify_host|in '.implode(',', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]),
			'ssl_cert_file' =>			'db connector.ssl_cert_file',
			'ssl_key_file' =>			'db connector.ssl_key_file',
			'ssl_key_password' =>		'db connector.ssl_key_password',
			'description' =>			'db connector.description',
			'status' =>					'db connector.status|in '.implode(',', [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED]),
			'tags_evaltype' =>			'db connector.tags_evaltype|in '.implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]),
			'tags' =>					'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = [];

			if ($this->getInput('authtype', ZBX_HTTP_AUTH_NONE) == ZBX_HTTP_AUTH_BEARER) {
				$fields['token'] = 'required|not_empty';
			}

			if ($this->hasInput('advanced_configuration')) {
				$fields += [
					'max_records' =>	'required',
					'max_senders' =>	'required',
					'max_attempts' =>	'required',
					'timeout' =>		'required|not_empty'
				];
			}

			if ($fields) {
				$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				$ret = !$validator->isErrorFatal() && !$validator->isError();
			}
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
			'authtype' => $this->getInput('authtype', $db_defaults['authtype']),
			'status' => $this->getInput('status', ZBX_CONNECTOR_STATUS_DISABLED),
			'tags' => []
		];

		$this->getInputs($connector, ['name', 'protocol', 'data_type', 'url', 'description', 'tags_evaltype']);

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

		if ($this->hasInput('advanced_configuration')) {
			$connector['max_records'] = $this->getInput('max_records_mode', 0) == 1
				? $this->getInput('max_records')
				: $db_defaults['max_records'];

			$this->getInputs($connector, ['max_senders', 'max_attempts', 'timeout', 'http_proxy', 'ssl_cert_file',
				'ssl_key_file', 'ssl_key_password'
			]);

			$connector['verify_peer'] = $this->getInput('verify_peer', ZBX_HTTP_VERIFY_PEER_OFF);
			$connector['verify_host'] = $this->getInput('verify_host', ZBX_HTTP_VERIFY_HOST_OFF);
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
