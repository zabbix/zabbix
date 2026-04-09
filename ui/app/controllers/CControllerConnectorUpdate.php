<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerConnectorUpdate extends CControllerConnectorUpdateGeneral {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		$api_uniq = ['connector.get', ['name' => '{name}'], 'connectorid'];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'connectorid' => ['db connector.connectorid', 'required'],
			'name' => ['db connector.name', 'required', 'not_empty'],
			'protocol' => ['db connector.protocol', 'required', 'in' => [ZBX_STREAMING_PROTOCOL_V1]],
			'data_type' => ['db connector.data_type', 'required',
				'in' => [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS]
			],
			'url' => ['db connector.url', 'required', 'not_empty',
				'use' => [CUrlValidator::class, []]
			],
			'item_value_types' => ['array', 'required', 'not_empty',
				'field' => ['integer',
					'in' => [ZBX_CONNECTOR_ITEM_VALUE_TYPE_UINT64, ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT,
						ZBX_CONNECTOR_ITEM_VALUE_TYPE_STR, ZBX_CONNECTOR_ITEM_VALUE_TYPE_LOG,
						ZBX_CONNECTOR_ITEM_VALUE_TYPE_TEXT, ZBX_CONNECTOR_ITEM_VALUE_TYPE_BIN,
						ZBX_CONNECTOR_ITEM_VALUE_TYPE_JSON
					]
				],
				'when' => ['data_type', 'in' => [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES]],
				'messages' => ['not_empty' => _('At least one type of information must be selected.')]
			],
			'authtype' => ['db connector.authtype', 'required',
				'in' => [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
					ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER
				]
			],
			'username' => ['db connector.username',
				'when' => ['authtype',
					'in' => [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST]
				]
			],
			'password' => ['db connector.password',
				'when' => ['authtype',
					'in' => [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST]
				]
			],
			'token' => ['db connector.token', 'required', 'not_empty',
				'when' => ['authtype', 'in' => [ZBX_HTTP_AUTH_BEARER]]
			],
			'http_proxy' => ['db connector.http_proxy'],
			'max_records_mode' => ['boolean'],
			'max_records' => ['db connector.max_records', 'required', 'min' => 0, 'max' => ZBX_MAX_INT32,
				'when' => ['max_records_mode', 'in' => [1]]
			],
			'max_senders' => ['db connector.max_senders', 'required', 'min' => 1, 'max' => 100],
			'max_attempts' => ['db connector.max_attempts', 'required', 'min' => 1, 'max' => 5],
			'attempt_interval' => ['db connector.attempt_interval', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 0, 'max' => 10]],
				'when' => ['max_attempts', 'not_in' => [0,1]]
			],
			'timeout' => ['db connector.timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => SEC_PER_MIN, 'usermacros' => true]]
			],
			'verify_peer' => ['db connector.verify_peer', 'required',
				'in' => [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]
			],
			'verify_host' => ['db connector.verify_host', 'required',
				'in' => [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]
			],
			'ssl_cert_file' => ['db connector.ssl_cert_file'],
			'ssl_key_file' => ['db connector.ssl_key_file'],
			'ssl_key_password' => ['db connector.ssl_key_password'],
			'description' => ['db connector.description'],
			'status' => ['db connector.status', 'required',
				'in' => [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED]
			],
			'tags_evaltype' => ['db connector.tags_evaltype', 'required',
				'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]
			],
			'tags' => ['objects', 'uniq' => ['tag', 'operator', 'value'],
				'fields' => [
					'operator' => ['db connector_tag.operator', 'required',
						'in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_LIKE,
							CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_NOT_LIKE
						]
					],
					'value' => ['db connector_tag.value', 'required',
						'when' => ['operator',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_EQUAL,
								CONDITION_OPERATOR_NOT_LIKE
							]
						]
					],
					'tag' => [
						['db connector_tag.tag', 'required'],
						['db connector_tag.tag', 'not_empty', 'when' => [
							['operator',
								'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_LIKE,
									CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_NOT_LIKE
								]
							],
							['value', 'not_empty']
						]]
					]
				],
				'messages' => ['uniq' => _('Tag filter name, operator and value combination is not unique.')]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update connector'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$connector = $this->getInputAll();

		// TODO: remove when DEV-4580 is merged
		self::removeInvalidWhenRuleInput($connector);

		self::processConnectorInput($connector);
		$result = API::Connector()->update($connector);

		$output = [];

		if ($result) {
			$output['success']['title'] = _('Connector updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update connector'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
