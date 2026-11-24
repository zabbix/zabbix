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


require_once __DIR__.'/../../include/forms.inc.php';

class CControllerTriggerMassupdate extends CController {

	private array $triggers = [];

	protected function checkInput(): bool {
		$fields = [
			'ids' => 'required|array_id',
			'prototype' => 'required|in 0,1',
			'update' => 'in 1',
			'visible' => 'array',
			'dependencies' => 'array_id',
			'tags' => 'array',
			'mass_update_tags' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'manual_close' => 'in '.implode(',', [ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED, ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED]),
			'parent_discoveryid' => 'id',
			'priority' => 'in '.implode(',', [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER]),
			'context' => 'required|string|in '.implode(',', ['host', 'template'])
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
		$ids = array_keys(array_flip($this->getInput('ids')));

		if (!$ids) {
			return false;
		}

		$parent_lld = [];

		if ($this->getInput('prototype') == 1) {
			$options = [
				'output' => [],
				'itemids' => [$this->getInput('parent_discoveryid', 0)],
				'nopermissions' => true
			];

			$parent_lld = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

			if (!$parent_lld) {
				return false;
			}
		}

		$options = [
			'output' => ['triggerid', 'templateid'],
			'triggerids' => $ids,
			'selectHosts' => ['status'],
			'editable' => true,
			'preservekeys' => true
		];

		if (array_key_exists('tags', $this->getInput('visible', []))) {
			$options['selectTags'] = ['tag', 'value'];
		}

		if ($parent_lld) {
			$options['discoveryids'] = $this->getInput('parent_discoveryid');
		}

		$this->triggers = $parent_lld ? API::TriggerPrototype()->get($options) : API::Trigger()->get($options);

		if (count($this->triggers) != count($ids)) {
			return false;
		}

		$has_template_access = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
		$has_host_access = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS);

		foreach ($this->triggers as $trigger) {
			if (!array_key_exists('hosts', $trigger) || !$trigger['hosts']) {
				return false;
			}

			foreach ($trigger['hosts'] as $host) {
				$is_template = $host['status'] == HOST_STATUS_TEMPLATE;

				if (($is_template && !$has_template_access) || (!$is_template && !$has_host_access)) {
					return false;
				}
			}
		}

		return $has_template_access || $has_host_access;
	}

	protected function doAction(): void {
		if ($this->hasInput('update')) {
			$visible = $this->getInput('visible', []);
			$tags = $this->getInput('tags', []);

			foreach ($tags as $key => $tag) {
				// Remove empty new tag lines.
				if ($tag['tag'] === '' && $tag['value'] === '') {
					unset($tags[$key]);
					continue;
				}

				// Remove inherited tags.
				if (array_key_exists('type', $tag) && !($tag['type'] & ZBX_PROPERTY_OWN)) {
					unset($tags[$key]);
				}
				else {
					unset($tags[$key]['type']);
				}
			}

			if (array_key_exists('tags', $visible)) {
				$tags = self::getUniqueTags($tags);
			}

			$triggers_to_update = [];

			foreach ($this->triggers as $triggerid => $trigger) {
				$upd_trigger = ['triggerid' => $triggerid];

				if (array_key_exists('priority', $visible)) {
					$upd_trigger['priority'] = $this->getInput('priority');
				}

				if (array_key_exists('dependencies', $visible)) {
					$upd_trigger['dependencies'] = zbx_toObject($this->getInput('dependencies', []), 'triggerid');
				}

				if (array_key_exists('tags', $visible)) {
					$tags_action = $this->getInput('mass_update_tags', ZBX_ACTION_ADD);

					if ($tags && $tags_action == ZBX_ACTION_ADD) {
						$upd_trigger['tags'] = self::getUniqueTags(array_merge($trigger['tags'], $tags));
					}
					elseif ($tags_action == ZBX_ACTION_REPLACE) {
						$upd_trigger['tags'] = $tags;
					}
					elseif ($tags && $tags_action == ZBX_ACTION_REMOVE) {
						$upd_trigger['tags'] =
							array_filter($trigger['tags'], static function (array $tag) use ($tags): bool {
								foreach ($tags as $_tag) {
									if ($tag['tag'] === $_tag['tag'] && $tag['value'] === $_tag['value']) {
										return false;
									}
								}

								return true;
							});
					}
				}

				if ($trigger['templateid'] == 0 && array_key_exists('manual_close', $visible)) {
					$upd_trigger['manual_close'] = $this->getInput('manual_close');
				}

				$triggers_to_update[] = $upd_trigger;
			}

			$result = $this->getInput('prototype') == 1
				? API::TriggerPrototype()->update($triggers_to_update)
				: API::Trigger()->update($triggers_to_update);

			$triggers_count = count($this->triggers);

			if ($result) {
				$messages = CMessageHelper::getMessages();
				$output = ['title' => $this->getInput('prototype') == 1
					? _n('Trigger prototype updated', 'Trigger prototypes updated', $triggers_count)
					: _n('Trigger updated', 'Triggers updated', $triggers_count)
				];

				if (count($messages)) {
					$output['messages'] = array_column($messages, 'message');
				}
			}
			else {
				CMessageHelper::setErrorTitle(
					$this->getInput('prototype') == 1
						? _n('Cannot update trigger prototype', 'Cannot update trigger prototypes', $triggers_count)
						: _n('Cannot update trigger', 'Cannot update triggers', $triggers_count)
				);

				$output = [
					'error' => [
						'title' => CMessageHelper::getTitle(),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$data = [
				'title' => _('Mass update'),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				],
				'prototype' => $this->getInput('prototype'),
				'ids' => $this->getInput('ids', []),
				'parent_discoveryid' => $this->getInput('parent_discoveryid', 0),
				'context' => $this->getInput('context'),
				'location_url' => $this->getInput('prototype') == 1
					? (new CUrl('zabbix.php'))
						->setArgument('action', 'trigger.prototype.list')
						->setArgument('parent_discoveryid', $this->getInput('parent_discoveryid', 0))
						->setArgument('context', $this->getInput('context'))
						->getUrl()
					: (new CUrl('zabbix.php'))
						->setArgument('action', 'trigger.list')
						->setArgument('context', $this->getInput('context'))
						->getUrl()
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}

	private static function getUniqueTags(array $tags): array {
		$unique_tags = [];

		foreach ($tags as $tag) {
			$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
		}

		return array_values($unique_tags);
	}
}
