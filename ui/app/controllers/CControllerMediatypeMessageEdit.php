<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


/**
 * Controller class containing operations for adding and updating media type message templates.
 */
class CControllerMediatypeMessageEdit extends CController {

	/**
	 * @var array  An array with all message template types.
	 */
	protected $message_types = [];

	protected function init(): void {
		$this->disableCsrfValidation();

		$this->message_types = CMediatypeHelper::getAllMessageTypes();
	}

	protected function checkInput(): bool {
		$fields = [
			'type' =>				'in '.implode(',', array_keys(CMediatypeHelper::getMediaTypes())),
			'message_format' =>		'in '.ZBX_MEDIA_MESSAGE_FORMAT_TEXT.','.ZBX_MEDIA_MESSAGE_FORMAT_HTML,
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

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
	}

	protected function doAction(): void {
		$data = [
			'type' => $this->getInput('type'),
			'message_format' => $this->getInput('message_format'),
			'message_type' => $this->getInput('message_type', -1),
			'old_message_type' => $this->getInput('old_message_type', -1),
			'message_types' => $this->getInput('message_types', []),
			'subject' => $this->getInput('subject', ''),
			'message' => $this->getInput('message', '')
		];

		if ($this->hasInput('message_type')) {
			$from = CMediatypeHelper::transformFromMessageType($data['message_type']);
			$data['eventsource'] = $from['eventsource'];
			$data['recovery'] = $from['recovery'];
		}
		else {
			$diff = array_diff($this->message_types, $data['message_types']);
			$diff = reset($diff);
			$data['message_type'] = $diff ?: CMediatypeHelper::MSG_TYPE_PROBLEM;
			$message_template = CMediatypeHelper::getMessageTemplate($data['type'], $data['message_type'],
				$data['message_format']
			);
			$data['subject'] = $message_template['subject'];
			$data['message'] = $message_template['message'];
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
