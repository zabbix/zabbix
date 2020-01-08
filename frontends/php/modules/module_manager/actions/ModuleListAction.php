<?php

namespace Modules\Example\Actions;

use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CUrl;
use API;
use APP;
use CProfile;
use CModuleManager;

class ModuleListAction extends CController {

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
		$config = select_config();
		$modules = [];
		$dbmodules = API::Module()->get([
			'output' => ['moduleid', 'relative_path', 'id', 'status', 'config'],
			'search' => [
				'id' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'status' => ($filter['status'] == -1) ? null : $filter['status']
			],
			'limit' => $config['search_limit'] + 1,
			'preservekeys' => true
		]);
		$manager = new CModuleManager(App::getRootDir());

		foreach ($dbmodules as $moduleid => $module) {
			$manager->loadModule($module['relative_path']);
			$manifest = $manager->getManifest($module['id']);

			if ($manifest) {
				$modules[$moduleid] = $module + $manifest;
			}
		}

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
}
