<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CUIWidget extends CDiv {

	public $domid;
	public $state;
	public $css_class;
	private $_header;
	private $_body;
	private $_footer;

	public function __construct($id, $body = null, $state = null) {
		$this->domid = $id;
		$this->state = $state; // 0 - closed, 1 - opened
		$this->css_class = 'header';
		$this->_header = null;
		$this->_body = array($body);
		$this->_footer = null;

		parent::__construct(null, 'ui-widget ui-widget-content ui-helper-clearfix ui-corner-all widget');
		$this->setAttribute('id', $id.'_widget');
	}

	public function addItem($item) {
		if (!is_null($item)) {
			$this->_body[] = $item;
		}
	}

	public function setHeader($caption = null, $icons = SPACE) {
		zbx_value2array($icons);
		if (is_null($caption) && !is_null($icons)) {
			$caption = SPACE;
		}
		$this->_header = new CDiv(null, 'nowrap ui-corner-all ui-widget-header '.$this->css_class);

		if (!is_null($this->state)) {
			$icon = new CIcon(
				_('Show').'/'._('Hide'),
				$this->state ? 'arrowup' : 'arrowdown',
				"changeHatStateUI(this,'".$this->domid."');"
			);
			$icon->setAttribute('id', $this->domid.'_icon');
			$this->_header->addItem($icon);
		}
		$this->_header->addItem($icons);
		$this->_header->addItem($caption);
		return $this->_header;
	}

	public function setDoubleHeader($left, $right) {
		$table = new CTable();
		$table->addStyle('width: 100%;');
		$lCol = new CCol($left);
		$lCol->addStyle('text-align: left; border: 0;');
		$rCol = new CCol($right);
		$rCol->addStyle('text-align: right; border: 0;');
		$table->addRow(array($lCol, $rCol));

		$this->_header = new CDiv(null, 'nowrap ui-corner-all ui-widget-header '.$this->css_class);
		$this->_header->addItem($table);
		return $this->_header;
	}

	public function setFooter($footer, $right = false) {
		$this->_footer = new CDiv($footer, 'nowrap ui-corner-all ui-widget-header footer '.($right ? ' right' : ' left'));
		return $this->_footer;
	}

	public function get() {
		$this->cleanItems();
		parent::addItem($this->_header);

		if (is_null($this->state)) {
			$this->state = true;
		}

		$div = new CDiv($this->_body, 'body');
		$div->setAttribute('id', $this->domid);

		if (!$this->state) {
			$div->setAttribute('style', 'display: none;');
			if ($this->_footer) {
				$this->_footer->setAttribute('style', 'display: none;');
			}
		}

		parent::addItem($div);
		parent::addItem($this->_footer);
		return $this;
	}

	public function toString($destroy = true) {
		$this->get();
		return parent::toString($destroy);
	}
}
