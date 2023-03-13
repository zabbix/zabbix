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


class CControllerMediatypeEnable extends CController {

	protected function checkInput() {
		$fields = [
			'mediatypeids' =>	'required|array_db media_type.mediatypeid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES)) {
			return false;
		}

		$mediatypes = API::Mediatype()->get([
			'mediatypeids' => $this->getInput('mediatypeids'),
			'countOutput' => true,
			'editable' => true
		]);

		return ($mediatypes == count($this->getInput('mediatypeids')));
	}

	protected function doAction() {
		$mediatypeids = $this->getInput('mediatypeids');

		$email_providers = API::MediaType()->get([
			'output' => ['name', 'passwd'],
			'mediatypeids' => $mediatypeids,
			'filter' => [
				'type' => MEDIA_TYPE_EMAIL,
				'provider' => [CMediatypeHelper::EMAIL_PROVIDER_GMAIL, CMediatypeHelper::EMAIL_PROVIDER_OFFICE365],
				'status' => MEDIA_STATUS_DISABLED
			],
			'preservekeys' => true
		]);

		$mediatypes = [];
		$incomplete_configurations = [];

		foreach ($mediatypeids as $mediatypeid) {
			if (array_key_exists($mediatypeid, $email_providers) && $email_providers[$mediatypeid]['passwd'] == '') {
				$incomplete_configurations[] = $email_providers[$mediatypeid]['name'];
				continue;
			}
			$mediatypes[] = [
				'mediatypeid' => $mediatypeid,
				'status' => MEDIA_TYPE_STATUS_ACTIVE
			];
		}

		$result = $mediatypes ? API::Mediatype()->update($mediatypes) : null;

		$updated = $result ? count($mediatypes) : count($mediatypeids);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'mediatype.list')
			->setArgument('page', CPagerHelper::loadPage('mediatype.list', null))
		);

		if ($result) {
			$response->setFormData(['uncheck' => '1']);

			if ($incomplete_configurations) {
				CMessageHelper::setSuccessTitle(_s('%1$s. %2$s: %3$s. %4$s.',
					_n('Media type enabled', 'Media types enabled', $updated),
					_('Not enabled'),
					implode(', ', $incomplete_configurations),
					_('Incomplete configuration')
				));
			}
			else {
				CMessageHelper::setSuccessTitle(_n('Media type enabled', 'Media types enabled', $updated));
			}
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot enable media type', 'Cannot enable media types', $updated));

			if ($incomplete_configurations) {
				info(_s('%1$s: %2$s', _('Incomplete configuration'), implode(',', $incomplete_configurations)));
			}
		}

		$this->setResponse($response);
	}
}

