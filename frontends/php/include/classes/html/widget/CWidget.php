<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	private $title_submenu = null;
	private $controls = null;

	/**
	 * The contents of the body of the widget.
	 *
	 * @var array
	 */
	protected $body = [];

	/**
	 * Layout mode (ZBX_LAYOUT_NORMAL|ZBX_LAYOUT_KIOSKMODE).
	 *
	 * @var integer
	 */
	protected $web_layout_mode = ZBX_LAYOUT_NORMAL;

	public function setTitle($title) {
		$this->title = $title;

		return $this;
	}

	public function setTitleSubmenu($title_submenu) {
		$this->title_submenu = $title_submenu;

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
		if ($breadcrumbs !== null && $this->web_layout_mode == ZBX_LAYOUT_NORMAL) {
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
		$items = [];

		if ($this->web_layout_mode == ZBX_LAYOUT_KIOSKMODE) {
			$this->addItem(
				get_icon('kioskmode', ['mode' => ZBX_LAYOUT_KIOSKMODE])
					->setAttribute('aria-label', _('Content controls'))
			);
		}
		elseif ($this->title !== null || $this->controls !== null) {
			$items[] = $this->createTopHeader();
		}

		$items[] = get_prepared_messages([
			'with_auth_warning' => true,
			'with_session_messages' => true,
			'with_current_messages' => true
		]);

		$items[] = new CTag('main', true, $this->body);

		return unpack_object($items);
	}

	private function createTopHeader() {
		$divs = [
			(new CTag('nav', true, (new CButton(null, _('Show sidebar')))
				->setId('sidebar-button-toggle')
				->addClass('button-toggle')
				->setAttribute('title', _('Show sidebar'))
			))
				->addClass('sidebar-nav-toggle')
				->setAttribute('role', 'navigation')
				->setAttribute('aria-label', _('Sidebar control'))
		];

		if ($this->title !== null) {
			$title_tag = (new CTag('h1', true, $this->title))->setId(ZBX_STYLE_PAGE_TITLE);

			if ($this->title_submenu) {
				$title_tag = (new CLinkAction($title_tag))
					->setMenuPopup([
						'type' => 'submenu',
						'data' => [
							'submenu' => $this->title_submenu
						],
						'options' => [
							'class' => ZBX_STYLE_PAGE_TITLE_SUBMENU
						]
					])
					->setAttribute('aria-label', _('Content controls: header'));
			}

			$divs[] = new CDiv($title_tag);
		}

		if ($this->controls !== null) {
			$divs[] = (new CDiv($this->controls))->addClass(ZBX_STYLE_HEADER_CONTROLS);
		}

		return (new CTag('header', true, $divs))->addClass(ZBX_STYLE_HEADER_TITLE);
	}
}
