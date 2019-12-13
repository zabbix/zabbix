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


class CModuleManager {

	const MANIFEST_VERSION = 1;

	const MODULES_NAMESPACE = 'Modules\\';

	/**
	 * Modules directory absolute path.
	 */
	protected $modules_dir;

	/**
	 * Contains array of modules information.
	 *
	 * object []['instance']  module class instance.
	 * array  []['manifest']  module parsed manifest.json file.
	 * string []['dir']       module directory absolute path with trailing slash.
	 * bool   []['status']    module enabled status.
	 * array  []['errors']    module intialization errors, array of exception objects.
	 * array  []['path']      module absolute path to directory, manifest.json and Module.php files.
	 *
	 */
	protected $modules = [];

	/**
	 * Create class object instance
	 *
	 * @param string $root_dir  Absolute path to frontend root directory.
	 */
	public function __construct($root_dir) {
		$this->modules_dir = $root_dir.'/modules';
	}

	/**
	 * Scan modules directory, parses manifest.json for valid modules. Return relative path and module manifest id for
	 * valid modules.
	 *
	 * @return array
	 */
	public function scanModulesDirectory() {
		$modules = [];

		foreach (new DirectoryIterator($this->modules_dir) as $path) {
			if ($path->isDot() || !$path->isDir()) {
				continue;
			}

			$manifest_path =  $this->modules_dir.'/'.trim($path->getFilename(), '/').'/manifest.json';
			$manifest = $this->parseManifestFile($manifest_path);

			if ($manifest) {
				$modules[] = [
					'id' => $manifest['id'],
					'relative_path' => $path->getFilename()
				];
			}
		}

		return $modules;
	}

	/**
	 * Parse manifest.json file and check it syntax, will return null for invalid manifest.json.
	 *
	 * @param string $path  Absolute path to manifest.json file.
	 *
	 * @return array|null
	 */
	public function parseManifestFile($path) {
		if (!file_exists($path)) {
			return null;
		}

		$manifest = json_decode(file_get_contents($path), true);

		if (!is_array($manifest)) {
			return null;
		}

		$manifest += ['manifest_version' => 0, 'namespace' => '', 'actions' => []];

		if ($manifest['manifest_version'] > static::MANIFEST_VERSION) {
			// Unknown manifest version.
			return null;
		}

		if (!array_key_exists('id', $manifest)) {
			// Required id does not exists.
			return null;
		}

		if ($manifest['namespace'] === '' || !preg_match('/^[a-z_]+$/i', $manifest['namespace'])) {
			// Wrong namespace.
			return null;
		}

		return $manifest;
	}

	/**
	 * Set module runtime enabled/disabled status.
	 *
	 * @param string $id  Module manifest id.
	 */
	public function enable($id) {
		$index = $this->getModuleIndexById($id);

		if (!is_null($index)) {
			$this->modules[$index]['status'] = true;
		}
	}

	/**
	 * Set module runtime disabled status.
	 *
	 * @param string $id  Module manifest id.
	 */
	public function disable($id) {
		$index = $this->getModuleIndexById($id);

		if (!is_null($index)) {
			$this->modules[$index]['status'] = false;
		}
	}

	/**
	 * Load and parse it manifest file.
	 *
	 * @param string $relative_path  Relative path to module folder.
	 *
	 * @return array|null
	 */
	public function loadModule($relative_path) {
		$dir = $this->modules_dir.'/'.trim($relative_path, '/');
		$manifest_path = $dir.'/manifest.json';
		$module_path = $dir.'/Module.php';
		$manifest = $this->parseManifestFile($manifest_path);

		if (!$manifest) {
			return null;
		}

		$index = $this->getModuleIndexById($manifest['id']);
		$new_module = [
			'id' => $manifest['id'],
			'path' => [
				'root' => $dir,
				'manifest' => $manifest_path,
				'module' => file_exists($module_path) ? $module_path : ''
			],
			'manifest' => $manifest,
			'namespace' => static::MODULES_NAMESPACE.$manifest['namespace'],
			'status' => false,
			'instance' => null,
			'errors' => []
		];

		if (!is_null($index)) {
			$new_module['errors'][] = new Exception(_s('Conflict for module %s id.', $manifest['id']));
		}

		if (array_key_exists($new_module['namespace'], $this->getLoadedNamespaces())) {
			$modules = [$manifest['id']];

			foreach ($this->modules as $module) {
				if ($module['namespace'] === $new_module['namespace']) {
					$modules[] = $module['id'];
				}
			}

			$new_module['errors'][] =  new Exception(_s('Modules %s use same namespace %s.', implode(', ', $modules),
				$new_module['namespace']
			));
		}

		$this->modules[] = $new_module;

		return $new_module;
	}

	/**
	 * Get module by action.
	 *
	 * @param string $action  Action of module.
	 *
	 * @return CModule|null
	 */
	public function getModuleByAction($action) {
		foreach ($this->modules as $module) {
			if ($module['instance'] && array_key_exists('actions', $module['manifest'])
					&& array_key_exists($action, $module['manifest']['actions'])) {

				return $module['instance'];
			}
		}

		return null;
	}

	/**
	 * Get module directory absolute path.
	 *
	 * @param string $id        Module manifest id.
	 * @param bool   $relative  Return relative or absolute path to module directory.
	 *
	 * @return string|null
	 */
	public function getModuleRootDir($id, $relative = false) {
		$index = $this->getModuleIndexById($id);

		if (is_null($index)) {
			return null;
		}

		$path = $this->modules[$index]['path']['root'];

		if ($relative && $path) {
			$path = substr($path, strlen($this->modules_dir) + 1);
		}

		return $path;
	}

	/**
	 * Get enabled modules namespaces as array where key is module full namespace and value is array of absolutes paths.
	 *
	 * @return array
	 */
	public function getRegisteredNamespaces() {
		$namespaces = [];

		foreach ($this->modules as $module) {
			if ($module['status']) {
				$namespaces[$module['namespace']] = [$module['path']['root']];
			}
		}

		return $namespaces;
	}

	/**
	 * Get loaded modules namespaces as array where key is module full namespace and value is array of absolutes paths.
	 *
	 * @return array
	 */
	public function getLoadedNamespaces() {
		$namespaces = [];

		foreach ($this->modules as $module) {
			$namespaces[$module['namespace']] = [$module['path']['root']];
		}

		return $namespaces;
	}

	/**
	 * Create instance of module and call register. Return module register method response. Is used only once when
	 * module folder scan find new modules.
	 *
	 * @param string $id  Module manifest id.
	 *
	 * @return array
	 */
	public function registerModule($id) {
		$config = [];
		$instance = $this->getModuleInstance($id);

		if ($instance) {
			$relative_path = $this->getModuleRootDir($id, true);
			$config = $instance->register($relative_path);
		}

		return $config;
	}

	/**
	 * Create instance of module and call init for enabled module.
	 *
	 * @param string $id      Module manifest id.
	 * @param array  $config  Module configuration array, will be passed to init method.
	 *
	 * @return CModule|null
	 */
	public function initModule($id, array $config) {
		$instance = $this->getModuleInstance($id);

		if ($instance) {
			$instance->init($config);
		}

		return $instance;
	}

	/**
	 * Get module class instance.
	 *
	 * @param string $id  Module manifest id.
	 *
	 * @return CModule|null
	 */
	public function getModuleInstance($id) {
		$index = $this->getModuleIndexById($id);

		if ($index === null) {
			return null;
		}

		if ($this->modules[$index]['instance']) {
			return $this->modules[$index]['instance'];
		}

		$instance = null;
		$main_class = '\\CModule';
		$module_class = $this->modules[$index]['path']['module']
			? $this->modules[$index]['namespace'].'\\Module'
			: $main_class;

		try {
			if (!class_exists($module_class, true)) {
				throw new Exception(_s('Module %s class %s not found.', $id, $module_class));
			}

			$instance = new $module_class($this->modules[$index]['manifest']);

			if (!($instance instanceof CModule) || !is_subclass_of($instance, CModule::class)) {
				throw new Exception(_s('%s class must extend %s class.', $module_class, $main_class));
			}

			$this->modules[$index]['instance'] = $instance;
		} catch (Exception $e) {
			$this->modules[$index]['errors'][] = $e;
		}

		return $instance;
	}

	/**
	 * Get module manifest as array. This method is used to get manifest for not enabled modules when there are no
	 * module instance available or there is error during module instance creation. For healthy module instances
	 * $instance->getManifest() method should be used instead.
	 *
	 * @param string $id  Module manifest id.
	 *
	 * @return array|null
	 */
	public function getModuleManifest($id) {
		$index = $this->getModuleIndexById($id);

		if ($index != null) {
			return $this->modules[$index]['manifest'];
		}

		return null;
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

		foreach ($this->modules as $module) {
			if ($module['status']) {
				$namespace = $module['namespace'].'\\Actions\\';

				foreach ($module['manifest']['actions'] as $action => $data) {
					$routes[$action] = [
						'class' => $namespace.$data['class']
					] + $data + $default;
				}
			}
		}

		return $routes;
	}

	/**
	 * Get loaded modules ids as array.
	 *
	 * @return array
	 */
	public function getLoadedModules() {
		return CArrayHelper::getByKeysStrict($this->modules, ['id']);
	}

	/**
	 * Get module init errors. Return array where key is module id and value is array of error string messages.
	 *
	 * @return array
	 */
	public function getErrors() {
		$errors = [];

		foreach ($this->modules as $module_details) {
			if ($module_details['errors']) {
				$errors[$module_details['id']] = [];

				foreach ($module_details['errors'] as $error) {
					$errors[$module_details['id']][] = $error->getMessage();
				}
			}
		}

		return $errors;
	}

	/**
	 * Get index in modules collection for module by module manifest.id
	 *
	 * @param string $id  Module manifest id.
	 *
	 * @return int|null
	 */
	protected function getModuleIndexById($id) {
		foreach ($this->modules as $index => $module) {
			if ($module['id'] === $id) {

				return $index;
			}
		}

		return null;
	}
}
