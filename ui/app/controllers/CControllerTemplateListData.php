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


class CControllerTemplateListData extends CControllerDataTable {

	protected array $allowed_data_fields = ['templateid', 'data_actions', 'name', 'hosts', 'items', 'triggers',
		'graphs', 'dashboards', 'discoveryRules', 'httpTests', 'vendor_name', 'vendor_version', 'parentTemplates',
		'templates', 'tags', 'custom_text'];

	protected function init(): void {
		parent::init();

		$this->addValidationRules(['sort_field' => 'string|in name']);
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function getData(): array {
		$data_fields = $this->getDataFields();
		$options = $this->getInput('options', []);
		$filter = $this->getInput('filter', []);
		$page = $this->getInput('page', 1);

		$sort_field = $this->getInput('sort_field', CControllerTemplateList::DEFAULT_SORT);
		$sort_order = $this->getInput('sort_order', CControllerTemplateList::DEFAULT_SORTORDER);

		if ($filter['tags']) {
			$filter['tags'] = array_filter($filter['tags'], static fn(array $tag) => $tag && $tag['tag'] != '');
		}

		CProfile::update('web.templates.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.templates.sortorder', $sort_order, PROFILE_TYPE_STR);

		$filter['templates'] = $filter['templates']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => array_column($filter['templates'], 'id'),
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];

		// Get template groups.
		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(API::TemplateGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => array_column($filter['groups'], 'id'),
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;

		if ($filter_groupids) {
			$filter_groupids = getTemplateSubGroups($filter_groupids);
		}

		// Select templates.
		$limit = (int) CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$templates = API::Template()->get([
			'output' => ['templateid', $sort_field],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'] ?: null,
			'inheritedTags' => true,
			'search' => array_filter([
				'name' => $filter['name'],
				'vendor_name' => $filter['vendor_name'],
				'vendor_version' => $filter['vendor_version']
			], 'strlen'),
			'parentTemplateids' => $filter['templates'] ? array_keys($filter['templates']) : null,
			'groupids' => $filter_groupids,
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit
		]);

		order_result($templates, $sort_field, $sort_order);

		$this->paging = $this->paginate($templates, $page, $sort_order);

		$templates = API::Template()->get([
			'output' => $data_fields,
			'selectHosts' => ['hostid'],
			'selectTemplates' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectDiscoveryRules' => API_OUTPUT_COUNT,
			'selectDashboards' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
			'selectInheritedTags' => ['tag', 'value'],
			'templateids' => array_column($templates, 'templateid'),
			'editable' => true,
			'preservekeys' => true
		]);

		order_result($templates, $sort_field, $sort_order);

		// Select editable templates:
		$linked_templateids = [];
		$editable_templates = [];
		$linked_hostids = [];
		$editable_hosts = [];

		foreach ($templates as &$template) {
			order_result($template['templates'], 'name');
			order_result($template['parentTemplates'], 'name');

			$linked_templateids += array_flip(array_column($template['parentTemplates'], 'templateid'));
			$linked_templateids += array_flip(array_column($template['templates'], 'templateid'));

			$template['hosts'] = array_flip(array_column($template['hosts'], 'hostid'));
			$linked_hostids += $template['hosts'];
		}
		unset($template);

		if ($linked_templateids) {
			$editable_templates = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => array_keys($linked_templateids),
				'editable' => true,
				'preservekeys' => true
			]);
		}
		if ($linked_hostids) {
			$editable_hosts = API::Host()->get([
				'output' => ['hostid'],
				'hostids' => array_keys($linked_hostids),
				'editable' => true,
				'preservekeys' => true
			]);
		}

		$editable_templateids = array_column($editable_templates, 'templateid');

		order_result($templates, $sort_field, $sort_order);

		CTagHelper::mergeOwnAndInheritedTags($templates, true);

		foreach ($templates as &$template) {
			$template['editable_hosts'] = array_intersect_key($template['hosts'], $editable_hosts);

			foreach ($template['templates'] as &$child_template) {
				$child_template['editable'] = in_array($child_template['templateid'], $editable_templateids);
			}
			unset($child_template);

			foreach ($template['parentTemplates'] as &$parent_template) {
				$parent_template['editable'] = in_array($parent_template['templateid'], $editable_templateids);
			}
			unset($parent_template);

			CArrayHelper::sort($template['tags'], ['tag', 'value']);
			$template['tags'] = CTagHelper::getTagsList($template);
		}
		unset($template);

		if (array_key_exists('custom_text', $options)) {
			$this->resolveColumnTexts($templates, $options['custom_text']);
		}

		CPagerHelper::savePage('template.list', $this->paging['page']);

		return [
			'data_fields' => $data_fields,
			'rows' => array_values(array_map(static fn (array $template) => [[], $template], $templates)),
			'max_in_table' => (int) CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS)
		];
	}

	protected function resolveColumnTexts(array &$objects, array $texts): void {
		$data = array_fill_keys(array_keys($objects), $texts);

		$resolved_texts = CDataTableMacrosResolver::resolveForSection('templates', $data);

		foreach ($objects as &$template) {
			$template['custom_text'] = $resolved_texts[$template['templateid']];
		}
	}
}
