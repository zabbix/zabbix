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


/**
 * Controller class containing operations for adding and updating media type message templates.
 */
class CControllerPopupMediatypeMessage extends CController {

	/**
	 * @var array  An array with all message template types.
	 */
	protected $message_types = [];

	protected function init() {
		$this->disableSIDvalidation();

		$this->message_types = CMediatypeHelper::getAllMessageTypes();
	}

	protected function checkInput() {
		$fields = [
			'type' =>				'required|in '.implode(',', array_keys(media_type2str())),
			'content_type' =>		'required|in '.SMTP_MESSAGE_FORMAT_PLAIN_TEXT.','.SMTP_MESSAGE_FORMAT_HTML,
			'message_type' =>		'in -1,'.implode(',', $this->message_types),
			'old_message_type' =>	'in -1,'.implode(',', $this->message_types),
			'message_types' =>		'array',
			'subject' =>			'db media_type_message.subject',
			'message' =>			'db media_type_message.message'
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

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = [
			'type' => $this->getInput('type'),
			'content_type' => $this->getInput('content_type'),
			'message_type' => $this->getInput('message_type', -1),
			'old_message_type' => $this->getInput('old_message_type', -1),
			'message_types' => $this->getInput('message_types', []),
			'subject' => $this->getInput('subject', ''),
			'message' => $this->getInput('message', '')
		];

		if (!$this->hasInput('message_type')) {
			$diff = array_diff($this->message_types, $data['message_types']);
			$diff = reset($diff);
			$data['message_type'] = $diff ? $diff : CMediatypeHelper::MSG_TYPE_PROBLEM;
			$message_template = CMediatypeHelper::getMessageTemplate($data['type'], $data['message_type'],
				$data['content_type']
			);
			$data['subject'] = $message_template['subject'];
			$data['message'] = $message_template['message'];
		}
		else {
			$from = CMediatypeHelper::transformFromMessageType($data['message_type']);
			$data['eventsource'] = $from['eventsource'];
			$data['recovery'] = $from['recovery'];
		}

		$output = [
			'title' => _('Message template'),
			'params' => $data,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($output));
	}
}
