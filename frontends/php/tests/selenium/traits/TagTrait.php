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

define('USER_ACTION_ADD', 'add');
define('USER_ACTION_UPDATE', 'update');
define('USER_ACTION_REMOVE', 'remove');

/**
 * Trait for tags in form related tests.
 */
trait TagTrait {

	/**
	 * Add new tags, type tag name and value if exist.
	 *
	 * @param string $name    tag name
	 * @param string $value   tag value
	 */
	public function addTag($name, $value) {
		$table = $this->query('id:tags-table')->asTable()->one();
		$rows = $table->getRows()->count() - 1;
		$table->query('button:Add')->one()->click();

		// Wait until new table row appears.
		$table->query('xpath:.//tbody/tr['.($rows + 2).']')->waitUntilPresent();

		if ($name !== null || $value !== null) {
			$row = $table->getRow($rows);

			if ($name !== null) {
				$row->getColumn('Name')->children()->one()->fill($name);
			}
			if ($value !== null) {
				$row->getColumn('Value')->children()->one()->fill($value);
			}
		}
	}

	/**
	 * Update tag name and/or value by tag index.
	 *
	 * @param integer $index    tag number in list
	 * @param string $name      tag name after update
	 * @param string $value     tag name after update
	 */
	public function updateTag($index, $name, $value) {
		$row = $this->query('id:tags-table')->asTable()->one()->getRow($index);

		if ($name !== null) {
			$row->getColumn('Name')->children()->one()->clear()->fill($name);
		}

		if ($value !== null) {
			$row->getColumn('Value')->children()->one()->clear()->fill($value);
		}
	}

	/**
	 * Remove tag(s) by tag index.
	 *
	 * @param array $indexes    tag indexes
	 */
	public function removeTag($indexes) {
		if (!is_array($indexes)) {
			$indexes = [$indexes];
		}

		sort($indexes);

		foreach (array_reverse($indexes) as $index) {
			$row = $this->query('id:tags-table')->asTable()->one()->getRow($index);
			$row->getColumn('Action')->query('button:Remove')->one()->click();
		}
	}

	/**
	 * Find tag indexes by tag element.
	 *
	 * @param string $field     tag element
	 * @param string $value     tag name or value
	 * @return array
	 */
	protected function findByField($field, $value) {
		$index = [];
		$rows = $this->query('id:tags-table')->asTable()->one()->getRows()->slice(0, -1);

		foreach ($rows as $i => $row) {
			if ($row->getColumn($field)->children()->one()->getAttribute('value') === $value) {
				$index[] = $i;
			}
		}

		return $index;
	}

	/**
	 * Return tag index(es) by tag name.
	 */
	public function findByName($name) {
		return $this->findByField('Name', $name);
	}

	/**
	 * Return tag index(es) by tag value.
	 */
	public function findByValue($value) {
		return $this->findByField('Value', $value);
	}

	/**
	 * Fill tag with specified data.
	 *
	 * @param array $tags    data array where keys are fields label text and values are values to be put in fields
	 *
	 * @throws Exception
	 */
	public function fillTags($tags, $defaultAction = USER_ACTION_ADD) {
		foreach ($tags as $tag) {
			$action = array_key_exists('action', $tag) ? $tag['action'] : $defaultAction;

			switch ($action) {
				case USER_ACTION_ADD:
					$this->addTag(array_key_exists('name', $tag) ? $tag['name'] : null,
							array_key_exists('value', $tag) ? $tag['value'] : null
					);

					break;

				case USER_ACTION_UPDATE:
					if (!array_key_exists('index', $tag)) {
						throw new Exception('Tag index is not specified.');
					}

					$this->updateTag($tag['index'],
							array_key_exists('name', $tag) ? $tag['name'] : null,
							array_key_exists('value', $tag) ? $tag['value'] : null
					);

					break;

				case USER_ACTION_REMOVE:
					if (!array_key_exists('index', $tag)) {
						if (array_key_exists('name', $tag)) {
							$tag['index'] = $this->findByName($tag['name']);
						}
						elseif (array_key_exists('value', $tag)) {
							$tag['index'] = $this->findByValue($tag['value']);
						}
						else {
							throw new Exception('Tag index is not specified.');
						}
					}

					$this->removeTag($tag['index']);
					break;

				default:
					throw new Exception('Tag action is not specified.');
					break;
			}
		}
	}

	/**
	 * Get input fields of tags.
	 *
	 * @return array
	 */
	public function getTags() {
		$rows = $this->query('id:tags-table')->asTable()->one()->getRows()->slice(0, -1);

		$tags = [];
		foreach ($rows as $row) {
			$tags[] = [
				'name' => $row->getColumn('Name')->children()->one()->getAttribute('value'),
				'value' => $row->getColumn('Value')->children()->one()->getAttribute('value')
			];
		}

		return $tags;
	}

	/**
	 * Check if values of tags inputs match data from data provider.
	 *
	 * @param array $tags    tag element values
	 */
	public function assertTags($tags) {
		$table = $this->query('id:tags-table')->asTable()->one();
		$rows = $table->getRows()->slice(0, -1);

		$this->assertEquals(count($tags), $rows->count(), 'Tag count does not match tag count in data provider.');

		foreach ($rows as $i => $row) { // Slice rows to cut off Add button.
			if (array_key_exists('name', $tags[$i])) {
				$this->assertEquals($tags[$i]['name'],
						$row->getColumn('Name')->children()->one()->getAttribute('value')
				);
			}

			if (array_key_exists('value', $tags[$i])) {
				$this->assertEquals($tags[$i]['value'],
						$row->getColumn('Value')->children()->one()->getAttribute('value')
				);
			}
		}
	}
}
