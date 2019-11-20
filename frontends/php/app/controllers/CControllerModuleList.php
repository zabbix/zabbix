<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
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
			'name' => CProfile::get('web.modules.filter.name', ''),
			'status' => CProfile::get('web.modules.filter.status', -1)
		];

		// data prepare

		/*
			PROTOTYPE:

			$config = select_config();

			$modules = API::Module()->get([
				'output' => ['name', 'version', 'status'],
				'search' => [
					'name' => ($filter['name'] === '') ? null : $filter['name']
				],
				'filter' => [
					'status' => ($filter['status'] == -1) ? null : $filter['status']
				],
				'limit' => $config['search_limit'] + 1,
				'editable' => true,
				'preservekeys' => true
			]);
		*/

		// Testing dummy modules.
		$modules = self::getModules($filter, $sort_field, $sort_order);

		foreach ($modules as $moduleid => &$module) {
			/*
				PROTOTYPE:

				$manifest = CModuleRegistry::getManifest($moduleid);
			*/

			// Testing dummy manifest.
			$manifest = [
				'author' => 'John Smith',
				'description' => 'Test data module description. Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
			];

			if ($manifest !== null) {
				$module = array_replace($module, [
					'author' => array_key_exists('author', $manifest) ? $manifest['author'] : null,
					'description' => array_key_exists('description', $manifest) ? $manifest['description'] : null
				]);
			}
			else {
				$module = array_replace($module, [
					'author' => null,
					'description' => null
				]);
			}
		}
		unset($module);

		$paging = getPagingLine($modules, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', 'module.list')
		);

		$data = [
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'uncheck' => $this->hasInput('uncheck'),
			'modules' => $modules,
			'paging' => $paging,
			'filter_profile' => 'web.modules.filter',
			'filter_active_tab' => CProfile::get('web.modules.filter.active', 1),
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Modules'));
		$this->setResponse($response);
	}

	// Dummy substitute for API::Module()->get().
	private static function getModules($filter, $sort_field, $sort_order) {
		$modules = [];

		for ($i = 1; $i <= 100; $i++) {
			$modules[$i] = [
				'name' => 'Test Module - '.$i,
				'version' => '1.0.'.$i,
				'status' => $i % 2 ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED
			];
		}

		$modules = array_filter($modules, function($module, $key) use ($filter) {
			if ($filter['status'] != -1 && $filter['status'] != $module['status']) {
				return false;
			}
			if ($filter['name'] !== '' && stripos($module['name'], $filter['name']) === false) {
				return false;
			}

			return true;
		}, ARRAY_FILTER_USE_BOTH);

		order_result($modules, $sort_field, $sort_order);

		return $modules;
	}
}
