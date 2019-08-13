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
 * Trait for filter related tests.
 */
trait FilterTrait {

	/**
	 * Get tag table element with mapping set.
	 *
	 * @param type	  $selector	   tags table selector
	 *
	 * @return CMultifieldTable
	 */
	protected function getTagTable($selector = 'id:filter-tags') {
		return $this->query($selector)->asMultifieldTable([
			'mapping' => [
				[
					'name' => 'name',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				],
				[
					'name' => 'operator',
					'selector' => 'class:radio-list-control',
					'class' => 'CSegmentedRadioElement'
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
	 * Select type of tags. Add new tag, select tag operator, name and value.
	 *
	 * @param array   $tags        tag operator, name and value
	 * @param type	  $selector	   tags table selector
	 *
	 * @return CMultifieldTablelement
	 */
	public function setTags($tags, $selector = 'id:filter-tags') {
		foreach ($tags as $i => $tag) {
			if ($i === 0) {
				$this->getTagTable($selector)->updateRow($i, $tag);
			}
			else {
				$this->getTagTable($selector)->addRow($tag);
			}
		}

		return $this;
	}

	/**
	 * Get input fields of tags.
	 *
	 * @return array
	 */
	public function getTags($selector = 'id:filter-tags') {
		return $this->getTagTable($selector)->getValue();
	}

	/**
	 * Check if values of tags inputs match data from data provider.
	 *
	 * @param array $data    tag element values
	 */
	public function assertTags($data, $selector = 'id:filter-tags') {
		$rows = [];
		foreach ($data as $i => $values) {
			$rows[$i] = [
				'name' => CTestArrayHelper::get($values, 'name', ''),
				'value' => CTestArrayHelper::get($values, 'value', ''),
				'operator' => CTestArrayHelper::get($values, 'operator', 'Contains')
			];
		}

		$this->assertEquals($rows, $this->getTags($selector), 'Tags on a page does not match tags in data provider.');
	}
}
