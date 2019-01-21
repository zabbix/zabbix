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


class CWidget {

	private $title = null;
	private $controls = null;

	/**
	 * The contents of the body of the widget.
	 *
	 * @var array
	 */
	protected $body = [];

	/**
	 * Layout mode (ZBX_LAYOUT_NORMAL|ZBX_LAYOUT_FULLSCREEN|ZBX_LAYOUT_KIOSKMODE).
	 *
	 * @var integer
	 */
	protected $web_layout_mode = ZBX_LAYOUT_NORMAL;

	public function setTitle($title) {
		$this->title = $title;

		return $this;
	}

	public function setControls($controls) {
		zbx_value2array($controls);
		$this->controls = $controls;

		return $this;
	}

	/**
	 * Set layout mode.
	 *
	 * @param integer $web_layout_mode
	 *
	 * @return CWidget
	 */
	public function setWebLayoutMode($web_layout_mode) {
		$this->web_layout_mode = $web_layout_mode;

		return $this;
	}

	public function setBreadcrumbs($breadcrumbs = null) {
		if ($breadcrumbs !== null && in_array($this->web_layout_mode, [ZBX_LAYOUT_NORMAL, ZBX_LAYOUT_FULLSCREEN])) {
			$this->body[] = $breadcrumbs;
		}

		return $this;
	}

	public function addItem($items = null) {
		if (!is_null($items)) {
			$this->body[] = $items;
		}

		return $this;
	}

	public function show() {
		echo $this->toString();

		return $this;
	}

	public function toString() {
		$widget = [];

		if ($this->web_layout_mode === ZBX_LAYOUT_KIOSKMODE) {
			$this
				->addItem(get_icon('fullscreen')
				->setAttribute('aria-label', _('Content controls')));
		} elseif ($this->title !== null || $this->controls !== null) {
			$widget[] = $this->createTopHeader();
		}
		$tab = [$widget, $this->body];
		return unpack_object($tab);
	}

	private function createTopHeader() {
		$divs = [];

		if ($this->title !== null) {
			$divs[] = (new CDiv((new CTag('h1', true, $this->title))->setId(ZBX_STYLE_PAGE_TITLE)))
				->addClass(ZBX_STYLE_CELL);
		}

		if ($this->controls !== null) {
			$divs[] = (new CDiv($this->controls))
				->addClass(ZBX_STYLE_CELL)
				->addClass(ZBX_STYLE_NOWRAP);
		}

		return (new CDiv($divs))
			->addClass(ZBX_STYLE_HEADER_TITLE)
			->addClass(ZBX_STYLE_TABLE);
	}
}
