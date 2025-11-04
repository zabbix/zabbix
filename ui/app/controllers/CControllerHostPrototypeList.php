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


class CControllerHostPrototypeList extends CController {

	private array $parent_discovery = [];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>			'required|in '.implode(',', ['host', 'template']),
			'parent_discoveryid' =>	'required|db items.itemid',
			'sort' =>				'in '.implode(',', ['name', 'status', 'discover']),
			'sortorder' =>			'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
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

		$this->parent_discovery = $parent_discovery[0];

		return true;
	}

	protected function doAction(): void {
		$data = [
			'parent_discoveryid' => $this->parent_discovery['itemid'],
			'discovery_rule' => $this->parent_discovery,
			'is_parent_discovered' => $this->parent_discovery['flags'] & ZBX_FLAG_DISCOVERY_CREATED,
			'action' => 'host.prototype.list',
			'context' => $this->getInput('context'),
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		$prefix = $data['context'] === 'host' ? 'web.hosts.' : 'web.templates.';
		$sort_field = $this->getInput('sort', CProfile::get($prefix.'host.prototype.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get($prefix.'host.prototype.list.sortorder', ZBX_SORT_UP));

		CProfile::update($prefix.'host.prototype.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update($prefix.'host.prototype.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data += [
			'sort' => $sort_field,
			'sortorder' => $sort_order
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['host_prototypes'] = API::HostPrototype()->get([
			'output' => ['hostid', 'host', 'name', 'status', 'templateid', 'discover', 'flags'],
			'selectTemplates' => ['templateid', 'name'],
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'selectDiscoveryData' => ['parent_hostid'],
			'discoveryids' => $data['parent_discoveryid'],
			'sortfield' => $sort_field,
			'limit' => $limit,
			'editable' => true
		]);

		if ($data['is_parent_discovered']) {
			$data['source_link_data'] = [
				'parent_itemid' => $this->parent_discovery['discoveryData']['parent_itemid'],
				'name' => $this->parent_discovery['name']
			];
		}

		CArrayHelper::sort($data['host_prototypes'], [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('host.prototype.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $data['host_prototypes'], $sort_order,
			(new CUrl('zabbix.php'))
				->setArgument('action', 'host.prototype.list')
				->setArgument('context', $data['context'])
				->setArgument('parent_discoveryid', $this->parent_discovery['itemid'])
		);

		// Fetch templates linked to the prototypes.
		$templateids = [];
		foreach ($data['host_prototypes'] as $host_prototype) {
			$templateids = array_merge($templateids, array_column($host_prototype['templates'], 'templateid'));
		}
		$templateids = array_keys(array_flip($templateids));

		$data['linked_templates'] = API::Template()->get([
			'output' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'templateids' => $templateids,
			'preservekeys' => true
		]);

		foreach ($data['linked_templates'] as $linked_template) {
			$templateids = array_merge($templateids, array_column($linked_template['parentTemplates'], 'templateid'));
		}
		$templateids = array_keys(array_flip($templateids));

		// Select writable template IDs.
		$data['writable_templates'] = [];

		if ($templateids) {
			$data['writable_templates'] = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => $templateids,
				'editable' => true,
				'preservekeys' => true
			]);
		}

		CTagHelper::mergeOwnAndInheritedTags($data['host_prototypes'], true);

		$data += [
			'tags' => CTagHelper::getTagsHtml($data['host_prototypes'], ZBX_TAG_OBJECT_HOST_PROTOTYPE),
			'parent_templates' => getHostPrototypeParentTemplates($data['host_prototypes'])
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of host prototypes'));
		$this->setResponse($response);
	}
}
