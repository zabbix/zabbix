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


class CControllerMediatypeCreate extends CControllerMediatypeUpdateGeneral {

	public static function getValidationRules(): array {
		$api_uniq = [
			'mediatype.get', ['name' => '{name}']
		];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'type' => ['db media_type.type', 'required',
				'in' => [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK]
			],
			'name' => ['db media_type.name', 'required', 'not_empty'],
			'provider' => ['db media_type.provider', 'in' => array_keys(CMediatypeHelper::getEmailProviders()),
				'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL]]
			],
			'smtp_server' => ['db media_type.smtp_server', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]]
				]
			],
			'smtp_port' => ['db media_type.smtp_port', 'required',
				'min' => ZBX_MIN_PORT_NUMBER, 'max' => ZBX_MAX_PORT_NUMBER,
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]]
				]
			],
			'smtp_helo' => ['db media_type.smtp_helo', 'required',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]]
				]
			],
			'smtp_email' =>	['db media_type.smtp_email', 'required', 'not_empty',
				'use' => [CEmailValidator::class, []],
				'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL]]
			],
			'smtp_security' => ['db media_type.smtp_security', 'required',
				'in' => [SMTP_SECURITY_NONE, SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL],
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]]
				]
			],
			'smtp_verify_peer' => ['boolean',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]],
					['smtp_security', 'in' => [SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL]]
				]
			],
			'smtp_verify_host' => ['boolean',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]],
					['smtp_security', 'in' => [SMTP_SECURITY_STARTTLS, SMTP_SECURITY_SSL]]
				]
			],
			'smtp_authentication' => ['db media_type.smtp_authentication', 'required',
				'in' =>[SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_PASSWORD, SMTP_AUTHENTICATION_OAUTH],
				'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL]]
			],
			'smtp_username' => ['db media_type.username', 'required',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_PASSWORD]]
				]
			],
			'passwd' =>	[
				['db media_type.passwd', 'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_PASSWORD]]
				]],
				['db media_type.passwd', 'not_empty', 'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['provider', 'not_in' => [CMediatypeHelper::EMAIL_PROVIDER_SMTP]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_PASSWORD]]
				]]
			],
			'tokens_status' => [
				['db media_type_oauth.tokens_status', 'required',
					'in' => [OAUTH_ACCESS_TOKEN_VALID | OAUTH_REFRESH_TOKEN_VALID],
					'when' => [
						['type', 'in' => [MEDIA_TYPE_EMAIL]],
						['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]]
					],
					'messages' => ['in' => _('Invalid OAuth configuration')]
				]
			],
			'redirection_url' => ['db media_type_oauth.redirection_url', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]],
					['tokens_status', 'not_in' => [0]]
				],
				'messages' => ['not_empty' => _('Invalid OAuth configuration')]
			],
			'client_id' => ['db media_type_oauth.client_id', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]],
					['redirection_url', 'not_empty']
				],
				'messages' => ['not_empty' => _('Invalid OAuth configuration')]
			],
			'client_secret' => ['db media_type_oauth.client_secret', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]]
				]
			],
			'authorization_url' => ['db media_type_oauth.authorization_url', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]],
					['client_id', 'not_empty']
				],
				'messages' => ['not_empty' => _('Invalid OAuth configuration')]
			],
			'token_url' => ['db media_type_oauth.token_url', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]],
					['authorization_url', 'not_empty']
				],
				'messages' => ['not_empty' => _('Invalid OAuth configuration')]
			],
			'access_token' => ['db media_type_oauth.access_token',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]]
				]
			],
			'access_token_updated' => ['db media_type_oauth.access_token_updated',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]]
				]
			],
			'access_expires_in' => ['db media_type_oauth.access_expires_in',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]]
				]
			],
			'refresh_token' => ['db media_type_oauth.refresh_token',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_EMAIL]],
					['smtp_authentication', 'in' => [SMTP_AUTHENTICATION_OAUTH]]
				]
			],
			'message_format' =>	['db media_type.message_format',
				'in' => [ZBX_MEDIA_MESSAGE_FORMAT_TEXT, ZBX_MEDIA_MESSAGE_FORMAT_HTML],
				'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL]]
			],
			'gsm_modem' => ['db media_type.gsm_modem', 'required', 'not_empty',
				'when' => ['type', 'in' => [MEDIA_TYPE_SMS]]
			],
			'exec_path' => ['db media_type.exec_path', 'required', 'not_empty',
				'when' => ['type', 'in' => [MEDIA_TYPE_EXEC]]
			],
			'parameters_exec' => ['objects', 'required',
				'fields' => ['value' => ['string']],
				'when' => ['type', 'in' => [MEDIA_TYPE_EXEC]]
			],
			'parameters_webhook' =>	['objects', 'required', 'uniq' => ['name'],
				'fields' => [
					'value' => ['string', 'required'],
					'name' => [
						['string', 'required'],
						['string', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
					]
				],
				'when' => ['type', 'in' => [MEDIA_TYPE_WEBHOOK]],
				'messages' => ['uniq' => _('Name is not unique.')]
			],
			'script' => ['db media_type.script', 'required', 'not_empty',
				'when' => ['type', 'in' => [MEDIA_TYPE_WEBHOOK]]
			],
			'timeout' => ['db media_type.timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => SEC_PER_MIN]],
				'when' => ['type', 'in' => [MEDIA_TYPE_WEBHOOK]]
			],
			'process_tags' => ['db media_type.process_tags',
				'in' => [ZBX_MEDIA_TYPE_TAGS_DISABLED, ZBX_MEDIA_TYPE_TAGS_ENABLED],
				'when' => ['type', 'in' => [MEDIA_TYPE_WEBHOOK]]
			],
			'show_event_menu' => ['db media_type.show_event_menu', 'in' => [ZBX_EVENT_MENU_HIDE, ZBX_EVENT_MENU_SHOW],
				'when' => ['type', 'in' => [MEDIA_TYPE_WEBHOOK]]
			],
			'event_menu_url' =>	['db media_type.event_menu_url', 'required', 'not_empty',
				// 'use' => [CHtmlUrlValidator::class, ['allow_event_tags_macro' => true, 'allow_user_macro' => false]],
				'when' => [
					['type', 'in' => [MEDIA_TYPE_WEBHOOK]],
					['show_event_menu', 'in' => [ZBX_EVENT_MENU_SHOW]]
				]
			],
			'event_menu_name' => ['db media_type.event_menu_name', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [MEDIA_TYPE_WEBHOOK]],
					['show_event_menu', 'in' => [ZBX_EVENT_MENU_SHOW]]
				]
			],
			'description' => ['db media_type.description'],
			'status' =>	['db media_type.status', 'in' => [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED]],
			'message_templates' => ['objects', 'uniq' => ['eventsource', 'recovery'], 'fields' => [
				'eventsource' => ['db media_type_message.eventsource', 'required',
					'in' => [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
						EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
					]
				],
				'recovery' => [
					['db media_type_message.recovery', 'required',
						'in' => [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION],
						'when' => ['eventsource', 'in' => [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE]]
					],
					['db media_type_message.recovery', 'required',
						'in' => [ACTION_OPERATION],
						'when' => ['eventsource', 'in' => [EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION]]
					],
					['db media_type_message.recovery', 'required',
						'in' => [ACTION_OPERATION, ACTION_RECOVERY_OPERATION],
						'when' => ['eventsource', 'in' => [EVENT_SOURCE_INTERNAL]]
					]
				],
				'subject' => ['db media_type_message.subject'],
				'message' => ['db media_type_message.message']
			]],
			'maxsessions' => ['db media_type.maxsessions', 'min' => 0, 'max' => 100,
				'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_WEBHOOK]]
			],
			'maxattempts' => ['db media_type.maxattempts', 'min' => 1, 'max' => 100],
			'attempt_interval' => ['db media_type.attempt_interval', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 0, 'max' => SEC_PER_HOUR]]
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
					'title' => _('Cannot add media type'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function doAction(): void {
		$mediatype = self::processMediatypeData($this->getInputAll());

		$result = API::Mediatype()->create($mediatype);
		$output = [];

		if ($result) {
			$output['success']['title'] = _('Media type added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add media type'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
