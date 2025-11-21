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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerHostPrototypeEdit extends CController {

	private array $parent_discovery = [];
	private array $host_prototype = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>				'required|in '.implode(',', ['host', 'template']),
			'parent_discoveryid' =>		'required|db items.itemid',
			'hostid' =>					'db hosts.hostid',
			'host' =>					'db hosts.host',
			'name' =>					'db hosts.name',
			'status' =>					'db hosts.status|in '.implode(',', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]),
			'discover' =>				'db hosts.discover|in '.implode(',', [ZBX_PROTOTYPE_DISCOVER, ZBX_PROTOTYPE_NO_DISCOVER]),
			'templates' =>				'array_db hosts.hostid',
			'add_templates' =>			'array_db hosts.hostid',
			'group_links' =>			'array',
			'group_prototypes' =>		'array',
			'show_inherited_tags' =>	'in 0,1',
			'tags' =>					'array',
			'show_inherited_macros' =>	'in 0,1',
			'macros' =>					'array',
			'custom_interfaces' =>		'in '.implode(',', [HOST_PROT_INTERFACES_INHERIT, HOST_PROT_INTERFACES_CUSTOM]),
			'interfaces' =>				'array',
			'inventory_mode' =>			'db host_inventory.inventory_mode| in '.implode(',', [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]),
			'clone' =>					'in 1'
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
		if (!($this->getInput('context') === 'host'
				? $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
				: $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES))) {
			return false;
		}

		$options = [
			'output' => ['itemid', 'name', 'hostid', 'flags'],
			'selectDiscoveryData' => ['parent_itemid'],
			'itemids' => $this->getInput('parent_discoveryid'),
			'editable' => true
		];

		$parent_discovery = API::DiscoveryRule()->get($options) ?: API::DiscoveryRulePrototype()->get($options);

		if (!$parent_discovery) {
			return false;
		}

		$this->parent_discovery = reset($parent_discovery);

		if ($this->hasInput('hostid')) {
			$host_prototypeid = $this->getInput('hostid');

			if ($host_prototypeid != 0) {
				$host_prototypes = API::HostPrototype()->get([
					'output' => ['hostid', 'host', 'name', 'status', 'discover', 'templateid', 'custom_interfaces',
						'flags', 'inventory_mode'
					],
					'selectDiscoveryData' => ['parent_hostid'],
					'selectTemplates' => ['templateid', 'name'],
					'selectGroupLinks' => ['groupid'],
					'selectGroupPrototypes' => ['group_prototypeid', 'name'],
					'selectMacros' => ['hostmacroid', 'macro', 'value', 'type', 'description'],
					'selectTags' => ['tag', 'value'],
					'selectInterfaces' => ['type', 'main', 'useip', 'ip', 'dns', 'port', 'details'],
					'hostids' => $host_prototypeid,
					'discoveryids' => $this->parent_discovery['itemid'],
					'editable' => true
				]);

				if (!$host_prototypes) {
					return false;
				}

				$this->host_prototype = $host_prototypes[0];
			}
		}

		return true;
	}

	protected function doAction(): void {
		$data = [
			'context' => $this->getInput('context'),
			'discovery_rule' => $this->parent_discovery,
			'clone' => $this->hasInput('clone') ? 1 : null,
			'show_inherited_tags' => (bool) $this->getInput('show_inherited_tags', 0),
			'show_inherited_macros' => (bool) $this->getInput('show_inherited_macros', 0),
			'warnings' => []
		];

		$interfaces = array_filter($this->getInput('interfaces', []), function ($interface) {
			unset($interface['main']);

			return $interface;
		});

		foreach ($interfaces as $index => &$interface) {
			$interface['main'] = $this->getInput('main_interface_'.$interface['type'], 0) == $index
				? INTERFACE_PRIMARY
				: INTERFACE_SECONDARY;
		}
		unset($interface);

		$parent_host = API::Host()->get([
			'output' => ['hostid', 'monitored_by', 'proxyid', 'proxy_groupid', 'status', 'ipmi_authtype',
				'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'tls_accept', 'tls_connect', 'tls_issuer',
				'tls_subject'
			],
			'selectInterfaces' => ['type', 'main', 'useip', 'ip', 'dns', 'port', 'details'],
			'hostids' => $this->parent_discovery['hostid'],
			'templated_hosts' => true
		]);
		$parent_host = $parent_host[0];
		$data['parent_host'] = $parent_host;

		if ($this->host_prototype) {
			$data += [
				'hostid' => $data['clone'] !== null ? 0 : $this->host_prototype['hostid'],
				'form_action' => $data['clone'] !== null ? 'host.prototype.create' : 'host.prototype.update',
				'host_prototype' => $this->host_prototype,
				'is_discovered_prototype' => $data['clone'] === null
					&& $this->host_prototype['flags'] & ZBX_FLAG_DISCOVERY_CREATED,
				'js_validation_rules' =>  $data['clone'] !== null
					? CControllerHostPrototypeCreate::getValidationRules()
					: CControllerHostPrototypeUpdate::getValidationRules()
			];

			$groupids = array_column($data['host_prototype']['groupLinks'], 'groupid');
			$data['groups'] = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $groupids,
				'preservekeys' => true
			]);

			$n = 0;
			foreach ($groupids as $groupid) {
				if (!array_key_exists($groupid, $data['groups'])) {
					$postfix = (++$n > 1) ? ' ('.$n.')' : '';
					$data['groups'][$groupid] = [
						'groupid' => $groupid,
						'name' => _('Inaccessible group').$postfix,
						'inaccessible' => true
					];
				}
			}
		}
		else {
			$data += [
				'form_action' => 'host.prototype.create',
				'hostid' => $this->getInput('hostid', 0),
				'host_prototype' => [
					'host' => $this->getInput('host', ''),
					'name' => $this->getInput('name', ''),
					'templateid' => 0,
					'groupLinks' => [],
					'templates' => [],
					'groupPrototypes' => [],
					'custom_interfaces' => $this->getInput('custom_interfaces',
						DB::getDefault('hosts', 'custom_interfaces')
					),
					'interfaces' => [],
					'tags' => [],
					'macros' => [],
					'status' => HOST_STATUS_MONITORED,
					'discover' => ZBX_PROTOTYPE_DISCOVER,
					'inventory_mode' => $this->getInput('inventory_mode',
						CSettingsHelper::get(CSettingsHelper::DEFAULT_INVENTORY_MODE)
					)
				],
				'groups' => [],
				'is_discovered_prototype' => false,
				'js_validation_rules' =>  CControllerHostPrototypeCreate::getValidationRules()
			];
		}

		if ($data['hostid'] == 0) {
			$data['host_prototype']['templateid'] = 0;
		}

		if (!$data['host_prototype']['tags']) {
			$data['host_prototype']['tags'][] = ['tag' => '', 'value' => ''];
		}
		else {
			CArrayHelper::sort($data['host_prototype']['tags'], ['tag', 'value']);
		}

		$data['host_prototype']['macros'] = array_values(order_macros($data['host_prototype']['macros'], 'macro'));

		$data['warnings'] = [];

		// Editable host groups.
		$groups_rw = ($data['groups'] && CWebUser::getType() != USER_TYPE_SUPER_ADMIN)
			? API::HostGroup()->get([
				'output' => [],
				'groupids' => array_keys($data['groups']),
				'editable' => true,
				'preservekeys' => true
			])
			: [];

		if ($this->hasInput('clone')) {
			$data['groups'] = [];
			$data['host_prototype']['host'] = $this->getInput('host', '');
			$data['host_prototype']['name'] = $this->getInput(
				$this->getInput('name', '') === '' ? 'host' : 'name', DB::getDefault('hosts', 'name')
			);

			$field_add_templates = $this->getInput('add_templates', []);
			$field_templates = $this->getInput('templates', []);

			$linked_templates = API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => array_merge($field_add_templates, $field_templates),
				'preservekeys' => true
			]);

			$data['host_prototype']['templates'] = array_intersect_key($linked_templates, array_flip($field_templates));
			CArrayHelper::sort($data['host_prototype']['templates'], ['name']);

			$data['host_prototype']['add_templates'] = array_map(function ($tmpl) {
				return CArrayHelper::renameKeys($tmpl, ['templateid' => 'id']);
			}, array_intersect_key($linked_templates, array_flip($field_add_templates)));

			if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN && $this->hasInput('group_links')) {
				$editable_groups_count = API::HostGroup()->get([
					'countOutput' => true,
					'groupids' => $this->getInput('group_links'),
					'editable' => true
				]);

				if ($editable_groups_count != count($this->getInput('group_links'))) {
					$data['warnings'][] = _("The host prototype being cloned belongs to a host group you don't have write permissions to. Non-writable group has been removed from the new host prototype.");
				}
			}

			$group_prototypes = $this->getInput('group_prototypes', []);

			foreach ($group_prototypes as &$group_prototype) {
				unset($group_prototype['group_prototypeid']);
			}
			unset($group_prototype);

			$data['host_prototype']['groupPrototypes'] = $group_prototypes;
			$data['host_prototype']['tags'] = $this->getInput('tags', []);
			$data['host_prototype']['status'] = $this->getInput('status', HOST_STATUS_NOT_MONITORED);
			$data['host_prototype']['inventory_mode'] = $this->getInput('inventory_mode', HOST_INVENTORY_DISABLED);
			$data['host_prototype']['discover'] = $this->getInput('discover', ZBX_PROTOTYPE_NO_DISCOVER);
			$data['host_prototype']['custom_interfaces'] = $this->getInput('custom_interfaces',
				DB::getDefault('hosts', 'custom_interfaces')
			);

			// Reset Secret text macros and set warning for cloned host.
			$secret_macro_reset = false;

			// Remove inherited macros data.
			$macros = cleanInheritedMacros($this->getInput('macros', []));

			// Remove empty new macro lines.
			$macros = array_filter($macros, static fn(array $macro): bool => (bool) array_filter(
				array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
			));

			$data['host_prototype']['macros'] = array_map(function ($macro) {
				unset($macro['hostmacroid']);

				return $macro + ['description' => ''];
			}, $macros);

			foreach ($data['host_prototype']['macros'] as &$macro) {
				if (array_key_exists('allow_revert', $macro) && array_key_exists('value', $macro)) {
					$macro['deny_revert'] = true;

					unset($macro['allow_revert']);
				}

				if ($macro['type'] == ZBX_MACRO_TYPE_SECRET && !array_key_exists('value', $macro)) {
					$macro = [
						'type' => ZBX_MACRO_TYPE_TEXT,
						'value' => ''
					] + $macro;

					unset($macro['allow_revert']);

					$secret_macro_reset = true;
				}
			}
			unset($macro);

			if ($secret_macro_reset) {
				$data['warnings'][] = _('The cloned host prototype contains user defined macros with type "Secret text". The value and type of these macros were reset.');
			}

			$interfaces = $this->getInput('interfaces', []);

			foreach ($interfaces as &$interface) {
				unset($interface['interfaceid'], $interface['items']);
			}
			unset($interface);

			$data['host_prototype']['interfaces'] = array_values($interfaces);
		}

		$group_links = $this->getInput('group_links', []);

		if ($group_links) {
			$data['groups'] = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $group_links,
				'editable' => true,
				'preservekeys' => true
			]);
		}

		$data['groups_ms'] = [];

		foreach ($data['groups'] as $group) {
			$data['groups_ms'][] = [
				'id' => $group['groupid'],
				'name' => $group['name'],
				'inaccessible' => array_key_exists('inaccessible', $group) && $group['inaccessible'],
				'disabled' => CWebUser::getType() != USER_TYPE_SUPER_ADMIN
					&& !array_key_exists($group['groupid'], $groups_rw)
			];
		}
		unset($data['groups']);

		$data['ms_proxy'] = [];
		$data['ms_proxy_group'] = [];

		if ($data['parent_host']['monitored_by'] == ZBX_MONITORED_BY_PROXY) {
			$data['ms_proxy'] = CArrayHelper::renameObjectsKeys(API::Proxy()->get([
				'output' => ['proxyid', 'name'],
				'proxyids' => $data['parent_host']['proxyid']
			]), ['proxyid' => 'id']);
		}
		elseif ($data['parent_host']['monitored_by'] == ZBX_MONITORED_BY_PROXY_GROUP) {
			$data['ms_proxy_group'] = CArrayHelper::renameObjectsKeys(API::ProxyGroup()->get([
				'output' => ['proxy_groupid', 'name'],
				'proxy_groupids' => $data['parent_host']['proxy_groupid']
			]), ['proxy_groupid' => 'id']);
		}

		self::extendLinkedTemplates($data);

		$data += [
			'readonly' => $data['host_prototype']['templateid'] != 0 || $data['is_discovered_prototype'],
			'user' => [
				'debug_mode' => $this->getDebugMode(),
				'can_edit_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
			]
		];

		if ($data['show_inherited_macros']) {
			$data['host_prototype']['macros'] = mergeInheritedMacros($data['host_prototype']['macros'],
				getInheritedMacros(array_keys($linked_templates), $data['parent_host']['hostid'])
			);
		}
		$data['host_prototype']['macros'] = array_values(order_macros($data['host_prototype']['macros'], 'macro'));

		if (!$data['readonly'] && !$data['host_prototype']['macros']) {
			$macro = [
				'type' => ZBX_MACRO_TYPE_TEXT,
				'macro' => '',
				'value' => '',
				'description' => ''
			];

			if ($data['show_inherited_macros']) {
				$macro['inherited_type'] = ZBX_PROPERTY_OWN;
			}

			$data['host_prototype']['macros'][] = $macro;
		}

		foreach ($data['host_prototype']['macros'] as &$macro) {
			$macro['discovery_state'] = CControllerHostMacrosList::DISCOVERY_STATE_MANUAL;

			if ($macro['type'] == ZBX_MACRO_TYPE_SECRET
					&& !array_key_exists('deny_revert', $macro) && !array_key_exists('value', $macro)) {
				$macro['allow_revert'] = true;
			}
		}
		unset($macro);

		// Parent discovery rules.
		$data['templates'] = makeHostPrototypeTemplatesHtml($data['hostid'],
			getHostPrototypeParentTemplates([$data['host_prototype']]), $data['user']['can_edit_templates']
		);

		$data['js_validation_rules'] = (new CFormValidator($data['js_validation_rules']))->getRules();

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	private static function extendLinkedTemplates(array &$data): void {
		$data['editable_templates'] = $data['host_prototype']['templates']
			? API::Template()->get([
				'output' => ['templateid'],
				'templateids' => array_column($data['host_prototype']['templates'], 'templateid'),
				'editable' => true,
				'preservekeys' => true
			])
			: [];
	}
}
