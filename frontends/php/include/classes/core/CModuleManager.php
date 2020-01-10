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


use CController as Action,
	Core\CModule;

class CModuleManager {

	const MANIFEST_VERSION = 1;
	const MODULES_NAMESPACE = 'Modules\\';

	/**
	 * Modules directory absolute path.
	 */
	private $modules_dir;

	/**
	 * Contains array of modules information.
	 */
	private $loaded_manifests;

	/**
	 * Contains array of CModule instances.
	 *
	 * @var CModule[]
	 */
	private $modules = [];

	/**
	 * Contains array of modules errors.
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * Create class object instance.
	 *
	 * @param string $root_dir  Absolute path to frontend root directory.
	 */
	public function __construct($root_dir) {
		$this->modules_dir = $root_dir.'/modules';

		$db_modules = DB::select('module', [
			'output' => ['id', 'relative_path', 'config'],
			'filter' => ['status' => MODULE_STATUS_ENABLED]
		]);

		$this->loaded_manifests = $this->loadManifests($db_modules);
	}

	/**
	 * Get module class instance.
	 *
	 * @param string $id  Module manifest id.
	 *
	 * @return CModule|null
	 */
	public function getModuleById($id) {
		return array_keys($id, $this->modules) ? $this->modules[$id] : null;
	}

	/**
	 * Get module by action.
	 *
	 * @param string $action  Action of module.
	 *
	 * @return CModule|null
	 */
	public function getModuleByActionName($action) {
		/** @var CModule $module */
		foreach ($this->modules as $module) {
			if (array_key_exists($action, $module->getActions())) {
				return $module;
			}
		}

		return null;
	}

	/**
	 * Get enabled modules namespaces as array where key is module full namespace and value is array of absolutes paths.
	 *
	 * @return array
	 */
	public function getNamespaces() {
		$namespaces = [];

		foreach ($this->loaded_manifests as $manifest) {
			$namespaces[$manifest['namespace']] = [$manifest['path']];
			$namespaces[self::MODULES_NAMESPACE.$manifest['namespace']] = [$manifest['path']];
		}

		return $namespaces;
	}

	/**
	 * Return actions only for modules with enabled status.
	 *
	 * @return array
	 */
	public function getRoutes() {
		$routes = [];
		$default = [
			'layout' => 'layout.htmlpage',
			'view' => null
		];

		/** @var CModule $module */
		foreach ($this->modules as $module) {
			if ($module->isEnabled()) {
				$namespace = self::MODULES_NAMESPACE.$module->getNamespace().'\\Actions\\';

				foreach ($module->getActions() as $action => $data) {
					$routes[$action] = ['class' => $namespace.$data['class']] + $data + $default;
				}
			}
		}

		return $routes;
	}

	/**
	 * Load modules manifests.
	 *
	 * @param string $modules[]['id']             Module unique id.
	 * @param string $modules[]['relative_path']  Relative path to module directory.
	 * @param string $modules[]['config']         JSON string with module configuration data.
	 * @param bool   $check_conflicts             Check conflicts between modules.
	 *
	 * @return array
	 */
	public function loadManifests($modules, $check_conflicts = true) {
		$manifests = [];

		foreach ($modules as $module) {
			$manifest = $this->loadManifest($module['relative_path']);

			if ($manifest == null) {
				continue;
			}

			$manifest['config'] += json_decode($module['config'], true);

			if ($check_conflicts) {
				$this->errors[$manifest['id']] = $this->checkConflicts($manifest, $manifests);

				if (!$this->errors[$manifest['id']]) {
					$manifests[$manifest['id']] = $manifest;
				}
			}
			else {
				$manifests[$manifest['id']] = $manifest;
			}
		}

		return $manifests;
	}

	/**
	 * Load module manifest.
	 *
	 * @param string $relative_path     Relative path to module directory.
	 *
	 * @return array|null
	 */
	public function loadManifest($relative_path) {
		$module_path = $this->modules_dir.DIRECTORY_SEPARATOR.trim($relative_path, DIRECTORY_SEPARATOR);
		$manifest_filename = $module_path.DIRECTORY_SEPARATOR.'manifest.json';

		if (!file_exists($manifest_filename)) {
			return null;
		}

		$manifest_file = file_get_contents($manifest_filename);

		if (!$manifest_file) {
			return null;
		}

		$manifest = json_decode($manifest_file, true);

		if (!is_array($manifest)) {
			return null;
		}

		if (!array_key_exists('id', $manifest)) {
			// Required id does not exists.
			return null;
		}

		$manifest += [
			'namespace' => '',
			'path' => $module_path,
			'manifest_version' => 0,
			'actions' => [],
			'config' => []
		];

		if ($manifest['manifest_version'] > static::MANIFEST_VERSION) {
			// Unknown manifest version.
			return null;
		}

		if ($manifest['namespace'] === '' || !preg_match('/^[a-z_]+$/i', $manifest['namespace'])) {
			// Wrong namespace.
			return null;
		}

		return $manifest;
	}

	/**
	 * Create active modules instances.
	 */
	public function loadModules() {
		foreach ($this->loaded_manifests as $manifest) {
			$main_class = 'CModule';
			$module_class = $manifest['path'] ? self::MODULES_NAMESPACE.$manifest['namespace'].'\\Module' : $main_class;

			try {
				if (!class_exists($module_class, true)) {
					throw new Exception(_s('Module %s class %s not found.', $manifest['id'], $module_class));
				}

				$instance = new $module_class($manifest);

				if (!($instance instanceof CModule) || !is_subclass_of($instance, CModule::class)) {
					throw new Exception(_s('%s class must extend %s class.', $module_class, $main_class));
				}

				if ($this->errors[$manifest['id']]) {
					$instance->setEnabled(false);
				}

				$this->modules[$manifest['id']] = $instance;
			} catch (Exception $e) {
				$this->errors[$manifest['id']][] = $e;
			}

			unset($this->loaded_manifests[$manifest['id']]);
		}
	}

	/**
	 * Initialize enabled modules. Call init method for every module instance.
	 */
	public function initModules() {
		foreach ($this->modules as $module) {
			if ($module->isEnabled()) {
				$module->init();
			}
		}
	}

	/**
	 * Check conflicts between modules in namespace and actions. Return array with errors.
	 *
	 * @param array $manifest    Module manifest to be checked against $manifests
	 * @param array $manifests   Array of checked and valid modules.
	 *
	 * @return array
	 */
	private function checkConflicts($manifest, $manifests) {
		$errors = [];

		foreach ($manifests as $stored_manifest) {
			$checked_modules = implode(', ', [$manifest['id'], $stored_manifest['id']]);

			if ($manifest['id'] == $stored_manifest['id']) {
				$this->errors[$manifest['id']][] = new Exception(
					_s('Conflict for module %s id.', $manifest['id'])
				);
			}

			if ($manifest['namespace'] == $stored_manifest['namespace']) {
				$this->errors[$manifest['id']][] = new Exception(
					_s('Modules %s use same namespace %s.', $checked_modules, $manifest['namespace'])
				);
			}

			$conflicted_actions = array_intersect_key($manifest['actions'], $stored_manifest['actions']);

			if ($conflicted_actions) {
				$this->errors[$manifest['id']][] = new Exception(
					_n('Modules %s use same action %s.', 'Modules %s use same actions %s.', $conflicted_actions,
						implode(', ', array_keys($conflicted_actions)), count($conflicted_actions))
				);
			}
		}

		return $errors;
	}

	/**
	 * Get module init errors. Return array where key is module id and value is array of error string messages.
	 *
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * Call modules before action event.
	 *
	 * @param Action $action  Action instance responsible for current request
	 */
	public function beforeAction(Action $action) {
		$this->invokeEventHandler($action, 'beforeAction');
	}

	/**
	 * Modules method to be called before application will exit and send response to browser.
	 *
	 * @param Action $action  Action instance responsible for current request.
	 */
	public function afterAction(Action $action) {
		$this->invokeEventHandler($action, 'afterAction');
	}

	/**
	 * Invokes event handler for every enabled module, current module event handler will be invoked last.
	 *
	 * @param Action $action   Current action object.
	 * @param string $event    Module event handler name.
	 */
	private function invokeEventHandler(Action $action, $event) {
		$action_name = $action->getAction();
		$current_module = $this->getModuleByActionName($action_name);

		foreach ($this->modules as $module) {
			if ($module->isEnabled() && !array_key_exists($action_name, $module->getActions())) {
				$module->$event($action);
			}
		}

		if ($current_module instanceof CModule) {
			$current_module->$event($action);
		}
	}
}
