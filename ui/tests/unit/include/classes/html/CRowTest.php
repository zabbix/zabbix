<?php declare(strict_types = 0);
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


class CRowTest extends CTagTest {

	public function constructProvider() {
		return [
			// the row should render an empty <td> tag instead
			[
				[null],
				'<tr></tr>'
			],
			[
				[''],
				'<tr><td></td></tr>'
			],
			[
				['test'],
				'<tr><td>test</td></tr>'
			],
			[
				[['one', 'two']],
				'<tr><td>one</td><td>two</td></tr>'
			],

			// null columns are not rendered
			[
				[['one', null]],
				'<tr><td>one</td></tr>'
			],

			[
				[new CCol('test')],
				'<tr><td>test</td></tr>'
			],
			[
				[[new CCol('one'), new CCol('two')]],
				'<tr><td>one</td><td>two</td></tr>'
			],

			[
				['', 'myclass'],
				'<tr class="myclass"><td></td></tr>'
			],
			[
				['', null, 'myid'],
				'<tr id="myid"><td></td></tr>'
			]
		];
	}

	public function createTag($items = null, $class = null, $id = null) {
		return (new CRow($items))
			->addClass($class)
			->setId($id);
	}
}
