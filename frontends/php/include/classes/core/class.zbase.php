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

require_once dirname(__FILE__).'/class.czabbixautoloader.php';

class ZBase {

	/**
	 * An instance of the current Z object.
	 *
	 * @var Z
	 */
	protected static $instance;

	/**
	 * The absolute path to the root directory.
	 *
	 * @var string
	 */
	public $rootDir;


	/**
	 * Class constructor.
	 */
	public function __construct() {
		// set root dir
		$this->rootDir = dirname(__FILE__).'/../../../';
	}


	/**
	 * Returns the current instance of Z.
	 *
	 * @static
	 *
	 * @return Z
	 */
	public static function getInstance() {
		return self::$instance;
	}


	/**
	 * Initializes the application.
	 */
	public function run() {
		// register our autoloader
		$this->registerAutoloader(array($this->createAutoloader(), 'load'));
	}


	/**
	 * Returns the absolute path to the root dir.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function getRootDir() {
		return self::$instance->rootDir;
	}


	/**
	 * An array of directories to add to the autoloader include paths. The paths must be given relative to the
	 * frontend root directory and start with a slash "/".
	 *
	 * @return array
	 */
	public function autoload() {
		return array(
			'/include/classes',
			'/include/classes/sysmaps',
			'/api/classes',
			'/api/rpc'
		);
	}


	/**
	 * Registers the callback as an autoloader.
	 *
	 * @param $callback
	 *
	 * @return bool
	 */
	public function registerAutoloader($callback) {
		return spl_autoload_register($callback);
	}


	/**
	 * Creates a new instance of Z.
	 *
	 * @static
	 *
	 * @return Z
	 */
	public static function createInstance() {
		self::$instance = new Z();

		return self::$instance;
	}


	/**
	 * @return CZabbixAutoloader
	 */
	protected function createAutoloader() {
		$autoloader = new CZabbixAutoloader();
		$autoloader->addIncludeDirs($this->autoload(), self::getRootDir());

		return $autoloader;
	}




}
