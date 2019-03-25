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
			'sendto' =>			'string|not_empty',
			'subject' =>		'string',
			'message' =>		'string'
		];

		$ret = $this->validateInput($fields) && $this->validateMediaType();

		if (!$ret) {
			$output = [];

			if (($messages = getMessages(false, _('Media type test failed.'))) !== null) {
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

	/**
	 * Additional method to validate fields specific for mediatype.
	 *
	 * @return bool
	 */
	protected function validateMediaType() {
		$mediatype = API::MediaType()->get([
			'output' => ['type', 'status'],
			'mediatypeids' => $this->getInput('mediatypeid'),
		]);

		if (!$mediatype) {
			error(_('No permissions to referred object or it does not exist!'));

			return false;
		}

		if ($mediatype[0]['status'] != MEDIA_STATUS_ACTIVE) {
			error(_('Cannot test disabled media type.'));

			return false;
		}

		$ret = true;

		if ($mediatype[0]['type'] != MEDIA_TYPE_EXEC) {
			$validator = new CNewValidator(array_map('trim', $this->getInputAll()), [
				'message' =>	'string|not_empty'
			]);

			foreach ($validator->getAllErrors() as $error) {
				error($error);
			}

			$ret = !$validator->isError();

			if ($ret && $mediatype[0]['type'] == MEDIA_TYPE_EMAIL) {
				$email_validator = new CEmailValidator();
				$ret = $email_validator->validate($this->getInput('sendto'));

				if (!$ret) {
					error($email_validator->getError());
				}
			}
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
		$result = $server->testMediaType([
				'mediatypeid' => $this->getInput('mediatypeid'),
				'sendto' =>	$this->getInput('sendto'),
				'subject' => $this->getInput('subject'),
				'message' => $this->getInput('message')
			],
			CWebUser::getSessionCookie()
		);

		if ($result) {
			$msg_title = null;
			info(_('Media type test successful.'));
		}
		else {
			$msg_title = _('Media type test failed.');
			error($server->getError());
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
