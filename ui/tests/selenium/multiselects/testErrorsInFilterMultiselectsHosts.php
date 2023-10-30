<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testErrorsInFilterMultiselects.php';

/**
 * Test for assuring that bug from ZBX-23302 is not reproducing.
 */
class testErrorsInFilterMultiselectsHosts extends testErrorsInFilterMultiselects {

	protected $filter_labels = [
		'context_page' => ['Templates', 'Templates', 'Template groups'],
		'object_page' => ['Hosts', 'Hosts', 'Host groups']
	];

	/**
	 * @dataProvider getCheckDialogsData
	 */
	public function testErrorsInFilterMultiselectsHosts_CheckDialogs($data) {
		$this->checkErrorInDialog($data, 'zabbix.php?action=host.list', 'Host', 'Zabbix servers',
				'Template inheritance test host'
		);
	}
}
