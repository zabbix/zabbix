<?php declare(strict_types = 0);
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


class CControllerTemplateGroupList extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>			'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATE_GROUPS);
	}

	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.templategroups.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.templategroups.sortorder', ZBX_SORT_UP));

		CProfile::update('web.templategroups.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.templategroups.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.templategroups.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.templategroups.filter_name');
		}

		$filter = [
			'name' => CProfile::get('web.templategroups.filter_name', '')
		];

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.templategroups.filter',
			'active_tab' => CProfile::get('web.templategroups.filter.active', 1),
			'config' => [
				'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE)
			],
			'allowed_ui_conf_templates' => CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TEMPLATES)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$groups = API::TemplateGroup()->get([
			'output' => ['groupid', $sort_field],
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name']
			],
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit
		]);
		CArrayHelper::sort($groups, [['field' => $sort_field, 'order' => $sort_order]]);

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('templategroup.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $groups, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$groupids = array_column($groups, 'groupid');

		$data['groupCounts'] = API::TemplateGroup()->get([
			'output' => ['groupid'],
			'selectTemplates' => API_OUTPUT_COUNT,
			'groupids' => $groupids,
			'preservekeys' => true
		]);

		$limit = CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE) + 1;
		$data['groups'] = API::TemplateGroup()->get([
			'output' => ['groupid', 'name'],
			'selectTemplates' => ['templateid', 'name'],
			'groupids' => $groupids,
			'limitSelects' => $limit
		]);
		CArrayHelper::sort($data['groups'], [['field' => $sort_field, 'order' => $sort_order]]);

		foreach ($data['groups'] as &$group) {
			CArrayHelper::sort($group['templates'], ['name']);
		}
		unset($group);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of template groups'));
		$this->setResponse($response);
	}
}
