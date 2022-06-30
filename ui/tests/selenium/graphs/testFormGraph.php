<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../common/testFormGraphs.php';

/**
 * backup graphs
 */
class testFormGraph extends testFormGraphs {

	const HOSTID = 40001; // Simple form test host.

	public $url = 'graphs.php?filter_set=1&filter_hostids%5B0%5D='.self::HOSTID.'&context=host';

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormGraph_Layout($data) {
		$this->checkGraphLayout($data);
	}

	/**
	 * @dataProvider getGraphData
	 */
	public function testFormGraph_Create($data) {
		$this->checkGraphForm($data);
	}
}
