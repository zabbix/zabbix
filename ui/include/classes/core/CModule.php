<?php declare(strict_types = 1);
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


namespace Core;

use CController as CAction;

/**
 * Base class for user modules. If Module.php is not provided by user module, this class will be instantiated instead.
 */
class CModule {

	/**
	 * Module directory path.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Module manifest.
	 *
	 * @var array
	 */
	private $manifest;

	/**
	 * @param string $dir       Module directory path.
	 * @param array  $manifest  Module manifest.
	 */
	public function __construct(string $dir, array $manifest) {
		$this->dir = $dir;
		$this->manifest = $manifest;
	}

	/**
	 * Initialize module.
	 */
	public function init(): void {
	}

	/**
	 * Get module directory path.
	 *
	 * @return string
	 */
	final public function getDir(): string {
		return $this->dir;
	}

	/**
	 * Get module manifest.
	 *
	 * @return array
	 */
	final public function getManifest(): array {
		return $this->manifest;
	}

	/**
	 * Get module id.
	 *
	 * @return string
	 */
	final public function getId(): string {
		return $this->manifest['id'];
	}

	/**
	 * Get module namespace.
	 *
	 * @return string
	 */
	final public function getNamespace(): string {
		return $this->manifest['namespace'];
	}

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	final public function getVersion(): string {
		return $this->manifest['version'];
	}

	/**
	 * Get module actions.
	 *
	 * @return array
	 */
	final public function getActions(): array {
		return $this->manifest['actions'];
	}

	/**
	 * Get module configuration.
	 *
	 * @return array
	 */
	final public function getConfig(): array {
		return $this->manifest['config'];
	}

	/**
	 * Get module configuration option.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $default  Default value.
	 *
	 * @return mixed  Configuration option (if exists) or the $default value.
	 */
	final public function getOption(string $name = null, $default = null) {
		return array_key_exists($name, $this->manifest['config']) ? $this->manifest['config'][$name] : $default;
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
