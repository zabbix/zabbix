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


namespace Zabbix\Core;

use API,
	CController as CAction;

/**
 * Base class for user modules. If Module.php is not provided by user module, this class will be instantiated instead.
 */
class CModule {

	public const TYPE_MODULE = 'module';
	public const TYPE_WIDGET = 'widget';

	private string $root_path;
	private string $relative_path;

	protected array $manifest;
	protected string $moduleid;

	public function __construct(string $root_path, string $relative_path, array $manifest, string $moduleid) {
		$this->root_path = $root_path;
		$this->relative_path = $relative_path;
		$this->manifest = $manifest;
		$this->moduleid = $moduleid;
	}

	public function init(): void {
	}

	public function getDir(): string {
		return $this->root_path.'/'.$this->relative_path;
	}

	public function getRelativePath(): string {
		return $this->relative_path;
	}

	public function getModuleId(): string {
		return $this->moduleid;
	}

	public function getManifest(): array {
		return $this->manifest;
	}

	public function getId(): string {
		return $this->manifest['id'];
	}

	public function getDefaultName(): string {
		return _($this->manifest['name']);
	}

	public function getNamespace(): string {
		return $this->manifest['namespace'];
	}

	public function getVersion(): string {
		return $this->manifest['version'];
	}

	public function getType(): string {
		return $this->manifest['type'];
	}

	public function getAuthor(): string {
		return $this->manifest['author'];
	}

	public function getUrl(): string {
		return $this->manifest['url'];
	}

	public function getDescription(): string {
		return $this->manifest['description'];
	}

	public function getActions(): array {
		return $this->manifest['actions'];
	}

	public function getAssets(): array {
		return $this->manifest['assets'];
	}

	public function getConfig(): array {
		return $this->manifest['config'];
	}

	public function setConfig(array $config): self {
		$this->manifest['config'] = $config;

		API::Module()->update([[
			'moduleid' => $this->moduleid,
			'config' => $config
		]]);

		return $this;
	}

	/**
	 * Get module configuration option.
	 *
	 * @param string|null $name     Option name.
	 * @param mixed       $default  Default value.
	 *
	 * @return mixed  Configuration option (if exists) or the $default value.
	 */
	public function getOption(string $name = null, $default = null) {
		return array_key_exists($name, $this->manifest['config']) ? $this->manifest['config'][$name] : $default;
	}

	public function getTranslationStrings(): array {
		return [];
	}

	/**
	 * Event handler, triggered before executing the action.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onBeforeAction(CAction $action): void {
	}

	/**
	 * Event handler, triggered on application exit.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onTerminate(CAction $action): void {
	}
}
