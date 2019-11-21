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
	 * array  [namespace]['errors']      module intialization errors, array of string.
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

			$dir = $path->getPathname();
			$manifest_path = $dir.'/manifest.json';
			$module_path = $dir.'/Module.php';
			$manifest = $this->parseManifestFile($manifest_path);

			if ($manifest) {
				$this->modules[$manifest['namespace']] = [
					'path' => [
						'root' => $dir,
						'manifest' => $manifest_path,
						'module' =>  file_exists($module_path) ? $module_path : ''
					],
					'manifest' => $manifest,
					'status' => false,
					'errors' => []
				];
			}
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

		if (!preg_match('/^[a-z_]+$/i', $manifest['namespace'])) {
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
	 * Create instance of enabled module and call init.
	 */
	public function initModules() {
		foreach ($this->modules as $namespace => &$module_details) {
			if ($module_details['status'] && $module_details['path']['module']) {
				$module_class = 'Modules\\'.$namespace.'\\Module';
				$manifest = $module_details['manifest'];
				try {
					$instance = new $module_class($manifest);
					$instance->init();
					$module_details['instance'] = $instance;
				}
				catch (Exception $e) {
					$module_details['errors'][] = $e;
				}
			}
		}
		unset($module_details);
	}

	/**
	 * Return actions only for modules with enabled status.
	 *
	 * @return array
	 */
	public function getModulesRoutes() {
		$routes = [];

		foreach ($this->modules as $namespace => $module) {
			if ($module['status']) {
				$default = [
					'layout' => 'layout.htmlpage',
					'view' => null
				];

				foreach ($module['manifest']['actions'] as $action => $data) {
					$routes[$action] = [
						'class' => 'Modules\\'.$namespace.'\\Actions\\'.$data['class']
					] + $default + $data;
				}
			}
		}

		return $routes;
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
