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


class CControllerMediatypeTestEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mediatypeid' => 'fatal|required|db media_type.mediatypeid'
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	protected function doAction(): void {
		$mediatype = API::MediaType()->get([
			'output' => ['type', 'name', 'status', 'parameters'],
			'mediatypeids' => $this->getInput('mediatypeid')
		]);

		if (!$mediatype) {
			error(_('No permissions to referred object or it does not exist!'));

			$output = [
				'error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]
			];

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);

			return;
		}

		if ($mediatype[0]['status'] != MEDIA_STATUS_ACTIVE) {
			error(_('Cannot test disabled media type.'));
		}

		CArrayHelper::sort($mediatype[0]['parameters'], ['name']);

		$this->setResponse(new CControllerResponseData([
			'title' => _s('Test media type "%1$s"', $mediatype[0]['name']),
			'errors' => hasErrorMessages() ? getMessages() : null,
			'mediatypeid' => $this->getInput('mediatypeid'),
			'sendto' => '',
			'subject' => _('Test subject'),
			'message' => _('This is the test message from Zabbix'),
			'parameters' => $mediatype[0]['parameters'],
			'type' => $mediatype[0]['type'],
			'enabled' => ($mediatype[0]['status'] == MEDIA_STATUS_ACTIVE),
			'user' => ['debug_mode' => $this->getDebugMode()]
		]));
	}
}
