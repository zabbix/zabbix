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
		$this->body = $body ? [$body] : [];

		parent::__construct();

		$this->addClass(ZBX_STYLE_DASHBOARD_WIDGET);
		$this->setId($this->id.'_widget');
	}

	/**
	 * Set widget header.
	 *
	 * @param string $caption
	 * @param array  $controls
	 *
	 * @return $this
	 */
	public function setHeader($caption, array $controls = []) {
		$this->header = (new CDiv())
			->addClass(ZBX_STYLE_DASHBOARD_WIDGET_HEAD)
			->addItem(
				(new CTag('h4', true, $caption))->setId($this->id.'_header')
			);

		if ($controls) {
			$this->header->addItem(new CList($controls));
		}

		return $this;
	}

	/**
	 * Set widget footer.
	 *
	 * @param string|array|CTag $footer
	 * @param bool				$right
	 */
	public function setFooter($list) {
		$this->footer = $list;
		$this->footer->addClass(ZBX_STYLE_DASHBOARD_WIDGET_FOOT);
		return $this;
	}

	/**
	 * Build widget header, body and footer.
	 */
	protected function build() {
		$body = (new CDiv($this->body))
			->setId($this->id);

		$this->cleanItems();

		$this->addItem($this->header);
		$this->addItem($body);
		$this->addItem($this->footer);
		return $this;
	}

	/**
	 * Get widget html.
	 */
	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
