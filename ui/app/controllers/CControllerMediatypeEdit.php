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


class CControllerMediatypeEdit extends CController {

	/**
	 * @var array
	 */
	private $mediatype = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeid' => 'db media_type.mediatypeid'
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

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES)) {
			return false;
		}

		if ($this->hasInput('mediatypeid')) {
			$mediatypes = API::Mediatype()->get([
				'output' => ['mediatypeid', 'type', 'name', 'smtp_server', 'smtp_port', 'smtp_helo', 'smtp_email',
					'exec_path', 'gsm_modem', 'username', 'status', 'smtp_security', 'smtp_verify_peer',
					'smtp_verify_host', 'smtp_authentication', 'maxsessions', 'maxattempts', 'attempt_interval',
					'message_format', 'script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url',
					'event_menu_name', 'parameters', 'description', 'provider'
				],
				'selectMessageTemplates' => ['eventsource', 'recovery', 'subject', 'message'],
				'mediatypeids' => $this->getInput('mediatypeid')
			]);

			if (!$mediatypes) {
				return false;
			}

			$this->mediatype = $mediatypes[0];
		}

		return true;
	}

	protected function doAction(): void {
		// default values
		$db_defaults = DB::getDefaults('media_type');
		$email_defaults =  CMediatypeHelper::getEmailProviders(CMediatypeHelper::EMAIL_PROVIDER_SMTP);

		$data = [
			'mediatypeid' => null,
			'type' => MEDIA_TYPE_EMAIL,
			'provider' => CMediatypeHelper::EMAIL_PROVIDER_SMTP,
			'name' => '',
			'smtp_server' => $email_defaults['smtp_server'],
			'smtp_port' => $email_defaults['smtp_port'],
			'smtp_helo' => $db_defaults['smtp_helo'],
			'smtp_email' => $email_defaults['smtp_email'],
			'smtp_security' => $email_defaults['smtp_security'],
			'smtp_verify_peer' => $email_defaults['smtp_verify_peer'],
			'smtp_verify_host' => $email_defaults['smtp_verify_host'],
			'smtp_authentication' => $email_defaults['smtp_authentication'],
			'exec_path' => '',
			'gsm_modem' => '/dev/ttyS0',
			'smtp_username' => '',
			'passwd' => '',
			'status' => MEDIA_TYPE_STATUS_ACTIVE,
			'display_password_input' => true,
			'maxsessions' => $db_defaults['maxsessions'],
			'maxattempts' => $db_defaults['maxattempts'],
			'attempt_interval' => $db_defaults['attempt_interval'],
			'script' => $db_defaults['script'],
			'timeout' => $db_defaults['timeout'],
			'process_tags' => $db_defaults['process_tags'],
			'show_event_menu' => $db_defaults['show_event_menu'],
			'event_menu_url' => $db_defaults['event_menu_url'],
			'event_menu_name' => $db_defaults['event_menu_name'],
			'parameters_exec' => [],
			'parameters_webhook' => [
				['name' => 'URL', 'value'=> ''],
				['name' => 'HTTPProxy', 'value'=> ''],
				['name' => 'To', 'value' => '{ALERT.SENDTO}'],
				['name' => 'Subject', 'value' => '{ALERT.SUBJECT}'],
				['name' => 'Message', 'value' => '{ALERT.MESSAGE}']
			],
			'description' => '',
			'message_format' => $email_defaults['message_format'],
			'message_templates' => [],
			'providers' => CMediatypeHelper::getEmailProviders()
		];

		$message_templates = [];

		if ($this->hasInput('mediatypeid')) {
			$data = array_merge($data, $this->mediatype);

			switch ($data['type']) {
				case MEDIA_TYPE_EMAIL:
					$data['smtp_username'] = $this->mediatype['username'];
					$data['display_password_input'] =
						$this->mediatype['smtp_authentication'] != SMTP_AUTHENTICATION_NORMAL;
					break;

				case MEDIA_TYPE_EXEC:
					$data['parameters_exec'] = $this->mediatype['parameters'];
					break;

				case MEDIA_TYPE_SMS:
					$data['maxsessions'] = 1;
					break;

				case MEDIA_TYPE_WEBHOOK:
					$data['script'] = $this->mediatype['script'];
					$data['timeout'] = $this->mediatype['timeout'];
					$data['process_tags'] = $this->mediatype['process_tags'];
					$data['show_event_menu'] = $this->mediatype['show_event_menu'];
					$data['event_menu_url'] = $this->mediatype['event_menu_url'];
					$data['event_menu_name'] = $this->mediatype['event_menu_name'];
					$data['parameters_webhook'] = $this->mediatype['parameters'];

					CArrayHelper::sort($data['parameters_webhook'], ['name']);
					$data['parameters_webhook'] = array_values($data['parameters_webhook']);
					break;
			}
		}

		if ($message_templates) {
			CArrayHelper::sort($message_templates, ['recovery']);

			// Sort message templates in a certain order by event source.
			foreach ([EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE, EVENT_SOURCE_DISCOVERY,
					EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL] as $eventsource) {
				foreach ($message_templates as $index => $message_template) {
					if ($message_template['eventsource'] == $eventsource) {
						$data['message_templates'][] = $message_template;
						unset($message_templates[$index]);
					}
				}
			}
		}

		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of media types'));
		$this->setResponse($response);
	}
}
