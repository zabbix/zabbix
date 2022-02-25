<?php declare(strict_types = 1);
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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupMassupdateTrigger extends CController {

	protected function checkInput() {
		$fields = [
			'ids' => 'required|array_id',
			'prototype' => 'in 1',
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
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->hasInput('prototype')) {
			$discoveryRule = API::DiscoveryRule()->get([
				'output' => [],
				'itemids' => [$this->getInput('parent_discoveryid', 0)],
				'editable' => true
			]);

			if (!$discoveryRule) {
				return false;
			}
		}
		else {
			$trigger = API::Trigger()->get([
				'output' => [],
				'triggerids' => $this->getInput('ids', []),
				'editable' => true
			]);

			if (!$trigger) {
				return false;
			}
		}

		return true;
	}

	protected function doAction() {
		if ($this->hasInput('update')) {
			$output = [];
			$triggerids = $this->getInput('ids', []);
			$visible = $this->getInput('visible', []);
			$tags = array_filter($this->getInput('tags', []),
				function (array $tag): bool {
					return ($tag['tag'] !== '' || $tag['value'] !== '');
				}
			);

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

			$result = true;

			$triggers_to_update = [];

			$options = [
				'output' => ['triggerid', 'templateid'],
				'triggerids' => $triggerids,
				'preservekeys' => true
			];

			if (!$this->hasInput('prototype')) {
				$options['filter'] = ['flags' => ZBX_FLAG_DISCOVERY_NORMAL];
			}

			if (array_key_exists('tags', $visible)) {
				$mass_update_tags = $this->getInput('mass_update_tags', ZBX_ACTION_ADD);

				if ($mass_update_tags == ZBX_ACTION_ADD || $mass_update_tags == ZBX_ACTION_REMOVE) {
					$options['selectTags'] = ['tag', 'value'];
				}

				$unique_tags = [];

				foreach ($tags as $tag) {
					$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
				}

				$tags = array_values($unique_tags);
			}

			if ($this->hasInput('prototype')) {
				$triggers = API::TriggerPrototype()->get($options);
			}
			else {
				$triggers = API::Trigger()->get($options);
			}


			if ($triggers) {
				foreach ($triggerids as $triggerid) {
					if (array_key_exists($triggerid, $triggers)) {
						$trigger = ['triggerid' => $triggerid];

						if (array_key_exists('priority', $visible)) {
							$trigger['priority'] = $this->getInput('priority');
						}

						if (array_key_exists('dependencies', $visible)) {
							$trigger['dependencies'] = zbx_toObject($this->getInput('dependencies', []), 'triggerid');
						}

						if (array_key_exists('tags', $visible)) {
							if ($tags && $mass_update_tags == ZBX_ACTION_ADD) {
								$unique_tags = [];

								foreach (array_merge($triggers[$triggerid]['tags'], $tags) as $tag) {
									$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
								}

								$trigger['tags'] = array_values($unique_tags);
							}
							elseif ($mass_update_tags == ZBX_ACTION_REPLACE) {
								$trigger['tags'] = $tags;
							}
							elseif ($tags && $mass_update_tags == ZBX_ACTION_REMOVE) {
								$diff_tags = [];

								foreach ($triggers[$triggerid]['tags'] as $a) {
									foreach ($tags as $b) {
										if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
											continue 2;
										}
									}

									$diff_tags[] = $a;
								}

								$trigger['tags'] = $diff_tags;
							}
						}

						if ($triggers[$triggerid]['templateid'] == 0 && array_key_exists('manual_close', $visible)) {
							$trigger['manual_close'] = $this->getInput('manual_close');
						}

						$triggers_to_update[] = $trigger;
					}
				}
			}

			if ($this->hasInput('prototype')) {
				$result = (bool) API::TriggerPrototype()->update($triggers_to_update);
			}
			else {
				$result = (bool) API::Trigger()->update($triggers_to_update);
			}

			if (!$result) {
				CMessageHelper::setErrorTitle(
					$this->hasInput('prototype') ? _('Cannot update trigger prototypes') : _('Cannot update trigger')
				);
			}

			if ($result) {
				$messages = CMessageHelper::getMessages();
				$output = ['title' => $this->hasInput('prototype')
					? _('Trigger prototypes updated')
					: _('Trigger updated')
				];
				if (count($messages)) {
					$output['messages'] = array_column($messages, 'message');
				}
			}
			else {
				$output['errors'] = makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
					->toString();
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
				'prototype' => $this->hasInput('prototype'),
				'ids' => $this->getInput('ids', []),
				'parent_discoveryid' => $this->getInput('parent_discoveryid', 0),
				'context' => $this->getInput('context'),
				'location_url' => $this->hasInput('prototype')
					? (new CUrl('trigger_prototypes.php'))
						->setArgument('parent_discoveryid', $this->getInput('parent_discoveryid', 0))
						->setArgument('context', $this->getInput('context'))
						->getUrl()
					: (new CUrl('triggers.php'))
						->setArgument('context', $this->getInput('context'))
						->getUrl()
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
