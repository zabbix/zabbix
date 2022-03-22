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


class CWidget {

	private const ZBX_STYLE_HEADER_TITLE = 'header-title';
	private const ZBX_STYLE_HEADER_DOC_LINK = 'header-doc-link';
	private const ZBX_STYLE_HEADER_NAVIGATION = 'header-navigation';
	private const ZBX_STYLE_HEADER_CONTROLS = 'header-controls';
	private const ZBX_STYLE_HEADER_KIOSKMODE_CONTROLS = 'header-kioskmode-controls';

	private $title;
	private $title_submenu;
	private $doc_url;
	private $controls;
	private $kiosk_mode_controls;

	/**
	 * Navigation, displayed exclusively in ZBX_LAYOUT_NORMAL mode.
	 *
	 * @var mixed
	 */
	private $navigation;

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

	public function setDocUrl($doc_url) {
		$this->doc_url = $doc_url;

		return $this;
	}

	public function setControls($controls) {
		$this->controls = $controls;

		return $this;
	}

	public function setKioskModeControls($kiosk_mode_controls) {
		$this->kiosk_mode_controls = $kiosk_mode_controls;

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

	/**
	 * Set navigation for displaying exclusively in ZBX_LAYOUT_NORMAL mode.
	 *
	 * @param mixed $navigation
	 *
	 * @return CWidget
	 */
	public function setNavigation($navigation) {
		$this->navigation = $navigation;

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
				(new CList())
					->addClass(self::ZBX_STYLE_HEADER_KIOSKMODE_CONTROLS)
					->addItem($this->kiosk_mode_controls)
					->addItem(
						get_icon('kioskmode', ['mode' => ZBX_LAYOUT_KIOSKMODE])
							->setAttribute('aria-label', _('Content controls'))
					)
			);
		}
		elseif ($this->title !== null || $this->controls !== null || $this->doc_url !== null) {
			$items[] = $this->createTopHeader();
		}

		$items[] = get_prepared_messages([
			'with_auth_warning' => true,
			'with_session_messages' => true,
			'with_current_messages' => true
		]);

		$navigation = ($this->navigation !== null && $this->web_layout_mode == ZBX_LAYOUT_NORMAL)
			? (new CDiv($this->navigation))->addClass(self::ZBX_STYLE_HEADER_NAVIGATION)
			: null;

		$items[] = new CTag('main', true, [$navigation, $this->body]);

		return unpack_object($items);
	}

	private function createTopHeader(): CTag {
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

		if ($this->doc_url !== null) {
			$divs[] = (new CDiv(
				(new CLink(null, $this->doc_url))
					->setTitle(_('Help'))
					->setTarget('_blank')
					->addClass(ZBX_STYLE_ICON_DOC_LINK)
			))->addClass(self::ZBX_STYLE_HEADER_DOC_LINK);
		}

		if ($this->controls !== null) {
			$divs[] = (new CDiv($this->controls))->addClass(self::ZBX_STYLE_HEADER_CONTROLS);
		}

		return (new CTag('header', true, $divs))->addClass(self::ZBX_STYLE_HEADER_TITLE);
	}
}
