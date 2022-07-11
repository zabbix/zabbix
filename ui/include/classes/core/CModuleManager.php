<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


use Core\CModule,
	CController as CAction;

/**
 * Module manager class for testing and loading user modules.
 */
final class CModuleManager {

	/**
	 * Highest supported manifest version.
	 */
	const MAX_MANIFEST_VERSION = 1;

	/**
	 * Home path of modules.
	 *
	 * @var string
	 */
	private $modules_dir;

	/**
	 * Manifest data of added modules.
	 *
	 * @var array
	 */
	private $manifests = [];

	/**
	 * List of instantiated, initialized modules.
	 *
	 * @var array
	 */
	private $modules = [];

	/**
	 * List of errors caused by module initialization.
	 *
	 * @var array
	 */
	private $errors = [];

	/**
	 * @param string $modules_dir  Home path of modules.
	 */
	public function __construct(string $modules_dir) {
		$this->modules_dir = $modules_dir;
	}

	/**
	 * Get home path of modules.
	 *
	 * @return string
	 */
	public function getModulesDir(): string {
		return $this->modules_dir;
	}

	/**
	 * Add module and prepare it's manifest data.
	 *
	 * @param string      $relative_path  Relative path to the module.
	 * @param string      $id             Stored module ID to optionally check the manifest module ID against.
	 * @param array|null  $config         Override configuration to use instead of one stored in the manifest file.
	 *
	 * @return array|null  Either manifest data or null if manifest file had errors or IDs didn't match.
	 */
	public function addModule(string $relative_path, string $id = null, array $config = null): ?array {
		$manifest = $this->loadManifest($relative_path);

		// Ignore module without a valid manifest.
		if ($manifest === null) {
			return null;
		}

		// Ignore module with an unexpected id.
		if ($id !== null && $manifest['id'] !== $id) {
			return null;
		}

		// Use override configuration, if supplied.
		if (is_array($config)) {
			$manifest['config'] = $config;
		}

		$this->manifests[$relative_path] = $manifest;

		return $manifest;
	}

	/**
	 * Get namespaces of all added modules.
	 *
	 * @return array
	 */
	public function getNamespaces(): array {
		$namespaces = [];

		foreach ($this->manifests as $relative_path => $manifest) {
			$module_path = $this->modules_dir.'/'.$relative_path;
			$namespaces['Modules\\'.$manifest['namespace']] = [$module_path];
		}

		return $namespaces;
	}

	/**
	 * Check added modules for conflicts.
	 *
	 * @return array  Lists of conflicts and conflicting modules.
	 */
	public function checkConflicts(): array {
		$ids = [];
		$namespaces = [];
		$actions = [];

		foreach ($this->manifests as $relative_path => $manifest) {
			$ids[$manifest['id']][] = $relative_path;
			$namespaces[$manifest['namespace']][] = $relative_path;
			foreach (array_keys($manifest['actions']) as $action_name) {
				$actions[$action_name][] = $relative_path;
			}
		}

		foreach (['ids', 'namespaces', 'actions'] as $var) {
			$$var = array_filter($$var, function($list) {
				return count($list) > 1;
			});
		}

		$conflicts = [];
		$conflicting_manifests = [];

		foreach ($ids as $id => $relative_paths) {
			$conflicts[] = _s('Identical ID (%1$s) is used by modules located at %2$s.', $id,
				implode(', ', $relative_paths)
			);
			$conflicting_manifests = array_merge($conflicting_manifests, $relative_paths);
		}

		foreach ($namespaces as $namespace => $relative_paths) {
			$conflicts[] = _s('Identical namespace (%1$s) is used by modules located at %2$s.', $namespace,
				implode(', ', $relative_paths)
			);
			$conflicting_manifests = array_merge($conflicting_manifests, $relative_paths);
		}

		$relative_paths = array_unique(array_reduce($actions, function($carry, $item) {
			return array_merge($carry, $item);
		}, []));

		if ($relative_paths) {
			$conflicts[] = _s('Identical actions are used by modules located at %1$s.', implode(', ', $relative_paths));
			$conflicting_manifests = array_merge($conflicting_manifests, $relative_paths);
		}

		return [
			'conflicts' => $conflicts,
			'conflicting_manifests' => array_unique($conflicting_manifests)
		];
	}

	/**
	 * Check, instantiate and initialize all added modules.
	 *
	 * @return array  List of initialized modules.
	 */
	public function initModules(): array {
		[
			'conflicts' => $this->errors,
			'conflicting_manifests' => $conflicting_manifests
		] = $this->checkConflicts();

		$non_conflicting_manifests = array_diff_key($this->manifests, array_flip($conflicting_manifests));

		foreach ($non_conflicting_manifests as $relative_path => $manifest) {
			$path = $this->modules_dir.'/'.$relative_path;

			if (is_file($path.'/Module.php')) {
				$module_class = implode('\\', ['Modules', $manifest['namespace'], 'Module']);

				if (!class_exists($module_class, true)) {
					$this->errors[] = _s('Wrong Module.php class name for module located at %1$s.', $relative_path);

					continue;
				}
			}
			else {
				$module_class = CModule::class;
			}

			try {
				/** @var CModule $instance */
				$instance = new $module_class($path, $manifest);

				if ($instance instanceof CModule) {
					$instance->init();

					$this->modules[$instance->getId()] = $instance;
				}
				else {
					$this->errors[] = _s('Module.php class must extend %1$s for module located at %2$s.',
						CModule::class, $relative_path
					);
				}
			}
			catch (Exception $e) {
				$this->errors[] = _s('%1$s - thrown by module located at %2$s.', $e->getMessage(), $relative_path);
			}
		}

		return $this->modules;
	}

	/**
	 * Get add initialized modules.
	 *
	 * @return array
	 */
	public function getModules(): array {
		return $this->modules;
	}

	/**
	 * Get loaded module instance associated with given action name.
	 *
	 * @param string $action_name
	 *
	 * @return CModule|null
	 */
	public function getModuleByActionName(string $action_name): ?CModule {
		foreach ($this->modules as $module) {
			if (array_key_exists($action_name, $module->getActions())) {
				return $module;
			}
		}

		return null;
	}

	/**
	 * Get actions of all initialized modules.
	 *
	 * @return array
	 */
	public function getActions(): array {
		$actions = [];

		foreach ($this->modules as $module) {
			foreach ($module->getActions() as $name => $data) {
				$actions[$name] = [
					'class' => implode('\\', ['Modules', $module->getNamespace(), 'Actions',
						str_replace('/', '\\', $data['class'])
					]),
					'layout' => array_key_exists('layout', $data) ? $data['layout'] : 'layout.htmlpage',
					'view' => array_key_exists('view', $data) ? $data['view'] : null
				];
			}
		}

		return $actions;
	}

	/**
	 * Publish an event to all loaded modules. The module of the responsible action will be served last.
	 *
	 * @param CAction $action  Action responsible for the current request.
	 * @param string  $event   Event to publish.
	 */
	public function publishEvent(CAction $action, string $event): void {
		$action_module = $this->getModuleByActionName($action->getAction());

		foreach ($this->modules as $module) {
			if ($module != $action_module) {
				$module->$event($action);
			}
		}

		if ($action_module) {
			$action_module->$event($action);
		}
	}

	/**
	 * Get errors encountered while module initialization.
	 *
	 * @return array
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	/**
	 * Load and parse module manifest file.
	 *
	 * @param string $relative_path  Relative path to the module.
	 *
	 * @return array|null  Either manifest data or null if manifest file had errors.
	 */
	private function loadManifest(string $relative_path): ?array {
		$module_path = $this->modules_dir.'/'.$relative_path;
		$manifest_file_name = $module_path.'/manifest.json';

		if (!is_file($manifest_file_name) || !is_readable($manifest_file_name)) {
			return null;
		}

		$manifest = file_get_contents($manifest_file_name);

		if ($manifest === false) {
			return null;
		}

		$manifest = json_decode($manifest, true);

		if (!is_array($manifest)) {
			return null;
		}

		// Check required keys in manifest.
		if (array_diff_key(array_flip(['manifest_version', 'id', 'name', 'namespace', 'version']), $manifest)) {
			return null;
		}

		// Check manifest version.
		if (!is_numeric($manifest['manifest_version']) || $manifest['manifest_version'] > self::MAX_MANIFEST_VERSION) {
			return null;
		}

		// Check manifest namespace syntax.
		if (!preg_match('/^[a-z_]+$/i', $manifest['namespace'])) {
			return null;
		}

		// Ensure empty defaults.
		$manifest += [
			'author' => '',
			'url' => '',
			'description' => '',
			'actions' => [],
			'config' => []
		];

		return $manifest;
	}
}
