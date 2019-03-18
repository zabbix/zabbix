<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$mediatype = (bool) API::MediaType()->get([
			'output' => [],
			'mediatypeids' => $this->getInput('mediatypeid'),
			'filter' => ['status' => MEDIA_STATUS_ACTIVE]
		]);

		if (!$mediatype) {
			error(_('No permissions to referred object or it does not exist!'));
		}

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test media type'),
			'errors' => hasErrorMesssages() ? getMessages() : null,
			'mediatypeid' => $this->getInput('mediatypeid'),
			'subject' => _('Test subject'),
			'message' => _('This is the test message from Zabbix')
		]));
	}
}
