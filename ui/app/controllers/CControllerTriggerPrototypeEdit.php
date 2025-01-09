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


require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerTriggerPrototypeEdit extends CController {

	/**
	 * @var array
	 */
	private $trigger_prototype;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>				'in '.implode(',', ['host', 'template']),
			'hostid' =>					'db hosts.hostid',
			'triggerid' =>				'db triggers.triggerid',
			'name' =>					'string',
			'expression' =>				'string',
			'show_inherited_tags' =>	'in 0,1',
			'form_refresh' =>			'in 0,1',
			'parent_discoveryid' =>		'required|db items.itemid',
			'correlation_mode' =>		'db triggers.correlation_mode|in '.implode(',', [ZBX_TRIGGER_CORRELATION_NONE, ZBX_TRIGGER_CORRELATION_TAG]),
			'correlation_tag' =>		'db triggers.correlation_tag',
			'dependencies' =>			'array',
			'description' =>			'db triggers.comments',
			'event_name' =>				'db triggers.event_name',
			'manual_close' =>			'db triggers.manual_close|in '.implode(',',[ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED]),
			'opdata' =>					'db triggers.opdata',
			'priority' =>				'db triggers.priority|in 0,1,2,3,4,5',
			'recovery_expression' =>	'string',
			'recovery_mode' =>			'db triggers.recovery_mode|in '.implode(',', [ZBX_RECOVERY_MODE_EXPRESSION, ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION, ZBX_RECOVERY_MODE_NONE]),
			'status' =>					'db triggers.status|in '.implode(',', [TRIGGER_STATUS_ENABLED, TRIGGER_STATUS_DISABLED]),
			'tags' =>					'array',
			'discover' =>				'db triggers.discover|in '.implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]),
			'type' =>					'db triggers.type|in 0,1',
			'url' =>					'db triggers.url',
			'url_name' =>				'db triggers.url_name'
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
		$discovery_rule = API::DiscoveryRule()->get([
			'output' => ['name', 'itemid', 'hostid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		]);

		if (!$discovery_rule) {
			return false;
		}

		if ($this->hasInput('triggerid')) {
			$trigger_prototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'expression', 'description', 'url', 'status', 'priority', 'comments',
					'templateid', 'type', 'state', 'flags', 'recovery_mode', 'recovery_expression', 'correlation_mode',
					'correlation_tag', 'manual_close', 'opdata', 'event_name', 'url_name', 'discover'
				],
				'selectHosts' => ['hostid'],
				'triggerids' => $this->getInput('triggerid'),
				'selectItems' => ['itemid', 'templateid', 'flags'],
				'selectDependencies' => ['triggerid'],
				'selectTags' => ['tag', 'value']
			]);

			if (!$trigger_prototypes) {
				return false;
			}

			$this->trigger_prototype = reset($trigger_prototypes);
		}
		else {
			$this->trigger_prototype = null;
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'hostid' => 0,
			'dependencies' => [],
			'context' => '',
			'expression' => '',
			'recovery_expression' => '',
			'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
			'correlation_mode' => ZBX_TRIGGER_CORRELATION_NONE,
			'correlation_tag' => '',
			'description' => '',
			'opdata' => '',
			'priority' => '0',
			'recovery_mode' => ZBX_RECOVERY_MODE_EXPRESSION,
			'type' => '0',
			'event_name' => '',
			'db_dependencies' => [],
			'limited' => false,
			'tags' => [],
			'triggerid' => null,
			'show_inherited_tags' => 0,
			'form_refresh' => 0,
			'status' => $this->hasInput('form_refresh') ? TRIGGER_STATUS_DISABLED : TRIGGER_STATUS_ENABLED,
			'templates' => [],
			'parent_discoveryid' => 0,
			'discover' => $this->hasInput('form_refresh') ? ZBX_PROTOTYPE_NO_DISCOVER : ZBX_PROTOTYPE_DISCOVER,
			'url' => '',
			'url_name' => ''
		];

		$this->getInputs($data, array_keys($data));

		$data['description'] = $this->getInput('name', '');
		$data['comments'] = $this->getInput('description', '');
		$data['dependencies'] = zbx_toObject($this->getInput('dependencies', []), 'triggerid');

		if ($data['tags']) {
			// Unset empty and inherited tags.
			$tags = [];

			foreach ($data['tags'] as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}

				if (($data['show_inherited_tags'] == 0 || !$this->trigger_prototype)
					&& (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN))) {
					continue;
				}

				$tags[] = [
					'tag' => $tag['tag'],
					'value' => $tag['value']
				];
			}

			$data['tags'] = $tags;
		}

		if ($this->trigger_prototype) {
			$trigger = CTriggerGeneralHelper::getAdditionalTriggerData($this->trigger_prototype, $data);

			if ($data['form_refresh']) {
				if ($data['show_inherited_tags']) {
					$data['tags'] = $trigger['tags'];
				}

				$data = array_merge($data, [
					'templateid' => $trigger['templateid'],
					'limited' => $trigger['limited'],
					'flags' => $trigger['flags'],
					'templates' => $trigger['templates'],
					'hostid' => $trigger['hostid']
				]);
			}
			else {
				$data = $trigger;
			}
		}

		CTriggerGeneralHelper::getDependencies($data);

		if (!$data['tags']) {
			$data['tags'][] = ['tag' => '', 'value' => ''];
		}
		else {
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
			$data['tags'] = array_values($data['tags']);
		}

		$data['expr_temp'] = $data['expression'];
		$data['recovery_expr_temp'] = $data['recovery_expression'];
		$data['user'] = ['debug_mode' => $this->getDebugMode()];
		$data['db_trigger'] = $this->trigger_prototype
			? CTriggerGeneralHelper::convertApiInputForForm($this->trigger_prototype)
			: [];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
