<?php

namespace Modules\Example\Actions;

use CController;
use CControllerResponseRedirect;
use CControllerResponseFatal;
use CUrl;
use API;
use APP;
use CAutoloader;
use CModuleManager;

class ModuleScanAction extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction() {
		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'module.list')
				->getUrl()
		);
		$manager = new CModuleManager(App::getRootDir());
		$modules_details = API::ModuleDetails()->get([
			'output' => ['moduleid', 'relative_path', 'id', 'status', 'config']
		]);
		$modules = $manager->scanModulesDirectory(zbx_objectValues($modules_details, 'relative_path'));
		$registered = zbx_objectValues($modules_details, 'relative_path');
		$add_modules = [];
		$del_modules = [];
		$removed = [];
		$added = [];

		foreach ($modules_details as $module) {
			if (in_array($module['relative_path'], $registered)) {
				$manager->loadModule($module['relative_path']);

				if ($module['status'] == MODULE_STATUS_ENABLED) {
//					$manager->enable($module['id']);
					// TODO: when $this->modules will be as array of CModule instances
//					$module->setEnabled(true);
				}
			}
			else {
				$del_modules[] = $module['moduleid'];
				$removed[] = $module['id'];
			}
		}

		foreach ($modules as $module) {
			if (in_array($module['relative_path'], $registered)) {
				continue;
			}

			$instance = $manager->loadModule($module['relative_path']);
		}

		$autoloader = new CAutoloader;
		$autoloader->register();

		foreach ($manager->getLoadedNamespaces() as $namespace => $paths) {
			$autoloader->addNamespace($namespace, $paths);
		}

		foreach ($modules as $module) {
			if (!in_array($module['relative_path'], $registered)) {
				$module['config'] = $manager->registerModule($module['id']);
				$add_modules[] = $module;
				$added[] = $module['id'];
			}
		}

		array_map('error', $manager->getErrors());

		if ($del_modules) {
			$result = API::ModuleDetails()->delete(['moduleids' => $del_modules]);

			if ($result) {
				$response->setMessageOk(_s('Modules deleted: %s', implode(', ', $removed)));
			}
			else {
				$response->setMessageError(_s('Cannot delete modules: %s', implode(', ', $removed)));
			}
		}

		if ($add_modules) {
			$result = API::ModuleDetails()->create($add_modules);

			if ($result) {
				$response->setMessageOk(_s('Modules added: %s', implode(', ', $added)));
			}
			else if ($add_modules) {
				$response->setFormData($this->getInputAll());
				$response->setMessageError(_s('Cannot add modules: %s', implode(', ', $added)));
				$this->setResponse($response);
				return;
			}
		}
		else {
			if ($manager->getErrors()) {
				$response->setMessageError(_('No new modules were found.'));
			}
			else {
				$response->setMessageOk(_('No new modules were found.'));
			}
		}

		$this->setResponse($response);
	}
}
