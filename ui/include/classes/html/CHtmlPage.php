<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CHtmlPage {

	public const PAGE_TITLE_ID = 'page-title-general';

	private const ZBX_STYLE_HEADER_TITLE = 'header-title';
	private const ZBX_STYLE_HEADER_DOC_LINK = 'header-doc-link';
	private const ZBX_STYLE_HEADER_NAVIGATION = 'header-navigation';
	private const ZBX_STYLE_HEADER_CONTROLS = 'header-controls';
	private const ZBX_STYLE_HEADER_KIOSKMODE_CONTROLS = 'header-kioskmode-controls';

	private string $title = '';
	private array $title_submenu = [];

	private ?CTag $controls = null;
	private ?CList $kiosk_mode_controls = null;

	private string $doc_url = '';

	private array $items = [];

	/**
	 * Navigation, displayed exclusively in ZBX_LAYOUT_NORMAL mode.
	 */
	private ?CList $navigation = null;

	/**
	 * Layout mode (ZBX_LAYOUT_NORMAL|ZBX_LAYOUT_KIOSKMODE).
	 */
	private int $web_layout_mode = ZBX_LAYOUT_NORMAL;

	public function setTitle(string $title): self {
		$this->title = $title;

		return $this;
	}

	public function setTitleSubmenu(array $title_submenu): self {
		$this->title_submenu = $title_submenu;

		return $this;
	}

	public function setDocUrl(string $doc_url): self {
		$this->doc_url = $doc_url;

		return $this;
	}

	public function setControls(?CTag $controls): self {
		$this->controls = $controls;

		return $this;
	}

	public function setKioskModeControls(?CList $kiosk_mode_controls): self {
		$this->kiosk_mode_controls = $kiosk_mode_controls;

		return $this;
	}

	public function setWebLayoutMode(int $web_layout_mode): self {
		$this->web_layout_mode = $web_layout_mode;

		return $this;
	}

	public function setNavigation(?CList $navigation): self {
		$this->navigation = $navigation;

		return $this;
	}

	public function addItem($value): self {
		if ($value !== null) {
			$this->items[] = $value;
		}

		return $this;
	}

	public function show(): self {
		echo $this->toString();

		return $this;
	}

	private function toString() {
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
		elseif ($this->title !== '' || $this->doc_url !== '' || $this->controls !== null) {
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

		$items[] = new CTag('main', true, [$navigation, $this->items]);

		return unpack_object($items);
	}

	private function createTopHeader(): CTag {
		$divs = [
			(new CTag('nav', true,
				(new CButtonIcon(ZBX_ICON_MENU, _('Show sidebar')))->setId('sidebar-button-toggle')
			))
				->addClass('sidebar-nav-toggle')
				->setAttribute('role', 'navigation')
				->setAttribute('aria-label', _('Sidebar control'))
		];

		if ($this->title !== '') {
			$title_tag = (new CTag('h1', true, $this->title))->setId(self::PAGE_TITLE_ID);

			if ($this->title_submenu !== []) {
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

		if ($this->doc_url !== '') {
			$divs[] = (new CDiv(
				(new CLink(null, $this->doc_url))
					->addClass(ZBX_STYLE_BTN_ICON)
					->addClass(ZBX_ICON_HELP)
					->setTitle(_('Help'))
					->setTarget('_blank')
			))->addClass(self::ZBX_STYLE_HEADER_DOC_LINK);
		}

		if ($this->controls !== null) {
			$divs[] = (new CDiv($this->controls))->addClass(self::ZBX_STYLE_HEADER_CONTROLS);
		}

		return (new CTag('header', true, $divs))->addClass(self::ZBX_STYLE_HEADER_TITLE);
	}
}
