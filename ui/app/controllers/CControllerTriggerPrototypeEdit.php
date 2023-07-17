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


require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerTriggerPrototypeEdit extends CController {

	/**
	 * @var array
	 */
	private $trigger_prototype;

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>							'in '.implode(',', ['host', 'template']),
			'hostid' =>								'db hosts.hostid',
			'triggerid' =>							'db triggers.triggerid',
			'show_inherited_tags' =>				'in 0,1',
			'form_refresh' =>						'in 0,1',
			'parent_discoveryid' =>					'required|db items.itemid'
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

		$this->discovery_rule = reset($discovery_rule);

		if ($this->hasInput('triggerid')) {
			$this->trigger_prototype = API::TriggerPrototype()->get([
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => ['hostid'],
				'triggerids' => $this->getInput('triggerid'),
				'selectItems' => ['itemid', 'templateid', 'flags'],
				'selectDependencies' => ['triggerid'],
				'selectTags' => ['tag', 'value']
			]);

			if (!$this->trigger_prototype) {
				return false;
			}
		}
		else {
			$this->trigger_prototype = null;
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'hostid' => $this->getInput('hostid', 0),
			'dependencies' => [],
			'context' => $this->getInput('context', ''),
			'expression' => $this->getInput('expression', ''),
			'recovery_expression' => '',
			'expression_full' => '',
			'recovery_expression_full' => '',
			'manual_close' => 1,
			'correlation_mode' => 0,
			'correlation_tag' => '',
			'description' => $this->getInput('description', ''),
			'opdata' => '',
			'priority' => '0',
			'recovery_mode' => 0,
			'type' => '0',
			'event_name' => '',
			'db_dependencies' => [],
			'limited' => false,
			'tags' => [],
			'recovery_expression_field_readonly' => false,
			'triggerid' => null,
			'show_inherited_tags' => $this->getInput('show_inherited_tags', 0),
			'form_refresh' => $this->getInput('form_refresh', 0),
			'templates' => [],
			'parent_discoveryid' => $this->getInput('parent_discoveryid'),
			'discover' => ZBX_PROTOTYPE_DISCOVER
		];

		if ($this->trigger_prototype) {
			$triggers = CMacrosResolverHelper::resolveTriggerExpressions($this->trigger_prototype,
				['sources' => ['expression', 'recovery_expression']]
			);

			$data = array_merge($data, reset($triggers));

			// Get templates.
			$data['templates'] = makeTriggerTemplatesHtml($data['triggerid'],
				getTriggerParentTemplates([$data], ZBX_FLAG_DISCOVERY_PROTOTYPE), ZBX_FLAG_DISCOVERY_PROTOTYPE,
				CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			);

			if ($data['show_inherited_tags']) {
				$items = [];
				$item_prototypes = [];

				foreach ($data['items'] as $item) {
					if ($item['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
						$items[] = $item;
					}
					else {
						$item_prototypes[] = $item;
					}
				}

				$item_parent_templates = getItemParentTemplates($items, ZBX_FLAG_DISCOVERY_NORMAL)['templates']
					+ getItemParentTemplates($item_prototypes, ZBX_FLAG_DISCOVERY_PROTOTYPE)['templates'];

				unset($item_parent_templates[0]);

				$db_templates = $item_parent_templates
					? API::Template()->get([
						'output' => ['templateid'],
						'selectTags' => ['tag', 'value'],
						'templateids' => array_keys($item_parent_templates),
						'preservekeys' => true
					])
					: [];

				$inherited_tags = [];

				foreach ($item_parent_templates as $templateid => $template) {
					if (array_key_exists($templateid, $db_templates)) {
						foreach ($db_templates[$templateid]['tags'] as $tag) {
							if (array_key_exists($tag['tag'], $inherited_tags)
								&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
								$inherited_tags[$tag['tag']][$tag['value']]['parent_templates'] += [
									$templateid => $template
								];
							}
							else {
								$inherited_tags[$tag['tag']][$tag['value']] = $tag + [
										'parent_templates' => [$templateid => $template],
										'type' => ZBX_PROPERTY_INHERITED
									];
							}
						}
					}
				}

				$db_hosts = API::Host()->get([
					'output' => [],
					'selectTags' => ['tag', 'value'],
					'hostids' => $data['hostid'],
					'templated_hosts' => true
				]);

				if ($db_hosts) {
					foreach ($db_hosts[0]['tags'] as $tag) {
						$inherited_tags[$tag['tag']][$tag['value']] = $tag;
						$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_INHERITED;
					}
				}

				foreach ($data['tags'] as $tag) {
					if (array_key_exists($tag['tag'], $inherited_tags)
						&& array_key_exists($tag['value'], $inherited_tags[$tag['tag']])) {
						$inherited_tags[$tag['tag']][$tag['value']]['type'] = ZBX_PROPERTY_BOTH;
					}
					else {
						$inherited_tags[$tag['tag']][$tag['value']] = $tag + ['type' => ZBX_PROPERTY_OWN];
					}
				}

				$data['tags'] = [];

				foreach ($inherited_tags as $tag) {
					foreach ($tag as $value) {
						$data['tags'][] = $value;
					}
				}
			}

			$dependency_triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' =>  array_column($data['dependencies'], 'triggerid'),
				'preservekeys' => true
			]);

			$dependency_trigger_prototypes = API::TriggerPrototype()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => array_column($data['dependencies'], 'triggerid'),
				'preservekeys' => true
			]);

			$data['db_dependencies'] = $dependency_triggers + $dependency_trigger_prototypes;

			foreach ($data['db_dependencies'] as &$dependency) {
				order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
			}
			unset($dependency);

			order_result($data['db_dependencies'], 'description');

			$data['limited'] = ($data['templateid'] != 0);
			$data['expression_full'] = $data['expression'];
			$data['recovery_expression_full'] = $data['recovery_expression'];

			if ($data['hostid'] == 0) {
				$data['hostid'] = $data['hosts'][0]['hostid'];
			}
		}

		if (!$data['tags']) {
			$data['tags'][] = ['tag' => '', 'value' => ''];
		}
		else {
			CArrayHelper::sort($data['tags'], ['tag', 'value']);
		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
