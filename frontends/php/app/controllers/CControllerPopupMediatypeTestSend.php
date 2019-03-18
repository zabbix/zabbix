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


class CControllerPopupMediatypeTestSend extends CController {

	protected function checkInput() {
		$fields = [
			'mediatypeid' =>	'fatal|required|db media_type.mediatypeid',
			'sendto' =>		'string|not_empty',
			'subject' =>		'string',
			'message' =>		'string|not_empty'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['messages'] = $messages->toString();
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
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$mediatype = API::MediaType()->get([
			'output' => ['type'],
			'mediatypeids' => $this->getInput('mediatypeid'),
			'filter' => ['status' => MEDIA_STATUS_ACTIVE]
		]);
		$result = true;
		$msg_title = null;

		if ($mediatype) {
			$mediatype = reset($mediatype);

			if ($mediatype['type'] == MEDIA_TYPE_EMAIL) {
				$email_validator = new CEmailValidator();

				if (!$email_validator->validate($this->getInput('sendto'))) {
					$result = false;
					error($email_validator->getError());
				}
			}

			if ($result) {
				$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
				$result = $server->testMediaType([
						'mediatypeid' => $this->getInput('mediatypeid'),
						'sendto' =>	$this->getInput('sendto'),
						'subject' => $this->getInput('subject'),
						'message' => $this->getInput('message')
					],
					get_cookie('zbx_sessionid')
				);

				if ($result) {
					info(_('Media type test successful.'));
				}
				else {
					$msg_title = _('Media type test failed.');
					error($server->getError());
				}
			}
		}
		else {
			$result = false;
			error(_('No permissions to referred object or it does not exist!'));
		}

		$output = [
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if (($messages = getMessages($result, $msg_title)) !== null) {
			$output['messages'] = $messages->toString();
		}

		$this->setResponse((new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView());
	}
}
