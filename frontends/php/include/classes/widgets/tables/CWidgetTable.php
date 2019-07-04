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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Table generation widget.
 *
 * Class CWidgetTable
 */
abstract class CWidgetTable {
	/**
	 * Widget table.
	 *
	 * @var CTableInfo
	 */
	protected $table;

	/**
	 * Table data.
	 *
	 * @var array
	 */
	protected $table_data;

	/**
	 * Table row.
	 *
	 * @var CCol[]
	 */
	protected $row;

	public function __construct(array $table_data = []) {
		$this->table_data = $table_data;
	}

	/**
	 * Set Table data.
	 */
	protected function setData(array $table_data = []) {
		$this->table_data = $table_data;
	}

	/**
	 * Table initialization.
	 */
	protected function setTable(array $classes = null, array $options = null) {
		$this->table = new CTableInfo();

		if ($classes) {
			foreach ($classes as $class) {
				$this->table->addClass($class);
			}
		}

		if ($options) {
			foreach ($options as $key => $option) {
				$method = 'set'.$this->camelize($key);
				if (method_exists($this->table, $method)) {
					$this->table->$method($option);
				}
			}
		}
	}

	/**
	 * Get generated Widget table
	 *
	 * @return CTableInfo
	 */
	public function getTable() {
		return $this->table;
	}

	abstract protected function initRow(array $row_data);
	abstract protected function extractRowElements(array $row_data);
	abstract protected function rowFilter(array $row_data);
	abstract protected function cellFilter($cell_key, array $cell_data);
	abstract protected function getTableCell($cell_key, array $cell_data);

	/**
	 * Populate normal two dimensional Table.
	 */
	protected function populateNormalTable() {
		foreach ($this->table_data as $row_data) {
			if (!$this->rowFilter($row_data)) {
				continue;
			}
			$this->row = $this->initRow($row_data);
			$row_elements = $this->extractRowElements($row_data);

			foreach ($row_elements as $cell_key => $cell_data) {
				if (!$this->cellFilter($cell_key, $cell_data)) {
					continue;
				}
				$this->row[] = $this->getTableCell($cell_key, $cell_data);
			}
			$this->table->addRow($this->row);
		}
	}

	/**
	 * Populate vertical one column Total Table.
	 */
	protected function populateVerticalTotalTable() {
		foreach ($this->table_data as $row_data) {
			$row_elements = $this->extractRowElements($row_data);

			foreach ($row_elements as $cell_key => $cell_data) {
				if (!$this->cellFilter($cell_key, $cell_data)) {
					continue;
				}
				$cell = $this->getTableCell($cell_key, $cell_data);
				$this->table->addRow($cell);
			}
		}
	}

	/**
	 * Convert string to camel-case format ('abc_def_gh' -> 'AbcDefGh').
	 *
	 * @param string $str  Input string.
	 * @return string
	 */
	protected function camelize($str)
	{
		return strtr(ucwords(strtr(strtolower($str), ['_' => ' '])), [' ' => '']);
	}
}
