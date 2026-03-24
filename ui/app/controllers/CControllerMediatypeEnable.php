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


class CControllerMediatypeEnable extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeids' => 'required|array_db media_type.mediatypeid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	protected function doAction(): void {
		$mediatypeids = $this->getInput('mediatypeids');

		$email_providers = API::MediaType()->get([
			'output' => ['name', 'passwd'],
			'mediatypeids' => $mediatypeids,
			'filter' => [
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => [CMediatypeHelper::EMAIL_PROVIDER_GMAIL, CMediatypeHelper::EMAIL_PROVIDER_OFFICE365],
				'smtp_authentication' => SMTP_AUTHENTICATION_PASSWORD,
				'status' => MEDIA_TYPE_STATUS_DISABLED
			],
			'preservekeys' => true
		]);

		$push_mediatypes = API::MediaType()->get([
			'output' => ['name'],
			'mediatypeids' => $mediatypeids,
			'filter' => [
				'type' => MEDIA_TYPE_PUSH,
				'status' => MEDIA_TYPE_STATUS_DISABLED
			],
			'preservekeys' => true
		]);

		$mediatypes = [];
		$incomplete_configurations = [];
		$bridge_disabled_configurations = [];

		foreach ($mediatypeids as $mediatypeid) {
			if (array_key_exists($mediatypeid, $email_providers) && $email_providers[$mediatypeid]['passwd'] === '') {
				$incomplete_configurations[] = $email_providers[$mediatypeid]['name'];
				continue;
			}

			if (array_key_exists($mediatypeid, $push_mediatypes) && !CTemporaryMobileFeatureHelper::isConfigured()) {
				$bridge_disabled_configurations[] = $push_mediatypes[$mediatypeid]['name'];
				continue;
			}

			$mediatypes[] = [
				'mediatypeid' => $mediatypeid,
				'status' => MEDIA_TYPE_STATUS_ACTIVE
			];
		}

		$result = $mediatypes ? API::Mediatype()->update($mediatypes) : null;
		$updated = $result ? count($mediatypes) : count($mediatypeids);
		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _n('Media type enabled', 'Media types enabled', $updated),
				'messages' => []
			];

			if ($incomplete_configurations) {
				$output['success']['messages'][] = _s(
					'%1$s: %2$s', _('Incomplete configuration'), implode(', ', $incomplete_configurations)
				);
			}

			if ($bridge_disabled_configurations) {
				$output['success']['messages'][] = _s(
					'%1$s: %2$s', _('Zabbix bridge must be configured to enable push notifications'),
					implode(', ', $bridge_disabled_configurations)
				);
			}
		}
		else {
			$output['error'] = [
				'title' => _n('Cannot enable media type', 'Cannot enable media types', $updated),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];

			if ($incomplete_configurations) {
				$output['error']['messages'][] = _s(
					'%1$s: %2$s', _('Incomplete configuration'), implode(', ', $incomplete_configurations)
				);
			}

			if ($bridge_disabled_configurations) {
				$output['error']['messages'][] = _s(
					'%1$s: %2$s', _('Zabbix bridge must be configured to enable push notifications'),
					implode(', ', $bridge_disabled_configurations)
				);
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
