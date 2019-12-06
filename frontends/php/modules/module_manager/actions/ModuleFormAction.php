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

class ModuleFormAction extends CController {

	/**
	 * Current module data.
	 *
	 * @var array
	 */
	private $module = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'moduleids' =>		'required',
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
		$modules = API::ModuleDetails()->get([
			'output' => ['relative_path', 'id', 'status', 'config'],
			'moduleids' => $this->getInput('moduleids')
		]);
		$this->module = reset($modules);

		return $this->module && $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction() {
		$manager = new CModuleManager(App::getRootDir());
		$manager->loadModule($this->module['relative_path']);
		$manager->initModule($this->module['id'], $this->module['config']);
		$manifest = $manager->getModuleManifest($this->module['id']);
		$data = $this->module + ($manifest ? $manifest : []);

		if ($this->hasInput('form_refresh')) {
			$data['status'] = $this->hasInput('status') ? MODULE_STATUS_ENABLED : MODULE_STATUS_DISABLED;
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Modules'));
		$this->setResponse($response);
	}
}
