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
	 * object []['instance']    module class instance.
	 * array  []['manifest']    module parsed manifest.json file.
	 * string []['dir']         module directory absolute path with trailing slash.
	 * bool   []['status']      module enabled status.
	 * array  []['errors']      module intialization errors, array of exception objects.
	 * array  []['path']        module absolute path to directory, manifest.json and Module.php files.
	 *
	 */
	protected $modules = [];

		/**
	 * Create class object instance
	 *
	 * @param string $modules_dir    Absolute path to frontend root directory.
	 */
	public function __construct($root_dir) {
		$this->modules_dir = $root_dir.'/modules';
	}

	/**
	 * Scan modules directory and register valid modules, parses manifest.json for valid modules.
	 */
	public function scanModulesDirectory() {
		foreach (new DirectoryIterator($this->modules_dir) as $path) {
			if ($path->isDot() || !$path->isDir()) {
				continue;
			}

			$this->loadModule($path->getFilename());
		}
	}

	/**
	 * Parse manifest.json file and check it syntax, will return null for invalid manifest.json.
	 *
	 * @param string $path    Absolute path to manifest.json file.
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
	 * @param string $moduleid   Module id.
	 */
	public function enable($moduleid) {
		$index = $this->getModuleIndexById($moduleid);

		if (!is_null($index)) {
			$this->modules[$index]['status'] = true;
		}
	}

	/**
	 * Set module runtime disabled status.
	 *
	 * @param string $moduleid   Module id.
	 */
	public function disable($moduleid) {
		$index = $this->getModuleIndexById($moduleid);

		if (!is_null($index)) {
			$this->modules[$index]['status'] = false;
		}
	}

	/**
	 * Load and parse it manifest file.
	 */
	public function loadModule($relative_path) {
		$dir = $this->modules_dir.'/'.trim($relative_path, '/');
		$manifest_path = $dir.'/manifest.json';
		$module_path = $dir.'/Module.php';
		$manifest = $this->parseManifestFile($manifest_path);

		if ($manifest) {
			$this->modules[] = [
				'id' => $manifest['id'],
				'path' => [
					'root' => $dir,
					'manifest' => $manifest_path,
					'module' => file_exists($module_path) ? $module_path : ''
				],
				'manifest' => $manifest,
				'namespace' => 'Modules\\'.$manifest['namespace'],
				'status' => false,
				'instance' => null,
				'errors' => []
			];
		}
	}

	/**
	 * Get module by action.
	 *
	 * @param string $action       Action of module.
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
	 * Get module instance by module id.
	 *
	 * @param string $moduleid      Module unique id as defined in manifest.json file.
	 * @return CModule|null
	 */
	public function getModuleById($moduleid) {
		$index = $this->getModuleIndexById($moduleid);

		if (is_null($index)) {
			return null;
		}

		return $this->modules[$index]['instance'];
	}

	/**
	 * Get module directory absolute path.
	 *
	 * @param string $moduleid      Module unique identifier.
	 * @param bool   $relative      Return relative or absolute path to module directory.
	 * @return string|null
	 */
	public function getModuleRootDir($moduleid, $relative = false) {
		$index = $this->getModuleIndexById($moduleid);

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
	 * Get enabled modules namespaces array.
	 *
	 * @return array
	 */
	public function getRegisteredNamespaces() {
		$namespaces = [];

		foreach ($this->modules as $module) {
			if ($module['status']) {
				$namespaces[static::MODULES_NAMESPACE.$module['manifest']['namespace']] = [$module['path']['root']];
			}
		}

		return $namespaces;
	}

	/**
	 * Create instance of module and call register. Return module register method response.
	 *
	 * @param string $moduleid      Module unique identifier.
	 * @return array
	 */
	public function registerModule($moduleid) {
		$config = [];
		$index = $this->getModuleIndexById($moduleid);

		try {
			if (is_null($index)) {
				throw new Exception(_('Cannot register %s module.', $moduleid));
			}

			$registered = $this->getRegisteredNamespaces();
			$namespace = static::MODULES_NAMESPACE.$this->modules[$index]['manifest']['namespace'];
			$main_class = '\\CModule';
			$module_class = $this->modules[$index]['path']['module']
				? 'Modules\\'.$this->modules[$index]['manifest']['namespace'].'\\Module'
				: $main_class;

			if ($module_class === $main_class && $this->modules[$index]['manifest']['actions']) {
				throw new Exception(_s('Module %s should have Module.php file.', $moduleid));
			}

			if (array_key_exists($namespace, $registered)) {
				throw new Exception(_s('Module %s use already reserved namespace %s.', $moduleid, $namespace));
			}

			if (!class_exists($module_class, true)) {
				throw new Exception(_s('Module %s class %s not found.', $moduleid, $module_class));
			}

			$instance = new $module_class($this->modules[$index]['manifest']);

			if (!($instance instanceof CModule) || is_null($instance->getManifest())) {
				throw new Exception(_s('%s class must extend %s class.', $module_class, $main_class));
			}

			$relative_path = $this->getModuleRootDir($moduleid, true);
			$config = $instance->register($relative_path);
			$this->modules[$index]['instance'] = $instance;
		}
		catch (Exception $e) {
			$this->modules[$index]['errors'][] = $e;
		}

		return $config;
	}

	/**
	 * Create instance of module and call init for enabled module.
	 *
	 * @param string $moduleid      Module unique identifier.
	 * @param array  $config        Module configuration array, will be passed to init method.
	 */
	public function initModule($moduleid, array $config) {
		$index = $this->getModuleIndexById($moduleid);

		if (is_null($index)) {
			return;
		}

		$main_class = '\\CModule';
		$module_class = $this->modules[$index]['path']['module']
			? 'Modules\\'.$this->modules[$index]['manifest']['namespace'].'\\Module'
			: $main_class;

		try {
			if (!class_exists($module_class, true)) {
				throw new Exception(_s('Module %s class %s not found.', $moduleid, $module_class));
			}

			$instance = new $module_class($this->modules[$index]['manifest']);

			if (!($instance instanceof CModule) || is_null($instance->getManifest())) {
				throw new Exception(_s('%s class must extend %s class.', $module_class, $main_class));
			}

			if ($this->modules[$index]['status']) {
				$instance->init($config);
			}

			$this->modules[$index]['instance'] = $instance;
		}
		catch (Exception $e) {
			$this->disable($moduleid);
			$this->modules[$index]['errors'][] = $e;
		}
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
				$namespace = 'Modules\\'.$module['manifest']['namespace'].'\\Actions\\';

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
		return zbx_objectValues($this->modules, 'id');
	}

	/**
	 * Get module init errors. Return array where key is module namespace and value is array of error string messages.
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
	 * @param string $moduleid      Module id.
	 * @return int|null
	 */
	protected function getModuleIndexById($moduleid) {
		foreach ($this->modules as $index => $module) {
			if ($module['id'] === $moduleid) {

				return $index;
			}
		}

		return null;
	}
}
