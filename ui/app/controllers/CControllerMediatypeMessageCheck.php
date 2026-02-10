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


class CControllerMediatypeMessageCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'type' => ['integer', 'required', 'in' => array_keys(CMediatypeHelper::getMediaTypes())],
			'message_format' => ['integer', 'required',
				'in' => [ZBX_MEDIA_MESSAGE_FORMAT_TEXT, ZBX_MEDIA_MESSAGE_FORMAT_HTML]
			],
			'message_type' => ['integer', 'required', 'in' => array_keys(CMediatypeHelper::getAllMessageTypes())],
			'subject' => ['db media_type_message.subject'],
			'message' => ['db media_type_message.message']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update media type'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$data = [
			'type' => $this->getInput('type'),
			'message_format' => $this->getInput('message_format'),
			'message_type' => $this->getInput('message_type', -1),
			'subject' => $this->getInput('subject', ''),
			'message' => $this->getInput('message', '')
		];

		$from = CMediatypeHelper::transformFromMessageType($data['message_type']);
		$data['eventsource'] = $from['eventsource'];
		$data['recovery'] = $from['recovery'];
		$data['message_type_name'] = $from['name'];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]));
	}
}
