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


class CControllerPopupMediatypeTestSend extends CController {

	/**
	 * Mediatype object.
	 *
	 * @var array
	 */
	private $mediatype;

	protected function checkInput() {
		$fields = [
			'mediatypeid' =>	'fatal|required|db media_type.mediatypeid',
			'sendto' =>			'string|not_empty',
			'subject' =>		'string',
			'message' =>		'string',
			'parameters' =>		'array'
		];

		$ret = $this->validateInput($fields) && $this->validateMediaType();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Media type test failed.'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
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
		$mediatypes = API::MediaType()->get([
			'output' => ['type', 'status'],
			'mediatypeids' => $this->getInput('mediatypeid')
		]);

		if (!$mediatypes) {
			error(_('No permissions to referred object or it does not exist!'));

			return false;
		}

		$this->mediatype = $mediatypes[0];

		if ($this->mediatype['status'] != MEDIA_STATUS_ACTIVE) {
			error(_('Cannot test disabled media type.'));

			return false;
		}

		$ret = true;

		if ($this->mediatype['type'] != MEDIA_TYPE_EXEC && $this->mediatype['type'] != MEDIA_TYPE_WEBHOOK) {
			$validator = new CNewValidator(array_map('trim', $this->getInputAll()), [
				'message' =>	'string|not_empty'
			]);

			foreach ($validator->getAllErrors() as $error) {
				error($error);
			}

			$ret = !$validator->isError();

			if ($ret && $this->mediatype['type'] == MEDIA_TYPE_EMAIL) {
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

		if ($this->mediatype['type'] == MEDIA_TYPE_WEBHOOK ) {
			$params = [];

			foreach ($this->getInput('parameters', []) as $parameter) {
				$params[$parameter['name']] = $parameter['value'];
			}

			$params = ['parameters' => $params];
		}
		else {
			$params = [
				'sendto' =>	$this->getInput('sendto'),
				'subject' => $this->getInput('subject'),
				'message' => $this->getInput('message')
			];
		}

		$params['mediatypeid'] = $this->getInput('mediatypeid');
		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::MEDIA_TYPE_TEST_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);
		$result = $server->testMediaType($params, CSessionHelper::getId());
		$debug = $server->getDebug();

		if ($result) {
			info(_('Media type test successful.'));
		}
		else {
			error($server->getError());
		}

		$output = [
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($result) {
			$output['success']['messages'] = array_column(get_and_clear_messages(), 'message');
		}
		else {
			$output['error'] = [
				'title' => _('Media type test failed.'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		if ($this->mediatype['type'] == MEDIA_TYPE_WEBHOOK) {
			$value = json_decode($result);
			$output['response'] = [
				'type' => json_last_error() ? _('Response type: String') : _('Response type: JSON'),
				'value' => json_last_error() ? $result : json_encode($value, JSON_PRETTY_PRINT)
			];
		}

		if ($debug) {
			$output['debug'] = [
				'log' => [],
				'ms' => $debug['ms']
			];
			$debuglevel = [_('Info'), _('Critical'), _('Error'), _('Warning'), _('Debug'), _('Trace')];

			foreach ($debug['logs'] as $logitem) {
				$ms = (DateTime::createFromFormat('U.u', $logitem['ms'] ? $logitem['ms']/1000 : 0.0001));
				$level = array_key_exists($logitem['level'], $debuglevel)
					? $debuglevel[$logitem['level']] : _('Unknown');
				$output['debug']['log'][] = [
					'ms' => $ms->format('H:i:s.v'),
					'level' => '['.$level.']',
					'message' => $logitem['message']
				];
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
