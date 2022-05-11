<?php
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


/**
 * Module list action.
 */
class CControllerModuleList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>			'in name',
			'sortorder' =>		'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in -1,'.MODULE_STATUS_ENABLED.','.MODULE_STATUS_DISABLED,
			'uncheck' =>		'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		// sort fields
		$sort_field = $this->getInput('sort', CProfile::get('web.modules.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.modules.sortorder', ZBX_SORT_UP));

		CProfile::update('web.modules.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.modules.sortorder', $sort_order, PROFILE_TYPE_STR);

		// filter fields
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.modules.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.modules.filter.status', getRequest('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.modules.filter.name');
			CProfile::delete('web.modules.filter.status');
		}

		$filter = [
			'name' => trim(CProfile::get('web.modules.filter.name', '')),
			'status' => CProfile::get('web.modules.filter.status', -1)
		];

		// data prepare
		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$db_modules = API::Module()->get([
			'output' => ['id', 'relative_path', 'status'],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'sortfield' => 'relative_path',
			'limit' => $limit,
			'preservekeys' => true
		]);

		$module_manager = new CModuleManager(APP::ModuleManager()->getModulesDir());
		$modules = [];

		foreach ($db_modules as $moduleid => $db_module) {
			$manifest = $module_manager->addModule($db_module['relative_path']);

			if ($manifest && ($filter['name'] === '' || mb_stripos($manifest['name'], $filter['name']) !== false)) {
				$modules[$moduleid] = $db_module + $manifest;
			}
		}

		// data sort and pager
		order_result($modules, $sort_field, $sort_order);

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('module.list', $page_num);
		$paging = CPagerHelper::paginate($page_num, $modules, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'uncheck' => $this->hasInput('uncheck'),
			'modules' => $modules,
			'paging' => $paging,
			'filter_profile' => 'web.modules.filter',
			'filter_active_tab' => CProfile::get('web.modules.filter.active', 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Modules'));

		$this->setResponse($response);
	}
}
