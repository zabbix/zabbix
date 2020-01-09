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


namespace Core;

use CController as Action;

class CModule {

	private $enabled = true;

	private $manifest = null;

	public function __construct(array $manifest) {
		$this->manifest = $manifest;
	}

	/**
	 * Module initialization method.
	 */
	public function init() {
	}

	/**
	 * Getter for module manifest id.
	 *
	 * @return string
	 */
	final public function getId() {
		return $this->manifest['id'];
	}

	/**
	 * Getter for module manifest actions.
	 *
	 * @return array
	 */
	final public function getActions() {
		return $this->manifest['actions'];
	}

	/**
	 * Getter for module config option.
	 *
	 * @param string $name     Configuration option name.
	 * @param mixed  $default  Default value.
	 *
	 * @return mixed
	 */
	final public function getConfig($name, $default = null) {
		return array_key_exists($name, $this->manifest['config']) ? $this->manifest['config'][$name] : $default;
	}

	/**
	 * Getter for module manifest.
	 *
	 * @return array
	 */
	final public function getManifest() {
		return $this->manifest;
	}

	/**
	 * Getter for module namespace.
	 *
	 * @return string
	 */
	final public function getNamespace() {
		return $this->manifest['namespace'];
	}

	/**
	 * Get module directory path.
	 *
	 * @param bool $relative  Return relative or absolute path to module directory.
	 *
	 * @return string
	 */
	final public function getRootDir($relative = false) {
		$path = $this->manifest['path'];

		if ($relative && $path) {
			$path = substr($path, strlen($this->modules_dir) + 1);
		}

		return $path;
	}

	/**
	 * Get module runtime status.
	 *
	 * @return bool
	 */
	public function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Set module runtime enabled/disabled status.
	 *
	 * @param bool $enabled    Module enabled status value, true for enable.
	 *
	 * @return CModule
	 */
	public function setEnabled($enabled) {
		$this->enabled = $enabled;

		return $this;
	}

	/**
	 * Module before action event.
	 *
	 * @param Action $action  Action instance responsible for current request
	 */
	public function beforeAction(Action $action) {
	}

	/**
	 * Module method to be called before application will exit and send response to browser. Will be called only for
	 * module responsible for current request.
	 *
	 * @param Action $action  Action instance responsible for current request.
	 */
	public function beforeTerminate(Action $action) {
	}
}
