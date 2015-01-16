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


class CTable extends CTag {

	public $headerClass;
	public $footerClass;
	protected $oddRowClass;
	protected $evenRowClass;
	protected $header;
	protected $footer;
	protected $colnum;
	protected $rownum;
	protected $message;

	public function __construct($message = null, $class = null) {
		parent::__construct('table', 'yes');
		$this->attr('class', $class);
		$this->rownum = 0;
		$this->oddRowClass = null;
		$this->evenRowClass = null;
		$this->header = '';
		$this->headerClass = null;
		$this->footer = '';
		$this->footerClass = null;
		$this->colnum = 1;
		$this->message = $message;
	}

	public function setOddRowClass($value = null) {
		$this->oddRowClass = $value;
	}

	public function setEvenRowClass($value = null) {
		$this->evenRowClass = $value;
	}

	public function setAlign($value) {
		return $this->attributes['align'] = $value;
	}

	public function setCellPadding($value) {
		return $this->attributes['cellpadding'] = strval($value);
	}

	public function setCellSpacing($value) {
		return $this->attributes['cellspacing'] = strval($value);
	}

	public function prepareRow($item, $class = null, $id = null) {
		if (is_null($item)) {
			return null;
		}
		if (is_object($item) && zbx_strtolower(get_class($item)) == 'ccol') {
			if (isset($this->header) && !isset($item->attributes['colspan'])) {
				$item->attributes['colspan'] = $this->colnum;
			}
			$item = new CRow($item, $class, $id);
		}

		if (is_object($item) && zbx_strtolower(get_class($item)) == 'crow') {
			$item->attr('class', $class);
		}
		else {
			$item = new CRow($item, $class, $id);
		}

		if (!isset($item->attributes['class']) || is_array($item->attributes['class'])) {
			$class = ($this->rownum % 2) ? $this->oddRowClass : $this->evenRowClass;
			$item->attr('class', $class);
			$item->attr('origClass', $class);
		}
		return $item;
	}

	public function setHeader($value = null, $class = 'header') {
		if (isset($_REQUEST['print'])) {
			hide_form_items($value);
		}
		if (is_null($class)) {
			$class = $this->headerClass;
		}
		if (is_object($value) && zbx_strtolower(get_class($value)) == 'crow') {
			if (!is_null($class)) {
				$value->setAttribute('class', $class);
			}
		}
		else {
			$value = new CRow($value, $class);
		}
		$this->colnum = $value->itemsCount();
		$this->header = $value->toString();
	}

	public function setFooter($value = null, $class = 'footer') {
		if (isset($_REQUEST['print'])) {
			hide_form_items($value);
		}
		if (is_null($class)) {
			$class = $this->footerClass;
		}
		$this->footer = $this->prepareRow($value, $class);
		$this->footer = $this->footer->toString();
	}

	public function addRow($item, $class = null, $id = null) {
		$item = $this->addItem($this->prepareRow($item, $class, $id));
		++$this->rownum;
		return $item;
	}

	public function showRow($item, $class = null, $id = null) {
		echo $this->prepareRow($item, $class, $id)->toString();
		++$this->rownum;
	}

	public function getNumRows() {
		return $this->rownum;
	}

	public function startToString() {
		$ret = parent::startToString();
		$ret .= $this->header;
		return $ret;
	}

	public function endToString() {
		$ret = '';
		if ($this->rownum == 0 && isset($this->message)) {
			$ret = $this->prepareRow(new CCol($this->message, 'message'));
			$ret = $ret->toString();
		}
		$ret .= $this->footer;
		$ret .= parent::endToString();
		return $ret;
	}
}
