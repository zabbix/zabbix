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


class CControllerTriggerPrototypeCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>					'required|db triggers.description|not_empty',
			'event_name' =>				'db triggers.event_name',
			'opdata' =>					'db triggers.opdata',
			'priority' =>				'db triggers.priority|in 0,1,2,3,4,5',
			'expression' =>				'required|string|not_empty',
			'recovery_mode' =>			'db triggers.recovery_mode|in '.implode(',', [ZBX_RECOVERY_MODE_EXPRESSION, ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, ZBX_RECOVERY_MODE_NONE]),
			'recovery_expression' =>	'string',
			'type' =>					'db triggers.type|in 0,1',
			'correlation_mode' =>		'db triggers.correlation_mode|in '.implode(',', [ZBX_TRIGGER_CORRELATION_NONE, ZBX_TRIGGER_CORRELATION_TAG]),
			'correlation_tag' =>		'db triggers.correlation_tag',
			'manual_close' =>			'db triggers.manual_close|in '.implode(',',[ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED]),
			'url_name' =>				'db triggers.url_name',
			'url' =>					'db triggers.url',
			'description' =>			'db triggers.comments',
			'status' =>					'db triggers.status|in '.implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED]),
			'tags' =>					'array',
			'dependencies' =>			'array',
			'hostid' =>					'db hosts.hostid',
			'discover' =>				'db triggers.discover|in '.implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]),
			'parent_discoveryid'=>		'required|db triggers.triggerid',
			'context' =>				'in '.implode(',', ['host', 'template'])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add trigger prototype'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getInput('context') === 'host'
			? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
			: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		$tags = $this->getInput('tags', []);

		// Unset empty and inherited tags.
		foreach ($tags as $key => $tag) {
			if ($tag['tag'] === '' && $tag['value'] === '') {
				unset($tags[$key]);
			}
			elseif (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
				unset($tags[$key]);
			}
			else {
				unset($tags[$key]['type']);
			}
		}

		$trigger_prototype = [
			'description' => $this->getInput('name'),
			'event_name' => $this->getInput('event_name', ''),
			'opdata' => $this->getInput('opdata', ''),
			'expression' => $this->getInput('expression'),
			'recovery_mode' => $this->getInput('recovery_mode', ZBX_RECOVERY_MODE_EXPRESSION),
			'type' => $this->getInput('type', 0),
			'url_name' => $this->getInput('url_name', ''),
			'url' => $this->getInput('url', ''),
			'priority' => $this->getInput('priority', TRIGGER_SEVERITY_NOT_CLASSIFIED),
			'comments' => $this->getInput('description', ''),
			'tags' => $tags,
			'manual_close' => $this->getInput('manual_close', ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED),
			'dependencies' => zbx_toObject($this->getInput('dependencies', []), 'triggerid'),
			'status' => $this->getInput('status', TRIGGER_STATUS_DISABLED),
			'discover' => $this->getInput('discover', ZBX_PROTOTYPE_NO_DISCOVER)
		];

		switch ($trigger_prototype['recovery_mode']) {
			case ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION:
				$trigger_prototype['recovery_expression'] = $this->getInput('recovery_expression', '');
			// break; is not missing here.

			case ZBX_RECOVERY_MODE_EXPRESSION:
				$trigger_prototype['correlation_mode'] = $this->getInput('correlation_mode', ZBX_TRIGGER_CORRELATION_NONE);

				if ($trigger_prototype['correlation_mode'] == ZBX_TRIGGER_CORRELATION_TAG) {
					$trigger_prototype['correlation_tag'] = $this->getInput('correlation_tag', '');
				}
				break;
		}

		$result = (bool) API::TriggerPrototype()->create($trigger_prototype);

		if ($result) {
			$output['success']['title'] = _('Trigger prototype added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add trigger prototype'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
