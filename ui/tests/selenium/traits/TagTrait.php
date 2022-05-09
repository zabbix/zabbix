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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Trait for tag related tests.
 */
trait TagTrait {

	protected $tag_selector = 'id:filter-tags';

	/**
	 * Set custom selector for tag table.
	 *
	 * @param string $selector    tag table selector
	 */
	public function setTagSelector($selector) {
		$this->tag_selector = $selector;
	}

	/**
	 * Get tag table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getTagTable() {
		return $this->query($this->tag_selector)->asMultifieldTable([
			'mapping' => [
				[
					'name' => 'name',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				],
				[
					'name' => 'operator',
					'selector' => 'xpath:./z-select',
					'class' => 'CDropdownElement'
				],
				[
					'name' => 'value',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		])->one();
	}

	/**
	 * Add new tag, select tag operator, name and value.
	 *
	 * @param array   $tags   tag operator, name and value
	 *
	 * @return CMultifieldTablelement
	 */
	public function setTags($tags) {
		$table = $this->getTagTable();

		foreach ($tags as $i => $tag) {
			if ($i === 0) {
				$table->updateRow($i, $tag);
			}
			else {
				$table->addRow($tag);
			}
		}

		return $this;
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
		foreach ($data as $values) {
			$rows[] = [
				'name' => CTestArrayHelper::get($values, 'name', ''),
				'value' => CTestArrayHelper::get($values, 'value', ''),
				'operator' => CTestArrayHelper::get($values, 'operator', 'Contains')
			];
		}

		$this->assertEquals($rows, $this->getTags(), 'Tags on a page does not match tags in data provider.');
	}
}
