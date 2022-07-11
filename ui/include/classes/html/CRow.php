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


class CRow extends CTag {

	protected $heading_column;
	protected $colspan = 0;

	/**
	 * @param CTag|array|null $item
	 * @param int|null $heading_column  Column index for heading column. Starts from 0. 'null' if no heading column.
	 */
	public function __construct($item = null, $heading_column = null) {
		parent::__construct('tr', true);
		$this->heading_column = $heading_column;
		$this->addItem($item);
	}

	/**
	 * Add row content.
	 *
	 * @param CTag|array $item  Column tag, column data or array with them.
	 *
	 * @return CRow
	 */
	public function addItem($item) {
		if ($item instanceof CCol) {
			$this->colspan += $item->getColSpan();
			parent::addItem($item);
		}
		elseif (is_array($item)) {
			foreach ($item as $el) {
				if ($el instanceof CCol) {
					$this->colspan += $el->getColSpan();
					parent::addItem($el);
				}
				elseif ($el !== null) {
					$col = $this->createCell($el);
					$this->colspan += $col->getColSpan();
					parent::addItem($col);
				}
			}
		}
		elseif ($item !== null) {
			$col = $this->createCell($item);
			$this->colspan += $col->getColSpan();
			parent::addItem($col);
		}

		return $this;
	}

	/**
	 * Create cell (td or th tag) with given content.
	 *
	 * @param CTag|array $el  Cell content.
	 *
	 * @return CCol
	 */
	protected function createCell($el) {
		return ($this->heading_column !== null && $this->itemsCount() == $this->heading_column)
			? (new CColHeader($el))
			: (new CCol($el));
	}

	/**
	 * Get total colspan count across all cells.
	 *
	 * @return int
	 */
	public function getColSpan(): int {
		return $this->colspan;
	}
}
