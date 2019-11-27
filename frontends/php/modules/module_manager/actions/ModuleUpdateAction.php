<?php

namespace Modules\Example\Actions;

use CController;
use CControllerResponseRedirect;
use CControllerResponseFatal;
use CUrl;
use API;
use APP;
use CModuleManager;

class ModuleUpdateAction extends CController {

	protected function checkInput() {
		$fields = [
			'moduleid' =>		'id',
			'moduleids' =>		'array'	,
			// form update fields
			'status' =>			'in 1',
			'form_refresh' =>	'int32'
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
		$modules = [];
		$moduleids = $this->getInput('moduleids', []);
		$count = count($moduleids);

		switch ($this->getAction()) {
			case 'module.update':
				$fields = ['status' => $this->hasInput('status') ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED];
				$success = _('Module updated');
				$error = _('Cannot update module');
				$moduleids = $this->getAllowedToEnable([$this->getInput('moduleid')]);
				break;

			case 'module.enable':
				$fields = ['status' => MODULE_STATUS_ENABLED];
				$success = _n('Module enabled', 'Modules enabled', $count);
				$error = _n('Cannot enable module', 'Cannot enable modules', $count);
				$moduleids = $this->getAllowedToEnable($moduleids);
				break;

			case 'module.disable':
				$fields = ['status' => MODULE_STATUS_DISABLED];
				$success = _n('Module disabled', 'Modules disabled', $count);
				$error = _n('Cannot disable module', 'Cannot disable modules', $count);
				break;
		}

		foreach ($moduleids as $moduleid) {
			$modules[] = compact('moduleid') + $fields;
		}

		$result = $moduleids ? API::ModuleDetails()->update($modules) : false;
		$url = (new CUrl('zabbix.php'))->setArgument('action', 'module.list');

		if ($result) {
			$url->setArgument('uncheck', '1');
			$response = new CControllerResponseRedirect($url->getUrl());
			$response->setMessageOk($success);
		}
		else {
			if ($this->getAction() === 'module.edit') {
				$url
					->setArgument('action', 'module.edit')
					->setArgument('moduleid', $moduleid);
			}

			$response = new CControllerResponseRedirect($url->getUrl());
			$response->setFormData($this->getInputAll());
			$response->setMessageError($error);
		}

		$this->setResponse($response);
	}

	/**
	 * Return only moduleids allowed to be enabled.
	 *
	 * @param array $moduleids      Module moduleids.
	 * @return array
	 */
	protected function getAllowedToEnable(array $moduleids) {
		$manager = new CModuleManager(App::getRootDir());
		// Initialise already active modules.
		$enabled = API::ModuleDetails()->get([
			'ouput' => ['moduleid', 'id', 'config'],
			'filter' => ['status' => MODULE_STATUS_ENABLED],
			'preservekeys' => true
		]);
		$disabledids = array_diff($moduleids, array_keys($enabled));

		if (!$disabledids) {
			return $disabledids;
		}

		// Modules to be enabled.
		$disabled = API::ModuleDetails()->get([
			'ouput' => ['moduleid', 'id', 'config'],
			'moduleids' => $disabledids
		]);
		$modules = array_merge($enabled, $disabled);
		$allowedids = [];

		foreach ($modules as $module) {
			$manager->loadModule($module['relative_path']);
			$manager->enable($module['id']);
			$manager->initModule($module['id'], $module['config']);

			if (!array_key_exists($module['id'], $manager->getErrors())) {
				$moduleids[] = $module['moduleid'];
			}
		}

		$errors = $manager->getErrors();
		array_map('error', $manager->getErrors());

		return $allowedids;
	}
}
