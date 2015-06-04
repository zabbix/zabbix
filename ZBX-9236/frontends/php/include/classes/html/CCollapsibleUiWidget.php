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


/**
 * A class for rendering a widget that can be collapsed or expanded.
 */
class CCollapsibleUiWidget extends CUiWidget {

	/**
	 * Expand/collapse widget.
	 *
	 * Supported values:
	 * - true - expanded;
	 * - false - collapsed.
	 *
	 * @var bool
	 */
	public $open = true;

	/**
	 * Sets the header and adds a default expand-collapse icon.
	 *
	 * @param string|array|CTag $caption
	 * @param array              $icons
	 */
	public function setHeader($caption = null, array $icons = []) {
		$icon = new CRedirectButton(SPACE, null);
		if($this->open) {
			$icon->addClass(ZBX_STYLE_BTN_WIDGET_COLLAPSE);
			$icon->setTitle(_('Hide'));
		}
		else {
			$icon->addClass(ZBX_STYLE_BTN_WIDGET_EXPAND);
			$icon->setTitle(_('Open'));
		}
		$icon->onClick('changeWidgetState(this, "'.$this->id.'");');
		$icon->setAttribute('id', $this->id.'_icon');
		$icons[] = $icon;

		parent::setHeader($caption, $icons);
	}

	/**
	 * Display the widget in expanded or collapsed state.
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

		$this->addItem($this->header);
		$this->addItem($body);
		$this->addItem($this->footer);
	}
}
