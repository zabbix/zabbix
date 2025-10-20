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


abstract class CControllerMediatypeUpdateGeneral extends CController {

	final protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	final protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	final protected function processMediatypeData(&$mediatype): void {
		if ($mediatype['type'] == MEDIA_TYPE_EMAIL) {
			if ($mediatype['provider'] === CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
				$mediatype['username'] = $mediatype['smtp_username'];
				unset($mediatype['smtp_username']);
			}
			else {
				preg_match('/.*<(?<email>.*[^>])>$/i', $mediatype['smtp_email'], $match);
				$clean_email = $match ? $match['email'] : $mediatype['smtp_email'];

				$domain = substr($clean_email, strrpos($clean_email, '@') + 1);

				$mediatype['smtp_helo'] = $domain;

				if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
					$mediatype['username'] = $clean_email;
				}

				if ($mediatype['provider'] == CMediatypeHelper::EMAIL_PROVIDER_OFFICE365_RELAY) {
					$formatted_domain = str_replace('.', '-', $domain);
					$static_part = CMediatypeHelper::getEmailProviders($mediatype['provider'])['smtp_server'];

					$mediatype['smtp_server'] = $formatted_domain.$static_part;
				}
			}
		}
		elseif ($mediatype['type'] == MEDIA_TYPE_SMS) {
			$mediatype['maxsessions'] = 1;
		}
		elseif ($mediatype['type'] == MEDIA_TYPE_WEBHOOK) {
			$mediatype['parameters'] = $mediatype['parameters_webhook'];
		}

		if ($mediatype['type'] != MEDIA_TYPE_EMAIL)  {
			$mediatype['provider'] = CMediatypeHelper::EMAIL_PROVIDER_SMTP;
		}

		unset($mediatype['parameters_exec']);
		unset($mediatype['parameters_webhook']);
	}

	/**
	 * Removes invalid parameter: matches cases when field doesn't have any valid when rule.
	 *
	 * @param array $mediatype
	 */
	private function unsetInvalidParameters(&$mediatype): void {
		$keepfields = ['mediatypeid', 'type', 'name', 'maxsessions', 'maxattempts', 'attempt_interval',
			'description', 'status'
		];

		switch ($mediatype['type']) {
			case MEDIA_TYPE_EMAIL:
				$keepfields = array_merge($keepfields, ['smtp_port', 'smtp_helo', 'smtp_security',
					'smtp_authentication', 'message_format', 'provider', 'smtp_server', 'smtp_email',
					'smtp_verify_peer', 'smtp_verify_host'
				]);

				if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
					$keepfields = array_merge($keepfields, ['username', 'passwd']);
				}
				elseif ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_OAUTH) {
					$keepfields = array_merge($keepfields, ['redirection_url', 'client_id', 'client_secret',
						'authorization_url', 'token_url', 'tokens_status', 'access_token', 'access_token_updated',
						'access_expires_in', 'refresh_token'
					]);
				}

				break;

			case MEDIA_TYPE_EXEC:
				$keepfields += array_merge($keepfields, ['exec_path', 'exec_params']);
				break;

			case MEDIA_TYPE_SMS:
				$keepfields[] = 'gsm_modem';
				break;

			case MEDIA_TYPE_WEBHOOK:
				$keepfields += array_merge($keepfields, ['script', 'timeout', 'process_tags', 'show_event_menu',
					'event_menu_name', 'event_menu_url', 'parameters_webhook'
				]);
				break;
		}

		$mediatype = array_intersect_key($mediatype, array_flip($keepfields));
	}
}
