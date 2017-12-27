<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	private $expanded = true;

	/**
	 * Sets the header and adds a default expand-collapse icon.
	 *
	 * @param string	$caption
	 * @param array		$controls
	 */
	public function setHeader($caption, array $controls = [], $cursor_move = false, $url = '') {
		$icon = (new CRedirectButton(null, null))
			->setId($this->id.'_icon')
			->onClick('changeWidgetState(this, "'.$this->id.'", "'.$url.'");');
		if ($this->expanded) {
			$icon->addClass(ZBX_STYLE_BTN_WIDGET_COLLAPSE)
				->setTitle(_('Collapse'));
		}
		else {
			$icon->addClass(ZBX_STYLE_BTN_WIDGET_EXPAND)
				->setTitle(_('Expand'));
		}
		$controls[] = $icon;

		parent::setHeader($caption, $controls, $cursor_move);

		return $this;
	}

	/**
	 * Display the widget in expanded or collapsed state.
	 */
	protected function build() {
		$body = (new CDiv($this->body))
			->addClass('body')
			->setId($this->id);

		if (!$this->expanded) {
			$body->setAttribute('style', 'display: none;');

			if ($this->footer) {
				$this->footer->setAttribute('style', 'display: none;');
			}
		}

		$this->cleanItems();
		$this->addItem($this->header);
		$this->addItem($body);
		$this->addItem($this->footer);

		return $this;
	}

	/**
	 * Sets expanded or collapsed state of the widget.
	 *
	 * @param bool
	 */
	public function setExpanded($expanded) {
		$this->expanded = $expanded;
		return $this;
	}
}
