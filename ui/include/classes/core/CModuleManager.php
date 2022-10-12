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
	public function addModule(string $relative_path, string $moduleid = null, string $id = null,
			array $config = null): ?array {

		$manifest = $this->loadManifest($relative_path);

		// Ignore module without a valid manifest.
		if ($manifest === null) {
			return null;
		}

		if ($moduleid !== null) {
			$manifest['moduleid'] = $moduleid;
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

	public function initModule($relative_path, $manifest): void {
		$base_classname = $manifest['type'] === CModule::TYPE_WIDGET ? CWidget::class : CModule::class;
		$classname = $manifest['type'] === CModule::TYPE_WIDGET ? 'Widget' : 'Module';

		$module_class = $base_classname;

		if (is_file($this->root_path.'/'.$relative_path.'/'.$classname.'.php')) {
			$module_class = implode('\\', [$manifest['root_namespace'], $manifest['namespace'], $classname]);

			if (!class_exists($module_class)) {
				$this->errors[] = _s('Wrong %1$s.php class name for module located at %2$s.', $classname,
					$relative_path
				);

				return;
			}
		}

		try {
			/** @var CModule $instance */
			$instance = new $module_class($this->root_path, $relative_path, $manifest);

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
		catch (Exception $e) {
			$this->errors[] = _s('%1$s - thrown by module located at %2$s.', $e->getMessage(), $relative_path);
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
	 * Get add initialized modules with type Widget.
	 */
	public function getWidgets(bool $for_template_dashboard_only = false): array {
		$widgets = [];

		/** @var CWidget $widget */
		foreach ($this->modules as $widget) {
			if (!($widget instanceof CWidget) || ($for_template_dashboard_only && !$widget->isSupportedInTemplate())) {
				continue;
			}
			$widgets[$widget->getId()] = $widget;
		}

		return $widgets;
	}

	public function getWidgetsDefaults(bool $for_template_dashboard_only = false): array {
		$widget_defaults = [];

		/** @var CWidget $widget */
		foreach (APP::ModuleManager()->getWidgets($for_template_dashboard_only) as $widget) {
			$widget_defaults[$widget->getId()] = $widget->getDefaults() + [
				'iterator' => false,
				'reference_field' => null,
				'foreign_reference_fields' => []
			];
		}

		return $widget_defaults;
	}

	public function getModule($module_id): ?CWidget {
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
			$module_path = $this->root_path.'/'.$relative_path;
			$namespaces[$manifest['root_namespace'].'\\'.$manifest['namespace']] = [$module_path];
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
				$action_class = implode('\\', [$module->getRootNamespace(), $module->getNamespace(), 'Actions',
					str_replace('/', '\\', $data['class'])
				]);

				if (!class_exists($action_class)) {
					$action_class = $data['class'];
				}

				$actions[$name] = [
					'class' => $action_class,
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

			$module_assets = $module->getAssets();
			$assets[$module->getId()] = [
				'css' => [],
				'js' => []
			];

			foreach ($module_assets['css'] as $css_file) {
				$assets[$module->getId()]['css'][] = $css_file;
			}

			foreach ($module_assets['js'] as $js_file) {
				$assets[$module->getId()]['js'][] = $js_file;
			}
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
		$module_path = $this->root_path.'/'.$relative_path;
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
		if (!preg_match('/^[0-9a-z_]+$/i', $manifest['namespace'])) {
			return null;
		}

		[$root_namespace] = explode('/', $relative_path, 2);
		$manifest['root_namespace'] = ucfirst($root_namespace);

		// Ensure empty defaults.
		$manifest += [
			'type' => CModule::TYPE_MODULE,
			'author' => '',
			'url' => '',
			'description' => '',
			'actions' => [],
			'assets' => [],
			'config' => [],
			'widget' => []
		];

		if ($manifest['type'] === CModule::TYPE_WIDGET) {
			$manifest['widget'] += [
				'name' => '',
				'form_class' => CWidget::DEFAULT_FORM_CLASS,
				'js_class' => CWidget::DEFAULT_JS_CLASS,
				'size' => CWidget::DEFAULT_SIZE,
				'refresh_rate' => CWidget::DEFAULT_REFRESH_RATE,
				'template_dashboard' => false,
				'use_time_selector' => false
			];
		}

		return $manifest;
	}
}
