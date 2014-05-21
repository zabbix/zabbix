<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	/**
	 * Widget id.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Expand/collapse widget.
	 *
	 * Supported values:
	 * - true - expanded;
	 * - false - collapsed.
	 *
	 * @var bool
	 */
	public $open;

	/**
	 * Header div.
	 *
	 * @var CDiv
	 */
	private $header;

	/**
	 * Body div.
	 *
	 * @var array
	 */
	private $body;

	/**
	 * Footer div.
	 *
	 * @var CDiv
	 */
	private $footer;

	/**
	 * Construct widget.
	 *
	 * @param string 			$id
	 * @param string|array|CTag $body
	 */
	public function __construct($id, $body = null) {
		$this->id = $id;
		$this->open = true;
		$this->header = null;
		$this->body = $body ? array($body) : array();
		$this->footer = null;

		parent::__construct(null, 'ui-widget ui-widget-content ui-helper-clearfix ui-corner-all widget');

		$this->setAttribute('id', $this->id.'_widget');
	}

	/**
	 * Add element to widget body.
	 *
	 * @param string|array|CTag $item
	 */
	public function addItem($item) {
		if ($item !== null) {
			$this->body[] = $item;
		}
	}

	/**
	 * Set widget header.
	 *
	 * @param string|array|CTag $caption
	 * @param string|array|CTag $icons
	 */
	public function setHeader($caption = null, $icons = SPACE) {
		zbx_value2array($icons);

		if ($caption === null && $icons !== null) {
			$caption = SPACE;
		}

		$this->header = new CDiv(null, 'nowrap ui-corner-all ui-widget-header header');

		$icon = new CIcon(
			_('Show').'/'._('Hide'),
			$this->open ? 'arrowup' : 'arrowdown',
			'changeWidgetState(this, "'.$this->id.'");'
		);
		$icon->setAttribute('id', $this->id.'_icon');

		$this->header->addItem(array($icon, $icons, $caption));
	}

	/**
	 * Set widget header with left and right parts.
	 *
	 * @param string|array|CTag $leftColumn
	 * @param string|array|CTag $rightColumn
	 */
	public function setDoubleHeader($leftColumn, $rightColumn) {
		$leftColumn = new CCol($leftColumn);
		$leftColumn->addStyle('text-align: left; border: 0;');

		$rightColumn = new CCol($rightColumn);
		$rightColumn->addStyle('text-align: right; border: 0;');

		$table = new CTable();
		$table->addStyle('width: 100%;');
		$table->addRow(array($leftColumn, $rightColumn));

		$this->header = new CDiv($table, 'nowrap ui-corner-all ui-widget-header header');
	}

	/**
	 * Set widget footer.
	 *
	 * @param string|array|CTag $footer
	 * @param bool				$right
	 */
	public function setFooter($footer, $right = false) {
		$this->footer = new CDiv($footer, 'nowrap ui-corner-all ui-widget-header footer '.($right ? ' right' : ' left'));
	}

	/**
	 * Build widget header, body and footer.
	 */
	public function build() {
		$body = new CDiv($this->body, 'body');
		$body->setAttribute('id', $this->id);

		if (!$this->open) {
			$body->setAttribute('style', 'display: none;');

			if ($this->footer) {
				$this->footer->setAttribute('style', 'display: none;');
			}
		}

		$this->cleanItems();

		parent::addItem($this->header);
		parent::addItem($body);
		parent::addItem($this->footer);
	}

	/**
	 * Get widget html.
	 */
	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
