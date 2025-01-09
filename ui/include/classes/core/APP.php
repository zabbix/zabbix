<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


if (version_compare(PHP_VERSION, '8.0.0', '<')) {
	echo sprintf('Minimum required PHP version is %1$s.', '8.0.0');
	exit;
}

require_once dirname(__FILE__).'/ZBase.php';

/**
 * A wrapper for the ZBase class.
 *
 * Feel free to modify and extend it to change the functionality of ZBase.
 */
class APP extends ZBase {

}
