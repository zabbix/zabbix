<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Trait for tags in form related tests.
 */
trait TagTrait {

	/**
	 * Get tag table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getTagTable() {
		return $this->query('id:tags-table')->asMultifieldTable([
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

	/**
	 * Fill tag with specified data.
	 *
	 * @param array $tags    data array where keys are fields label text and values are values to be put in fields
	 *
	 * @throws Exception
	 */
	public function fillTags($tags, $defaultAction = USER_ACTION_ADD) {
		foreach ($tags as &$tag) {
			$tag['action'] = CTestArrayHelper::get($tag, 'action', $defaultAction);
		}
		unset($tag);

		$this->getTagTable()->fill($tags);
	}

	/**
	 * Get input fields of tags.
	 *
	 * @return array
	 */
	public function getTags() {
		return $this->getTagTable()->getValue();
	}

	/**
	 * Check if values of tags inputs match data from data provider.
	 *
	 * @param array $data    tag element values
	 */
	public function assertTags($data) {
		$rows = [];
		foreach ($data as $i => $values) {
			$rows[$i] = [
				'name' => CTestArrayHelper::get($values, 'name', ''),
				'value' => CTestArrayHelper::get($values, 'value', ''),
			];
		}

		$this->assertEquals($rows, $this->getTags(), 'Tags on a page does not match tags in data provider.');
	}
}
