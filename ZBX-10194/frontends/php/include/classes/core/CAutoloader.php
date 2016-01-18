<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	 * An array of directories, where the autoloader will look for the classes.
	 *
	 * @var array
	 */
	protected $includePaths = [];

	/**
	 * Initializes object with array of include paths.
	 *
	 * @param array $includePaths absolute paths
	 */
	public function __construct(array $includePaths) {
		$this->includePaths = $includePaths;
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
	 * @param $className
	 */
	protected function loadClass($className) {
		if ($classFile = $this->findClassFile($className)) {
			require $classFile;
		}
	}

	/**
	 * Attempts to find corresponding file for given class name in the current include directories.
	 *
	 * @param string $className
	 *
	 * @return bool|string
	 */
	protected function findClassFile($className) {
		foreach ($this->includePaths as $includePath) {
			$filePath = $includePath.'/'.$className.'.php';

			if (is_file($filePath)) {
				return $filePath;
			}
		}

		return false;
	}
}
