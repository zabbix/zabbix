<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Table row element.
 */
class CTableRowElement extends CElement {
	/**
	 * Parent element
	 *
	 * @var CTableElement
	 */
	public $parent;

	/**
	 * @inheritdoc
	 */
	public function normalize() {
		if ($this->parent === null) {
			$this->parent = $this->parents('tag:table')->one();
		}
	}

	/**
	 * Get collection of row columns indexed by table headers.
	 *
	 * @return CElementCollection
	 */
	public function getColumns() {
		$headers = $this->parent->getHeadersText();
		$columns = [];

		foreach ($this->query('xpath:./td')->all() as $i => $column) {
			$columns[$headers[$i]] = $column;
		}

		return new CElementCollection($columns);
	}

	/**
	 * Get column by index or name.
	 *
	 * @param mixed $column    column index or name
	 *
	 * @return CElement
	 */
	public function getColumn($column) {
		$headers = $this->parent->getHeadersText();

		if (is_string($column)) {
			$column = array_search($column, $headers);
			if ($column === false) {
				return null;
			}

			$column++;
		}

		return $this->query('xpath:./td['.$column.']')->one();
	}

	/**
	 * Select table row.
	 * For tables with checkboxes.
	 *
	 * @return $this
	 */
	public function select() {
		$this->query('xpath:.//input[@type="checkbox"]')->asCheckbox()->one()->check();

		return $this;
	}

	/**
	 * Check if table row is selected.
	 * For tables with checkboxes.
	 *
	 * @return $this
	 */
	public function isSelected() {
		return $this->query('xpath:.//input[@type="checkbox"]')->one()->isSelected();
	}
}
