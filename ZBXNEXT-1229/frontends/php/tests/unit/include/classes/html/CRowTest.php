<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CRowTest extends CTagTest {

	public function constructProvider() {
		return array(
			// the row should render an empty <td> tag instead
			array(
				array(null),
				'<tr></tr>'
			),
			array(
				array(''),
				'<tr><td></td></tr>'
			),
			array(
				array('test'),
				'<tr><td>test</td></tr>'
			),
			array(
				array(array('one', 'two')),
				'<tr><td>one</td><td>two</td></tr>'
			),

			// null columns are not rendered
			array(
				array(array('one', null)),
				'<tr><td>one</td></tr>'
			),

			array(
				array(new CCol('test')),
				'<tr><td>test</td></tr>'
			),
			array(
				array(array(new CCol('one'), new CCol('two'))),
				'<tr><td>one</td><td>two</td></tr>'
			),

			array(
				array('', 'myclass'),
				'<tr class="myclass"><td></td></tr>'
			),
			array(
				array('', null, 'myid'),
				'<tr id="myid"><td></td></tr>'
			),
		);
	}

	public function createTag($items = null, $class = null, $id = null) {
		return new CRow($items, $class, $id);
	}
}
