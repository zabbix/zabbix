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


use CController as CAction;

class CModule {

	protected $manifest = null;

	public function __construct(array $manifest) {
		$this->manifest = $manifest;
	}

	final public function getManifest() {
		return $this->manifest;
	}

	/**
	 * Module register action, called once module is regsitered in Zabbix. Returned array will be stored as initial
	 * 'config' data in moduledetails.config field.
	 *
	 * @param string $path  Relative path to module code.
	 *
	 * @return array
	 */
	public function register($path) {
		return [];
	}

	/**
	 * Module initalization method.
	 *
	 * @param array $config  Database stored config settings.
	 */
	public function init(array $config) {
	}

	/**
	 * Module before action event.
	 *
	 * @param CAction $action  Action instance responsible for current request
	 */
	public function beforeAction(CAction $action) {
	}

	/**
	 * Module method to be called before application will exit and send response to browser. Will be called only for
	 * module responsible for current request.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function beforeTerminate(CAction $action) {
	}
}
