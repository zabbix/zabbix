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


class CControllerMediatypeUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeid' =>			'fatal|required|db media_type.mediatypeid',
			'type' =>					'required|db media_type.type|in '.implode(',', array_keys(CMediatypeHelper::getMediaTypes())),
			'name' =>					'required|db media_type.name|not_empty',
			'smtp_server' =>			'db media_type.smtp_server',
			'smtp_port' =>				'db media_type.smtp_port',
			'smtp_helo' =>				'db media_type.smtp_helo',
			'smtp_email' =>				'db media_type.smtp_email',
			'smtp_security' =>			'db media_type.smtp_security|in '.SMTP_SECURITY_NONE.','.SMTP_SECURITY_STARTTLS.','.SMTP_SECURITY_SSL,
			'smtp_verify_peer' =>		'db media_type.smtp_verify_peer|in 0,1',
			'smtp_verify_host' =>		'db media_type.smtp_verify_host|in 0,1',
			'smtp_authentication' =>	'db media_type.smtp_authentication|in '.SMTP_AUTHENTICATION_NONE.','.SMTP_AUTHENTICATION_NORMAL,
			'exec_path' =>				'db media_type.exec_path',
			'gsm_modem' =>				'db media_type.gsm_modem',
			'smtp_username' =>			'db media_type.username',
			'passwd' =>					'db media_type.passwd',
			'parameters_exec' =>		'array',
			'parameters_webhook' =>		'array',
			'script' => 				'db media_type.script',
			'timeout' => 				'db media_type.timeout',
			'process_tags' =>			'in '.ZBX_MEDIA_TYPE_TAGS_DISABLED.','.ZBX_MEDIA_TYPE_TAGS_ENABLED,
			'show_event_menu' =>		'in '.ZBX_EVENT_MENU_HIDE.','.ZBX_EVENT_MENU_SHOW,
			'event_menu_url' =>			'db media_type.event_menu_url',
			'event_menu_name' =>		'db media_type.event_menu_name',
			'status' =>					'db media_type.status|in '.MEDIA_TYPE_STATUS_ACTIVE,
			'maxsessions' =>			'db media_type.maxsessions',
			'maxattempts' =>			'db media_type.maxattempts',
			'attempt_interval' =>		'db media_type.attempt_interval',
			'description' =>			'db media_type.description',
			'message_format' =>			'db media_type.message_format|in '.ZBX_MEDIA_MESSAGE_FORMAT_TEXT.','.ZBX_MEDIA_MESSAGE_FORMAT_HTML,
			'message_templates' =>		'array',
			'provider' => 				'int32|in '.implode(',', array_keys(CMediatypeHelper::getEmailProviders()))
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('type') == MEDIA_TYPE_EMAIL) {
			$email_validator = new CEmailValidator();

			if (!$email_validator->validate($this->getInput('smtp_email', ''))) {
				error($email_validator->getError());
				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update media type'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	protected function doAction(): void {
		$db_defaults = DB::getDefaults('media_type');

		$mediatype = [
			'mediatypeid' => $this->getInput('mediatypeid'),
			'type' =>  $this->getInput('type'),
			'name' => $this->getInput('name'),
			'maxsessions' => $this->getInput('maxsessions',  $db_defaults['maxsessions']),
			'maxattempts' =>  $this->getInput('maxattempts', $db_defaults['maxattempts']),
			'attempt_interval' => $this->getInput('attempt_interval', $db_defaults['attempt_interval']),
			'description' => $this->getInput('description', ''),
			'status' => $this->hasInput('status') ? MEDIA_TYPE_STATUS_ACTIVE : MEDIA_TYPE_STATUS_DISABLED,
			'message_templates' => $this->getInput('message_templates', [])
		];

		switch ($mediatype['type']) {
			case MEDIA_TYPE_EMAIL:
				$this->getInputs($mediatype, ['smtp_port', 'smtp_helo', 'smtp_security', 'smtp_authentication',
					'message_format', 'passwd'
				]);

				$smtp_email = $this->getInput('smtp_email', '');

				$mediatype['provider'] = $this->hasInput('provider') ? $this->getInput('provider') : null;
				$mediatype['smtp_server'] = $this->getInput('smtp_server', '');
				$mediatype['smtp_email'] = $smtp_email;
				$mediatype['smtp_verify_peer'] = $this->getInput('smtp_verify_peer', 0);
				$mediatype['smtp_verify_host'] = $this->getInput('smtp_verify_host', 0);

				if ($mediatype['provider'] != CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
					preg_match('/.*<(?<email>.*[^>])>$/i', $smtp_email, $match);
					$clean_email = $match ? $match['email'] : $smtp_email;

					$domain = substr($clean_email, strrpos($clean_email, '@') + 1);

					$mediatype['smtp_helo'] = $domain;

					if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_NORMAL) {
						$mediatype['username'] = $clean_email;
					}

					if ($mediatype['provider'] == CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY) {
						$formatted_domain = substr_replace($domain, '-', strrpos($domain, '.'), 1);
						$static_part = CMediatypeHelper::getEmailProviders($mediatype['provider'])['smtp_server'];

						$mediatype['smtp_server'] = $formatted_domain.$static_part;
					}
				}
				elseif ($this->hasInput('smtp_username')) {
					$mediatype['username'] = $this->getInput('smtp_username');
				}
				break;

			case MEDIA_TYPE_EXEC:
				$mediatype['parameters'] = [];
				$mediatype['exec_path'] = $this->getInput('exec_path', '');

				foreach (array_values($this->getInput('parameters_exec', [])) as $sortorder => $parameter) {
					$mediatype['parameters'][] = ['sortorder' => $sortorder, 'value' => $parameter['value']];
				}
				break;

			case MEDIA_TYPE_SMS:
				$mediatype['gsm_modem'] = $this->getInput('gsm_modem', '');
				$mediatype['maxsessions'] = 1;
				break;

			case MEDIA_TYPE_WEBHOOK:
				$this->getInputs($mediatype,
					['script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_name', 'event_menu_url']
				);

				$mediatype += [
					'process_tags' => ZBX_MEDIA_TYPE_TAGS_DISABLED,
					'show_event_menu' => ZBX_EVENT_MENU_HIDE,
					'event_menu_name' => '',
					'event_menu_url' => ''
				];

				$parameters = $this->getInput('parameters_webhook', []);
				$mediatype['parameters'] = [];

				if (array_key_exists('name', $parameters) && array_key_exists('value', $parameters)) {
					$mediatype['parameters'] = array_map(static fn($name, $value) => compact('name', 'value'),
						$parameters['name'],
						$parameters['value']
					);
				}
				break;
		}

		if ($mediatype['type'] != MEDIA_TYPE_EMAIL) {
			$mediatype['provider'] = CMediatypeHelper::EMAIL_PROVIDER_SMTP;
		}

		$result = API::Mediatype()->update($mediatype);
		$output = [];

		if ($result) {
			$output['success']['title'] = _('Media type updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => ('Cannot update media type'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
