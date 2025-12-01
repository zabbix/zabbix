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

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	protected static function processMediatypeData(array $mediatype): array {
		if ($mediatype['type'] == MEDIA_TYPE_EMAIL) {
			if ($mediatype['provider'] === CMediatypeHelper::EMAIL_PROVIDER_SMTP) {
				if ($mediatype['smtp_authentication'] == SMTP_AUTHENTICATION_PASSWORD) {
					$mediatype['username'] = $mediatype['smtp_username'];
					unset($mediatype['smtp_username']);
				}
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
		elseif ($mediatype['type'] == MEDIA_TYPE_EXEC) {
			$mediatype['parameters'] = [];

			foreach ($mediatype['parameters_exec'] as $sortorder => $parameter) {
				$mediatype['parameters'][] = ['sortorder' => $sortorder, 'value' => $parameter['value']];
			}
		}

		if ($mediatype['type'] != MEDIA_TYPE_EMAIL)  {
			$mediatype['provider'] = CMediatypeHelper::EMAIL_PROVIDER_SMTP;
		}

		unset($mediatype['parameters_exec']);
		unset($mediatype['parameters_webhook']);

		return $mediatype;
	}
}
