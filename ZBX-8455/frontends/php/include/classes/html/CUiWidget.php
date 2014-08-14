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


class CUiWidget extends CDiv {

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
	protected $header;

	/**
	 * Body div.
	 *
	 * @var array
	 */
	protected $body;

	/**
	 * Footer div.
	 *
	 * @var CDiv
	 */
	protected $footer;

	/**
	 * Construct widget.
	 *
	 * @param string 			$id
	 * @param string|array|CTag $body
	 */
	public function __construct($id, $body = null) {
		$this->id = $id;
		$this->body = $body ? array($body) : array();

		parent::__construct(null, 'ui-widget ui-widget-content ui-helper-clearfix ui-corner-all widget ui-tabs');

		$this->setAttribute('id', $this->id.'_widget');
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

		$this->header->addItem(array($icons, $caption));
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

		$this->cleanItems();

		$this->addItem($this->header);
		$this->addItem($body);
		$this->addItem($this->footer);
	}

	/**
	 * Get widget html.
	 */
	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
