<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/FormParametersTrait.php';

/**
 * Trait for tags in form related tests.
 */
trait TagTrait {

	use FormParametersTrait;

	protected $table_selector = 'id:tags-table';

	/**
	 * Set custom selector for table.
	 *
	 * @param string $selector    table selector
	 */
	public function setTableSelector($selector) {
		$this->table_selector = $selector;
	}

	/**
	 * Get table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getTable() {
		return $this->query($this->table_selector)->asMultifieldTable([
			'mapping' => [
				'Name' => [
					'name' => 'name',
					'selector' => 'xpath:./textarea',
					'class' => 'CElement'
				],
				'Value' => [
					'name' => 'value',
					'selector' => 'xpath:./textarea',
					'class' => 'CElement'
				]
			]
		])->one();
	}
}
