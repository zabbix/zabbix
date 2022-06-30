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
class testFormGraphPrototype extends testFormGraphs {

	CONST LLDID = 133800; // testFormDiscoveryRule on Simple form test host.

	public $prototype = true;
	public $url = 'graphs.php?parent_discoveryid='.self::LLDID.'&context=host';


	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormGraphPrototype_Layout($data) {
		$this->checkGraphLayout($data);
	}

	/**
	 * @dataProvider getGraphData
	 */
	public function testFormGraphPrototype_Create($data) {
		$this->checkGraphForm($data);
	}
}
