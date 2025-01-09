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


class CControllerTemplateList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'filter_name' =>			'string',
			'filter_vendor_name' =>		'string',
			'filter_vendor_version' =>	'string',
			'filter_templates' =>		'array_db hosts.hostid',
			'filter_groups' =>			'array_db hosts_groups.groupid',
			'filter_evaltype' =>		'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>			'array',
			'sort' =>					'in name',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>				'in 1',
			'page' =>					'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			$this->updateProfiles();
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles();
		}

		$filter = [
			'name' => CProfile::get('web.templates.filter_name', ''),
			'vendor_name' => CProfile::get('web.templates.filter_vendor_name', ''),
			'vendor_version' => CProfile::get('web.templates.filter_vendor_version', ''),
			'templates' => CProfile::getArray('web.templates.filter_templates'),
			'groups' => CProfile::getArray('web.templates.filter_groups'),
			'evaltype' => CProfile::get('web.templates.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => []
		];

		foreach (CProfile::getArray('web.templates.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.templates.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.templates.filter.tags.operator', null, $i)
			];
		}

		CArrayHelper::sort($filter['tags'], ['tag', 'value', 'operator']);

		$sort_field = $this->getInput('sort', CProfile::get('web.templates.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.templates.sortorder', ZBX_SORT_UP));

		CProfile::update('web.templates.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.templates.sortorder', $sort_order, PROFILE_TYPE_STR);

		$filter['templates'] = $filter['templates']
			? CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templates'],
				'preservekeys' => true
			]), ['templateid' => 'id'])
			: [];

		// Get template groups.
		$filter['groups'] = $filter['groups']
			? CArrayHelper::renameObjectsKeys(API::TemplateGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groups'],
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		$filter_groupids = $filter['groups'] ? array_keys($filter['groups']) : null;

		if ($filter_groupids) {
			$filter_groupids = getTemplateSubGroups($filter_groupids);
		}

		// Select templates.
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$templates = API::Template()->get([
			'output' => ['templateid', $sort_field],
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
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

		$templates = API::Template()->get([
			'output' => ['templateid', 'name', 'vendor_name', 'vendor_version'],
			'selectHosts' => ['hostid'],
			'selectTemplates' => ['templateid', 'name'],
			'selectParentTemplates' => ['templateid', 'name'],
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'selectDashboards' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectTags' => ['tag', 'value'],
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

		if (!$filter['tags']) {
			$filter['tags'] = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
		}

		$data = [
			'action' => $this->getAction(),
			'templates' => $templates,
			'filter' => $filter,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'editable_templates' => $editable_templates,
			'editable_hosts' => $editable_hosts,
			'profileIdx' => 'web.templates.filter',
			'active_tab' => CProfile::get('web.templates.filter.active', 1),
			'tags' => makeTags($templates, true, 'templateid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']),
			'config' => [
				'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
			],
			'allowed_ui_conf_hosts' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
			'uncheck' => ($this->getInput('uncheck', 0) == 1)
		];

		// pager
		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('template.list', $page_num);
		$data['page'] = $page_num;
		$data['paging'] = CPagerHelper::paginate($page_num, $data['templates'], $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of templates'));
		$this->setResponse($response);
	}

	private function updateProfiles(): void {
		$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
		foreach ($this->getInput('filter_tags', []) as $filter_tag) {
			if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
				continue;
			}

			$filter_tags['tags'][] = $filter_tag['tag'];
			$filter_tags['values'][] = $filter_tag['value'];
			$filter_tags['operators'][] = $filter_tag['operator'];
		}

		CProfile::update('web.templates.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.templates.filter_vendor_name', $this->getInput('filter_vendor_name', ''),
			PROFILE_TYPE_STR
		);
		CProfile::update('web.templates.filter_vendor_version', $this->getInput('filter_vendor_version', ''),
			PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter_templates', $this->getInput('filter_templates', []),
			PROFILE_TYPE_ID);
		CProfile::updateArray('web.templates.filter_groups', $this->getInput('filter_groups', []), PROFILE_TYPE_ID);
		CProfile::update('web.templates.filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			PROFILE_TYPE_INT
		);
		CProfile::updateArray('web.templates.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
		CProfile::updateArray('web.templates.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
	}

	private function deleteProfiles(): void {
		CProfile::delete('web.templates.filter_name');
		CProfile::delete('web.templates.filter_vendor_name');
		CProfile::delete('web.templates.filter_vendor_version');
		CProfile::deleteIdx('web.templates.filter_templates');
		CProfile::deleteIdx('web.templates.filter_groups');
		CProfile::delete('web.templates.filter.evaltype');
		CProfile::deleteIdx('web.templates.filter.tags.tag');
		CProfile::deleteIdx('web.templates.filter.tags.value');
		CProfile::deleteIdx('web.templates.filter.tags.operator');
	}
}
