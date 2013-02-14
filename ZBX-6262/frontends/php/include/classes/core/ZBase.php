<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


require_once dirname(__FILE__).'/CAutoloader.php';

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
	protected $rootDir;

	/**
	 * Session object.
	 *
	 * @var CSession
	 */
	protected $session;

	/**
	 * Returns the current instance of Z.
	 *
	 * @static
	 *
	 * @return Z
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new Z();
		}

		return self::$instance;
	}

	/**
	 * Initializes the application.
	 */
	public function run() {
		$this->rootDir = $this->findRootDir();

		$this->registerAutoloader();
	}

	/**
	 * Returns the absolute path to the root dir.
	 *
	 * @return string
	 */
	public static function getRootDir() {
		return self::getInstance()->rootDir;
	}

	/**
	 * Returns the path to the frontend's root dir.
	 *
	 * @return string
	 */
	private function findRootDir() {
		return realpath(dirname(__FILE__).'/../../..');
	}

	/**
	 * Register autoloader.
	 */
	private function registerAutoloader() {
		$autoloader = new CAutoloader($this->getIncludePaths());
		$autoloader->register();
	}

	/**
	 * An array of directories to add to the autoloader include paths.
	 *
	 * @return array
	 */
	private function getIncludePaths() {
		return array(
			$this->rootDir.'/include/classes',
			$this->rootDir.'/include/classes/core',
			$this->rootDir.'/include/classes/api',
			$this->rootDir.'/include/classes/db',
			$this->rootDir.'/include/classes/debug',
			$this->rootDir.'/include/classes/validators',
			$this->rootDir.'/include/classes/export',
			$this->rootDir.'/include/classes/export/writers',
			$this->rootDir.'/include/classes/export/elements',
			$this->rootDir.'/include/classes/import',
			$this->rootDir.'/include/classes/import/importers',
			$this->rootDir.'/include/classes/import/readers',
			$this->rootDir.'/include/classes/import/formatters',
			$this->rootDir.'/include/classes/screens',
			$this->rootDir.'/include/classes/sysmaps',
			$this->rootDir.'/include/classes/helpers',
			$this->rootDir.'/include/classes/helpers/trigger',
			$this->rootDir.'/include/classes/tree',
			$this->rootDir.'/include/classes/parsers',
			$this->rootDir.'/api/classes',
			$this->rootDir.'/api/rpc'
		);
	}

	/**
	 * An array of available themes.
	 *
	 * @return array
	 */
	public static function getThemes() {
		return array(
			'classic' => _('Classic'),
			'originalblue' => _('Original blue'),
			'darkblue' => _('Black & Blue'),
			'darkorange' => _('Dark orange')
		);
	}

	/**
	 * Return session object.
	 *
	 * @return CSession
	 */
	public function getSession() {
		if ($this->session === null) {
			$this->session = new CSession();
		}

		return $this->session;
	}
}
