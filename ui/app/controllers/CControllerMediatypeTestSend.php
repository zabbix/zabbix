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


class CControllerMediatypeTestSend extends CController {

	/**
	 * Mediatype object.
	 *
	 * @var array
	 */
	private $mediatype;

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'mediatypeid' => ['db media_type.mediatypeid', 'required'],
			'type' => ['db media_type.type', 'required',
				'in' => [MEDIA_TYPE_EMAIL, MEDIA_TYPE_EXEC, MEDIA_TYPE_SMS, MEDIA_TYPE_WEBHOOK]],
			'sendto' => [
				['string','required', 'not_empty',
					'when' => ['type', 'in' => [MEDIA_TYPE_SMS]]
				],
				['string','required', 'not_empty', 'use' => [CEmailValidator::class],
					'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL]]
				]
			],
			'subject' => ['string', 'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL, MEDIA_TYPE_SMS]]],
			'message' => ['string', 'required', 'not_empty',
				'when' => ['type', 'in' => [MEDIA_TYPE_EMAIL, MEDIA_TYPE_SMS]]
			],
			'parameters' => ['objects', 'fields' => [
				'name' => ['db media_type_param.name', 'required', 'not_empty',
					'when' => ['../type', 'in' => [MEDIA_TYPE_WEBHOOK]]],
				'value' => ['db media_type_param.value', 'required']
			], 'when' => ['../type', 'in' => [MEDIA_TYPE_EXEC, MEDIA_TYPE_WEBHOOK]]]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules()) && $this->validateMediaType();

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	/**
	 * Additional method to validate fields specific for mediatype.
	 *
	 * @return bool
	 */
	protected function validateMediaType(): bool {
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

		return true;
	}

	protected function doAction(): void {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		switch ($this->mediatype['type']) {
			case MEDIA_TYPE_EXEC:
				$parameters = [];

				foreach ($this->getInput('parameters', []) as $parameter) {
					$parameters[] = $parameter['value'];
				}

				$params = ['parameters' => $parameters];
				break;

			case MEDIA_TYPE_WEBHOOK:
				$parameters = [];

				foreach ($this->getInput('parameters', []) as $parameter) {
					$parameters[$parameter['name']] = $parameter['value'];
				}

				$params = ['parameters' => $parameters];
				break;

			default:
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
