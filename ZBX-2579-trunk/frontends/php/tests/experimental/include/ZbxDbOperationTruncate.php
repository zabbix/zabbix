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


class ZbxDbOperationTruncate extends PHPUnit_Extensions_Database_Operation_Truncate {

	/**
	 * Add disabling of foreign keys for truncate operations in db tests.
	 *
	 * @param PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection
	 * @param PHPUnit_Extensions_Database_DataSet_IDataSet       $dataSet
	 */
	public function execute(
			PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection,
			PHPUnit_Extensions_Database_DataSet_IDataSet $dataSet) {
		$connection->getConnection()->query("SET @FAKE_PREV_foreign_key_checks = @@foreign_key_checks");
		$connection->getConnection()->query("SET foreign_key_checks = 0");
		parent::execute($connection, $dataSet);
		$connection->getConnection()->query("SET foreign_key_checks = @FAKE_PREV_foreign_key_checks");
	}
}
