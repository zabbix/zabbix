<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use CController as CAction;

use Zabbix\Core\{
	CModule,
	CWidget
};

/**
 * Module manager class for testing and loading user modules.
 */
final class CModuleManager {

	/**
	 * Lowest supported manifest version.
	 */
	private const MIN_MANIFEST_VERSION = 2;

	/**
	 * Highest supported manifest version.
	 */
	private const MAX_MANIFEST_VERSION = 2;

	/**
	 * Root path of modules.
	 */
	private string $root_path;

	/**
	 * Current action name.
	 */
	private string $action_name;

	/**
	 * Manifest data of added modules.
	 */
	private array $manifests = [];

	/**
	 * DB moduleids of added modules.
	 */
	private array $moduleids = [];

	/**
	 * List of instantiated, initialized modules.
	 */
	private array $modules = [];

	/**
	 * List of errors caused by module initialization.
	 */
	private array $errors = [];

	/**
	 * @param string $root_path  Root path of modules.
	 */
	public function __construct(string $root_path) {
		$this->root_path = $root_path;
	}

	/**
	 * Add module and prepare it's manifest data.
	 *
	 * @param string      $relative_path  Relative path to the module.
	 * @param string|null $moduleid       DB module ID.
	 * @param string|null $id             Stored module ID to optionally check the manifest module ID against.
	 * @param array|null  $config         Override configuration to use instead of one stored in the manifest file.
	 *
	 * @return array|null  Either manifest data or null if manifest file had errors or IDs didn't match.
	 */
	public function addModule(string $relative_path, ?string $moduleid = null, ?string $id = null,
			?array $config = null): ?array {

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
		$this->moduleids[$relative_path] = $moduleid;

		return $manifest;
	}

	public function initModules(): void {
		[
			'conflicts' => $this->errors,
			'conflicting_manifests' => $conflicting_manifests
		] = $this->checkConflicts();

		$non_conflicting_manifests = array_diff_key($this->manifests, array_flip($conflicting_manifests));

		foreach ($non_conflicting_manifests as $relative_path => $manifest) {
			$base_classname = $manifest['type'] === CModule::TYPE_WIDGET ? CWidget::class : CModule::class;
			$classname = $manifest['type'] === CModule::TYPE_WIDGET ? 'Widget' : 'Module';

			$module_class = $base_classname;

			try {
				if (is_file($this->root_path.'/'.$relative_path.'/'.$classname.'.php')) {
					$module_class = implode('\\', [$manifest['namespace'], $classname]);

					if (!class_exists($module_class)) {
						$this->errors[] = _s('Wrong %1$s.php class name for module located at %2$s.', $classname,
							$relative_path
						);

						return;
					}
				}

				/** @var CModule $instance */
				$instance = new $module_class($manifest, $this->moduleids[$relative_path], $relative_path);

				if ($instance instanceof $base_classname) {
					$instance->init();

					$this->modules[$instance->getId()] = $instance;
				}
				else {
					$this->errors[] = _s('%1$s.php class must extend %2$s for module located at %3$s.',
						$classname, $base_classname, $relative_path
					);
				}
			}
			catch (Throwable $e) {
				$this->errors[] = _s('%1$s - thrown by module located at %2$s.', $e->getMessage(), $relative_path);
			}
		}
	}

	/**
	 * Get initialized modules.
	 */
	public function getModules(): array {
		return $this->modules;
	}

	public function setActionName(string $action_name): self {
		$this->action_name = $action_name;

		return $this;
	}

	/**
	 * Get loaded module instance associated with action.
	 *
	 * @return CModule|null
	 */
	public function getActionModule(): ?CModule {
		/** @var CModule $module */
		foreach ($this->modules as $module) {
			if (array_key_exists($this->action_name, $module->getActions())) {
				return $module;
			}
		}

		return null;
	}

	/**
	 * Get initialized widget modules.
	 */
	public function getWidgets(): array {
		$widgets = [];

		/** @var CWidget $widget */
		foreach ($this->modules as $widget) {
			if (!($widget instanceof CWidget)) {
				continue;
			}
			$widgets[$widget->getId()] = $widget;
		}

		return $widgets;
	}

	public function getWidgetsDefaults(): array {
		$widget_defaults = [];

		/** @var CWidget $widget */
		foreach (APP::ModuleManager()->getWidgets() as $widget) {
			$widget_defaults[$widget->getId()] = $widget->getDefaults();
		}

		return $widget_defaults;
	}

	public function getModule($module_id): ?CModule {
		if (!array_key_exists($module_id, $this->modules)) {
			return null;
		}

		return $this->modules[$module_id];
	}

	public function getManifests(): array {
		return $this->manifests;
	}

	/**
	 * Get namespaces of all added modules.
	 */
	public function getNamespaces(): array {
		$namespaces = [];

		foreach ($this->manifests as $relative_path => $manifest) {
			$namespaces[$manifest['namespace']] = [$this->root_path.'/'.$relative_path];
		}

		return $namespaces;
	}

	/**
	 * Get actions of all initialized modules.
	 */
	public function getActions(): array {
		$actions = [];

		/** @var CModule $module */
		foreach ($this->modules as $module) {
			foreach ($module->getActions() as $name => $data) {
				$actions[$name] = [
					'class' => implode('\\', [$module->getNamespace(), 'Actions',
						str_replace('/', '\\', $data['class'])
					]),
					'layout' => array_key_exists('layout', $data) ? $data['layout'] : 'layout.htmlpage',
					'view' => array_key_exists('view', $data) ? $data['view'] : null
				];
			}
		}

		return $actions;
	}

	public function getAssets(): array {
		$assets = [];

		/** @var CModule $module */
		foreach ($this->modules as $module) {
			if ($module->getType() === CModule::TYPE_WIDGET && !CRouter::isDashboardAction($this->action_name)) {
				continue;
			}

			$assets[$module->getId()] = $module->getAssets();
		}

		return $assets;
	}

	/**
	 * Get errors encountered while module initialization.
	 */
	public function getErrors(): array {
		return $this->errors;
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
			$$var = array_filter($$var, static function($list) {
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

		$relative_paths = array_unique(array_reduce($actions, static function($carry, $item) {
			return array_merge($carry, $item);
		}, []));

		if ($relative_paths) {
			$conflicts[] = _s('Identical actions are used by modules located at %1$s.', implode(', ', $relative_paths));
			$conflicting_manifests = array_merge($conflicting_manifests, $relative_paths);
		}

		$this->errors = $conflicts;

		return [
			'conflicts' => $conflicts,
			'conflicting_manifests' => array_unique($conflicting_manifests)
		];
	}

	/**
	 * Publish an event to all loaded modules. The module of the responsible action will be served last.
	 *
	 * @param CAction $action  Action responsible for the current request.
	 * @param string  $event   Event to publish.
	 */
	public function publishEvent(CAction $action, string $event): void {
		$action_module = $this->getActionModule();

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
	 * Load and parse module manifest file.
	 *
	 * @param string $relative_path  Relative path to the module.
	 *
	 * @return array|null  Either manifest data or null if manifest file had errors.
	 */
	private function loadManifest(string $relative_path): ?array {
		$relative_path_parts = explode('/', $relative_path, 2);

		if (count($relative_path_parts) != 2) {
			return null;
		}

		$manifest_file_name = $this->root_path.'/'.$relative_path.'/manifest.json';

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
		if (!is_numeric($manifest['manifest_version']) || $manifest['manifest_version'] < self::MIN_MANIFEST_VERSION
				|| $manifest['manifest_version'] > self::MAX_MANIFEST_VERSION) {
			return null;
		}

		if (trim($manifest['id']) === '' || trim($manifest['name']) === '') {
			return null;
		}

		// Check manifest namespace syntax.
		if (!preg_match('/^[0-9a-z_]+$/i', $manifest['namespace'])) {
			return null;
		}

		$manifest['namespace'] = ucfirst($relative_path_parts[0]).'\\'.$manifest['namespace'];

		// Check module type.
		if (array_key_exists('type', $manifest)
				&& !in_array($manifest['type'], [CModule::TYPE_MODULE, CModule::TYPE_WIDGET], true)) {
			return null;
		}

		// Ensure empty defaults.
		$manifest += [
			'type' => CModule::TYPE_MODULE,
			'author' => '',
			'url' => '',
			'description' => '',
			'actions' => [],
			'assets' => [],
			'config' => []
		];

		$manifest['assets'] += [
			'css' => [],
			'js' => []
		];

		if ($manifest['type'] === CModule::TYPE_WIDGET) {
			if (!array_key_exists('widget', $manifest)) {
				$manifest['widget'] = [];
			}

			$manifest['widget'] += [
				'name' => '',
				'form_class' => CWidget::DEFAULT_FORM_CLASS,
				'js_class' => CWidget::DEFAULT_JS_CLASS,
				'in' => [],
				'out' => [],
				'size' => CWidget::DEFAULT_SIZE,
				'refresh_rate' => CWidget::DEFAULT_REFRESH_RATE
			];
		}

		return $manifest;
	}
}
