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


/**
 * Controller class containing operations for adding and updating media type message templates.
 */
class CControllerPopupMediatypeMessage extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'update' =>			'in 0,1',
			'index' =>			'string',
			'type' =>			'in '.implode(',', array_keys(media_type2str())),
			'content_type' =>	'in '.SMTP_MESSAGE_FORMAT_PLAIN_TEXT.','.SMTP_MESSAGE_FORMAT_HTML,
			'message_type' =>	'in '.implode(',', CMediatypeHelper::getAllMessageTypes()),
			'eventsource' =>	'db media_type_message.eventsource|in '.implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL]),
			'recovery' =>		'db media_type_message.recovery|in '.implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_ACKNOWLEDGE_OPERATION]),
			'subject' =>		'db media_type_message.subject',
			'message' =>		'db media_type_message.message'
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
		return true;
	}

	protected function doAction() {
		$data = [
			'type' => $this->getInput('type'),
			'content_type' => $this->getInput('content_type'),
			'index' => $this->getInput('index')
		];

		if ($this->hasInput('eventsource') && $this->hasInput('recovery')) {
			$data['eventsource'] = $this->getInput('eventsource');
			$data['recovery'] = $this->getInput('recovery');
			$data['message_type'] = CMediatypeHelper::transformToMessageType($data['eventsource'], $data['recovery']);
		}
		else {
			$data['message_type'] = $this->getInput('message_type', CMediatypeHelper::MSG_TYPE_PROBLEM);
			$from = CMediatypeHelper::transformFromMessageType($data['message_type']);
			$data['eventsource'] = $from['eventsource'];
			$data['recovery'] = $from['recovery'];
		}

		$message_template = CMediatypeHelper::getMessageTemplate($data['type'], $data['message_type'],
			$data['content_type']
		);
		$data['subject'] = $this->getInput('subject', $message_template['subject']);
		$data['message'] = $this->getInput('message', $message_template['message']);

		$output = [
			'title' => _('Message template'),
			'params' => $data,
			'update' => $this->getInput('update', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($output));
	}
}
