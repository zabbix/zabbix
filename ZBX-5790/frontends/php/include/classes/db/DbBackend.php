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


/**
 * Abstract database backend class.
 */
abstract class DbBackend {

	protected $error;

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return boolean
	 */
	abstract protected function checkDbVersionTable();

	/**
	 * Check if connected database version matches with frontend version.
	 *
	 * @return bool
	 */
	public function checkDbVersion() {
		if (!$this->checkDbVersionTable()) {
			return false;
		}

		$version = DBfetch(DBselect('SELECT dv.mandatory,dv.optional FROM dbversion dv'));
		if ($version['mandatory'] != ZABBIX_DB_VERSION) {
			$this->setError(_s('The frontend does not match Zabbix database. Current database version (mandatory/optional): %d/%d. Required mandatory version: %d. Contact your system administrator.',
				$version['mandatory'], $version['optional'], ZABBIX_DB_VERSION));
			return false;
		}

		return true;
	}

	/**
	 * Set error string.
	 *
	 * @param string $error
	 */
	public function setError($error) {
		$this->error = $error;
	}

	/**
	 * Return error or null if no error occured.
	 *
	 * @return mixed
	 */
	public function getError() {
		return $this->error;
	}
}
