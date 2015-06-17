<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CFormTable extends CForm {

	private $title;
	private $tableclass = 'formtable';
	protected $top_items = [];
	protected $center_items = [];
	protected $bottom_items = [];

	public function __construct($title = null, $action = null, $method = null, $enctype = null, $form_variable = null) {
		$method = is_null($method) ? 'post' : $method;
		parent::__construct($method, $action, $enctype);

		$this->setTitle($title);

		$form_variable = is_null($form_variable) ? 'form' : $form_variable;
		$this->addVar($form_variable, getRequest($form_variable, 1));

		$this->bottom_items = (new CCol(SPACE))
			->addClass('form_row_last')
			->setColSpan(2);
	}

	public function setName($value) {
		$this->setAttribute('name', $value);
		$this->setId(zbx_formatDomId($value));
		return $this;
	}

	public function setTitle($value = null) {
		$this->title = $value;
		return $this;
	}

	public function addRow($item1, $item2 = null, $class = null, $id = null) {
		if (is_object($item1) && strtolower(get_class($item1)) === 'crow') {
		}
		elseif (is_object($item1) && strtolower(get_class($item1)) === 'ctable') {
			$td = (new CCol($item1))
				->addClass('form_row_c')
				->setColSpan(2);
			$item1 = new CRow($td);
		}
		else {
			if (is_string($item1)) {
				$item1 = nbsp($item1);
			}
			if (empty($item1)) {
				$item1 = SPACE;
			}
			if (empty($item2)) {
				$item2 = SPACE;
			}

			$item1 = (new CRow(
				[
					(new CCol($item1))->addClass('form_row_l'),
					(new CCol($item2))->addClass('form_row_r')
				]))->addClass($class);
		}

		if ($id !== null) {
			$item1->setId(zbx_formatDomId($id));
		}
		array_push($this->center_items, $item1);
		return $this;
	}

	public function addSpanRow($value, $class = null) {
		if (is_null($value)) {
			$value = SPACE;
		}
		if (is_null($class)) {
			$class = 'form_row_c';
		}
		$col = (new CCol($value))
			->addClass($class)
			->setColSpan(2);
		array_push($this->center_items, new CRow($col));
		return $this;
	}

	public function addItemToBottomRow($value) {
		$this->bottom_items->addItem($value);
		return $this;
	}

	/**
	 * Sets the class for the table element.
	 *
	 * @param string $class
	 */
	public function setTableClass($class) {
		$this->tableclass = $class;
		return $this;
	}

	public function bodyToString() {
		$res = parent::bodyToString();
		$tbl = new CTable();
		$tbl->addClass($this->tableclass);
		$tbl->setCellSpacing(0);
		$tbl->setCellPadding(1);

		// add first row
		if (!is_null($this->title)) {
			$col = (new CCol())
				->addClass('form_row_first')
				->setColSpan(2);

			if (isset($this->title)) {
				$col->addItem($this->title);
			}
			$tbl->setHeader($col);
		}

		// add last row
		$tbl->setFooter($this->bottom_items);

		// add center rows
		foreach ($this->center_items as $item) {
			$tbl->addRow($item);
		}
		return $res.$tbl->toString();
	}
}
