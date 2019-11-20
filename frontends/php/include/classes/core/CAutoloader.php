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


/**
 * The Zabbix autoloader class.
 */
class CAutoloader {
	/**
	 * Autoloader root directory will be prepended to any registered relative path.
	 *
	 * @var string
	 */
	protected $root_dir;

	/**
	 * Registered namespace array. Key is namespace name with trailing '\' and value is array of directories
	 * relative path with trailing '/'.
	 *
	 * @var array
	 */
	protected $namespaces = [];

	/**
	 * Initializes object with array of include paths.
	 *
	 * @param string $root_dir          web root directory
	 */
	public function __construct($root_dir) {
		$this->root_dir = $root_dir;
	}

	/**
	 * Register supported namespace.
	 *
	 * @param string $prefix      Namespace value, should not have '\' as last character.
	 * @param array  $paths       Array of namespace files directory relative path, should not have '/' as last character.
	 */
	public function addNamespace($namespace, array $paths) {
		foreach ($paths as $path) {
			$this->namespaces[$namespace][] = realpath($this->root_dir.$path).'/';
		}
	}

	/**
	 * Add "loadClass" method as an autoload handler.
	 *
	 * @return bool
	 */
	public function register() {
		return spl_autoload_register([$this, 'loadClass']);
	}

	/**
	 * Attempts to find and load the given class.
	 *
	 * @param string $fq_class_name
	 */
	protected function loadClass($fq_class_name) {
		$chunks = explode('\\', $fq_class_name);
		$class_name = array_pop($chunks);
		$namespace = implode('\\', $chunks);

		if (array_key_exists($namespace, $this->namespaces)) {
			$file_name = $class_name.'.php';

			foreach ($this->namespaces[$namespace] as $dir) {
				if (is_file($dir.$file_name)) {
					require $dir.$file_name;

					return true;
				}
			}
		}

		return false;
	}
}
