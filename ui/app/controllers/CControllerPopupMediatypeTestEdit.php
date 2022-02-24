<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerPopupMediatypeTestEdit extends CController {

	protected function checkInput() {
		$fields = [
			'mediatypeid' => 'fatal|required|db media_type.mediatypeid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$mediatype = API::MediaType()->get([
			'output' => ['type', 'name', 'status', 'parameters'],
			'mediatypeids' => $this->getInput('mediatypeid')
		]);

		if (!$mediatype) {
			error(_('No permissions to referred object or it does not exist!'));

			$output = [];
			if (($messages = getMessages(false, null, false)) !== null) {
				$output['errors'] = $messages->toString();
			}

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
