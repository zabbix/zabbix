<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php

/**
 * The Zabbix autoloader class.
 */
class CZabbixAutoloader {

	/**
	 * An array of directories, where the autoloader will look for the classes.
	 *
	 * @var array
	 */
	protected $includeDirs = array();


	/**
	 * Attempts to find and load the given class in the current include directories.
	 *
	 * @param $className
	 *
	 * @return null
	 */
	public function load($className) {
		foreach ($this->includeDirs as $dir) {
			if ($this->requireClass($className, $dir)) {
				return;
			}
		}
	}


	/**
	 * Adds an array of include directories to the current $includeDirs array.
	 *
	 * @param array $dirs
	 * @param string $prefix
	 */
	public function addIncludeDirs(array $dirs, $prefix = '') {
		foreach ($dirs as $path) {
			$this->includeDirs[] = $prefix.$path;
		}
	}


	/**
	 * Attempts to load the class $className from the directory $dir.
	 *
	 * @param $className
	 * @param $dir
	 *
	 * @return bool
	 */
	protected function requireClass($className, $dir) {
		// try the new file name first
		$path = $dir.'/'.$className.'.php';
		if (!is_file($path)) {
			// fallback to the old file name
			$path = $dir.'/class.'.strtolower($className).'.php';
			if (!is_file($path)) {
				return false;
			}
		}

		require_once $path;

		return true;
	}

}
