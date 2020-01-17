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


declare(strict_types = 1);

namespace Core;

use \CController as CAction;

class CModule {

	/**
	 * Module directory path.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Module manifest.
	 *
	 * @var array
	 */
	private $manifest;

	/**
	 * @param string $path      Module directory path.
	 * @param array  $manifest  Module manifest.
	 */
	public function __construct(string $path, array $manifest) {
		$this->path = $path;
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
	final public function getPath(): string {
		return $this->path;
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
	 * Get module configuration options.
	 *
	 * @param mixed $name     (optional) Option name.
	 * @param mixed $default  Default value.
	 *
	 * @return mixed  Either whole configuration or option specified.
	 */
	final public function getConfig($name = null, $default = null) {
		if ($name === null) {
			return $this->manifest['config'];
		}
		elseif (array_key_exists($name, $this->manifest['config'])) {
			return $this->manifest['config'][$name];
		}
		else {
			return $default;
		}
	}

	/**
	 * Event handler, triggered before executing the action.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onBeforeAction(CAction $action): void {
	}

	/**
	 * Event handler, triggered after executing the action, before rendering the view.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onAfterAction(CAction $action): void {
	}

	/**
	 * Event handler, triggered on application exit.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onTerminate(CAction $action): void {
	}
}
