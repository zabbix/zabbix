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

/**
 * Trait for Macros in form related tests.
 */
trait MacrosTrait {

	protected $table_selector = 'id:tbl_macros';

	/**
	 * Set custom selector for macros table.
	 *
	 * @param string $selector    macros table selector
	 */
	public function setTableSelector($selector) {
		$this->table_selector = $selector;
	}

	/**
	 * Get macros table element with mapping set.
	 *
	 * @param string $value_column    value column header
	 *
	 * @return CMultifieldTable
	 */
	protected function getMacrosTable($value_column = 'Value') {
		return $this->query('id:tbl_macros')->asMultifieldTable([
			'mapping' => [
				'Macro' => [
					'name' => 'macro',
					'selector' => 'xpath:./textarea',
					'class' => 'CElement'
				],
				$value_column => [
					'name' => 'value',
					'selector' => 'xpath:./textarea',
					'class' => 'CElement'
				],
				'Description' => [
					'name' => 'description',
					'selector' => 'xpath:./textarea',
					'class' => 'CElement'
				]
			]
		])->one();
	}

	/**
	 * Fill macros fields  with specified data.
	 *
	 * @param array $macros    data array where keys are fields label text and values are values to be put in fields
	 *
	 * @throws Exception
	 */
	public function fillMacros($macros, $defaultAction = USER_ACTION_ADD) {
		foreach ($macros as &$macro) {
			$macro['action'] = CTestArrayHelper::get($macro, 'action', $defaultAction);
		}
		unset($macro);

		$this->getMacrosTable()->fill($macros);
	}

	/**
	 * Get input fields of macros.
	 *
	 * @return array
	 */
	public function getMacros() {
		return $this->getMacrosTable()->getValue();
	}

	/**
	 * Remove macros rows.
	 *
	 * @return $this
	 */
	public function removeMacros() {
		return $this->getMacrosTable()->clear();
	}

	/**
	 * Check if values of macros inputs match data from data provider.
	 *
	 * @param array $data    macros element values
	 */
	public function assertMacros($data = []) {
		$rows = [];
		foreach ($data as $values) {
			if (CTestArrayHelper::get($values, 'action') !== USER_ACTION_REMOVE) {
				$rows[] = [
					'macro' => CTestArrayHelper::get($values, 'macro', ''),
					'value' => CTestArrayHelper::get($values, 'value', ''),
					'description' => CTestArrayHelper::get($values, 'description', ''),
				];
			}
		}

		if (!$rows) {
			$rows[] = [
				'macro' => '',
				'value' => '',
				'description' => ''
			];
		}

		$this->assertEquals($rows, $this->getMacros(), 'Macros on a page does not match macros in data provider.');
	}
}
