<?php
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


class CControllerMediatypeCreate extends CController {

	protected function checkInput() {
		$fields = [
			'type' =>					'required|db media_type.type|in '.implode(',', array_keys(CMediatypeHelper::getMediaTypes())),
			'name' =>					'db media_type.name|not_empty',
			'smtp_server' =>			'db media_type.smtp_server',
			'smtp_port' =>				'db media_type.smtp_port',
			'smtp_helo' =>				'db media_type.smtp_helo',
			'smtp_email' =>				'db media_type.smtp_email',
			'smtp_security' =>			'db media_type.smtp_security|in '.SMTP_CONNECTION_SECURITY_NONE.','.SMTP_CONNECTION_SECURITY_STARTTLS.','.SMTP_CONNECTION_SECURITY_SSL_TLS,
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
			'status' =>					'db media_type.status|in '.MEDIA_TYPE_STATUS_ACTIVE.','.MEDIA_TYPE_STATUS_DISABLED,
			'maxsessions' =>			'db media_type.maxsessions',
			'maxattempts' =>			'db media_type.maxattempts',
			'attempt_interval' =>		'db media_type.attempt_interval',
			'description' =>			'db media_type.description',
			'form_refresh' =>			'int32',
			'content_type' =>			'db media_type.content_type|in '.SMTP_MESSAGE_FORMAT_PLAIN_TEXT.','.SMTP_MESSAGE_FORMAT_HTML,
			'message_templates' =>		'array',
			'provider' =>				'int32|in '.implode(',', array_keys(CMediatypeHelper::getEmailProviders()))
		];

		$ret = $this->validateInput($fields);
		$error = $this->GetValidationError();

		if ($ret && $this->getInput('type') == MEDIA_TYPE_EMAIL) {
			$email_validator = new CEmailValidator();

			if (!$email_validator->validate($this->getInput('smtp_email'))) {
				error($email_validator->getError());
				$error = self::VALIDATION_ERROR;
				$ret = false;
			}
		}

		if (!$ret) {
			switch ($error) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot add media type'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	protected function doAction() {
		$mediatype = [];

		$this->getInputs($mediatype, ['type', 'name', 'status', 'maxsessions', 'maxattempts', 'attempt_interval',
			'description'
		]);
		$mediatype['message_templates'] = $this->getInput('message_templates', []);

		switch ($mediatype['type']) {
			case MEDIA_TYPE_EMAIL:
				$this->getInputs($mediatype, ['smtp_server', 'smtp_port', 'smtp_helo', 'smtp_email', 'smtp_security',
					'smtp_verify_peer', 'smtp_verify_host', 'smtp_authentication', 'passwd', 'content_type', 'provider'
				]);

				if ($mediatype['provider'] != CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
					preg_match('/.*<(?<email>.*[^>])>$/i', $this->getInput('smtp_email'), $match);
					$clean_email = $match ? $match['email'] : $this->getInput('smtp_email');

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
				else {
					if ($this->hasInput('smtp_username')) {
						$mediatype['username'] = $this->getInput('smtp_username');
					}
				}

				break;

			case MEDIA_TYPE_EXEC:
				$mediatype['parameters'] = [];

				$this->getInputs($mediatype, ['exec_path']);

				foreach (array_values($this->getInput('parameters_exec', [])) as $sortorder => $parameter) {
					$mediatype['parameters'][] = ['sortorder' => $sortorder, 'value' => $parameter['value']];
				}
				break;

			case MEDIA_TYPE_SMS:
				$this->getInputs($mediatype, ['gsm_modem']);
				$mediatype['maxsessions'] = 1;
				break;

			case MEDIA_TYPE_WEBHOOK:
				$mediatype['process_tags'] = ZBX_MEDIA_TYPE_TAGS_DISABLED;
				$mediatype['show_event_menu'] = ZBX_EVENT_MENU_HIDE;
				$this->getInputs($mediatype, ['script', 'timeout', 'process_tags', 'show_event_menu', 'event_menu_url',
					'event_menu_name'
				]);
				$parameters = $this->getInput('parameters_webhook', []);

				if (array_key_exists('name', $parameters) && array_key_exists('value', $parameters)) {
					$mediatype['parameters'] = array_map(function ($name, $value) {
							return compact('name', 'value');
						},
						$parameters['name'],
						$parameters['value']
					);
				}
				break;
		}

		$result = API::Mediatype()->create($mediatype);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'mediatype.list')
				->setArgument('page', CPagerHelper::loadPage('mediatype.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Media type added'));
		}
		else {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'mediatype.edit')
			);
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add media type'));
		}
		$this->setResponse($response);
	}
}
