<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class CActionGoButtonGroup
 *
 * A simple extend of go button groups with action name set to "actions".
 */
class CActionGoButtonGroup extends CGoButtonGroup {

	/**
	 * @param string       $checkboxesName
	 * @param array        $buttonsData
	 * @param string|null  $cookieNamePrefix
	 */
	function __construct($checkboxesName, array $buttonsData, $cookieNamePrefix = null) {
		parent::__construct('action', $checkboxesName, $buttonsData, $cookieNamePrefix);
	}
}
