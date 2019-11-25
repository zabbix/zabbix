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

	/**
	 * Modules directory absolute path.
	 */
	protected $modules_dir;

	/**
	 * Contains array of modules information.
	 *
	 * object [namespace]['instance']    module class instance.
	 * array  [namespace]['manifest']    module parsed manifest.json file.
	 * string [namespace]['dir']         module directory absolute path with trailing slash.
	 * bool   [namespace]['status']      module enabled status.
	 * array  [namespace]['errors']      module intialization errors, array of exception objects.
	 * array  [namespace]['path']        module absolute path to directory, manifest.json and Module.php files.
	 *
	 */
	protected $modules = [];

		/**
	 * Create class object instance
	 *
	 * @param string $modules_dir    Absolute path to modules directory.
	 */
	public function __construct($modules_dir) {
		$this->modules_dir = $modules_dir;
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
	 * @param string $id        Module id.
	 */
	public function enable($id) {
		if (array_key_exists($id, $this->modules)) {
			$this->modules[$id]['status'] = true;
		}
	}

	/**
	 * Set module runtime disabled status.
	 *
	 * @param string $id        Module id.
	 */
	public function disable($id) {
		if (array_key_exists($id, $this->modules)) {
			$this->modules[$id]['status'] = false;
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
			$this->modules[$manifest['id']] = [
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
	 * Get enabled modules namespaces array.
	 *
	 * @return array
	 */
	public function getRegisteredNamespaces() {
		$global_namespace = 'Modules\\';
		$namespaces = [];

		foreach ($this->modules as $module) {
			if ($module['status']) {
				$namespaces[$global_namespace.$module['manifest']['namespace']] = [$module['path']['root']];
			}
		}

		return $namespaces;
	}

	/**
	 * Get module by action.
	 *
	 * @param string $action       Action of module.
	 * @return CModule|null
	 */
	public function getModuleByAction($action) {
		$module = null;

		foreach ($this->modules as $module) {
			if ($module['instance'] && array_key_exists('actions', $module['manifest'])
					&& array_key_exists($action, $module['manifest']['actions'])) {
				return $module['instance'];
			}
		}

		return $module;
	}

	/**
	 * Create instance of module and call init for enabled module.
	 *
	 * @param string $moduleid      Module unique identifier.
	 * @param array  $config        Module configuration array, will be passed to init method.
	 */
	public function initModule($moduleid, array $config) {
		$module = $this->modules[$moduleid];
		$main_class = '\\CModule';
		$module_class = $module['path']['module']
			? 'Modules\\'.$module['manifest']['namespace'].'\\Module'
			: $main_class;

		try {
			$instance = new $module_class($module['manifest']);

			if (!($instance instanceof CModule) || is_null($instance->getManifest())) {
				throw new Exception(_s('%s class must extend %s class.', $module_class, $main_class));
			}

			if ($this->modules[$moduleid]['status']) {
				$instance->init($config);
			}

			$this->modules[$moduleid]['instance'] = $instance;
		}
		catch (Exception $e) {
			//$this->disable($moduleid);
			$this->modules[$moduleid]['errors'][] = $e;
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

		foreach ($this->modules as $namespace => $module) {
			if ($module['status']) {
				$namespace = 'Modules\\'.$namespace.'\\Actions\\';

				foreach ($module['manifest']['actions'] as $action => $data) {
					$routes[$action] = [
						'class' => $namespace.$data['class']
					] + $default + $data;
				}
			}
		}

		return $routes;
	}

	/**
	 * Get module directory absolute path.
	 *
	 * @param string $moduleid      Module unique identifier.
	 * @return string
	 */
	public function getModuleRootDir($moduleid) {
		return $this->modules[$moduleid]['path']['root'];
	}

	/**
	 * Get module init errors. Return array where key is module namespace and value is array of error string messages.
	 *
	 * @return array
	 */
	public function getErrors() {
		$errors = [];

		foreach ($this->modules as $namespace => $module_details) {
			if ($module_details['errors']) {
				$errors[$namespace] = [];

				foreach ($module_details['errors'] as $error) {
					$errors[$namespace][] = $error->getMessage();
				}
			}
		}

		return $errors;
	}
}
