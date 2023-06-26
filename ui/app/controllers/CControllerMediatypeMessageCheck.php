<?php declare(strict_types = 0);
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


class CControllerMediatypeMessageCheck extends CController {

	/**
	 * @var array  An array with all message template types.
	 */
	protected $message_types = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();

		$this->message_types = CMediatypeHelper::getAllMessageTypes();
	}

	protected function checkInput(): bool {
		$fields = [
			'type' =>			'in '.implode(',', array_keys(CMediatypeHelper::getMediaTypes())),
			'content_type' =>	'in '.SMTP_MESSAGE_FORMAT_PLAIN_TEXT.','.SMTP_MESSAGE_FORMAT_HTML,
			'message_type' =>	'required|in '.implode(',', $this->message_types),
			'subject' =>		'db media_type_message.subject',
			'message' =>		'db media_type_message.message'
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
		return true;
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$data = [
			'type' => $this->getInput('type'),
			'content_type' => $this->getInput('content_type'),
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
