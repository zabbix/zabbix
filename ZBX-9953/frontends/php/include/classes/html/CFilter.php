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


class CFilter extends CTag {

	private $filterid;
	private $columns = [];
	private $form;
	private $footer = null;
	private $navigator = false;
	private $name = 'zbx_filter';
	private $opened = true;
	private $show_buttons = true;

	public function __construct($filterid) {
		parent::__construct('div', true);
		$this->addClass(ZBX_STYLE_FILTER_CONTAINER);
		$this->setId('filter-space');
		$this->filterid = $filterid;
		$this->columns = [];

		$this->form = (new CForm('get'))
			->cleanItems()
			->setAttribute('name', $this->name)
			->setId('id', $this->name);

		// filter is opened by default
		$this->opened = (CProfile::get($this->filterid, 1) == 1);
	}

	public function getName() {
		return $this->name;
	}

	public function addColumn($column) {
		$this->columns[] = (new CDiv($column))->addClass(ZBX_STYLE_CELL);
		return $this;
	}

	public function setFooter($footer) {
		$this->footer = $footer;
		return $this;
	}

	public function removeButtons() {
		$this->show_buttons = false;
		return $this;
	}

	public function addNavigator() {
		$this->navigator = true;
		return $this;
	}

	public function addVar($name, $value) {
		$this->form->addVar($name, $value);
		return $this;
	}

	private function getHeader() {
		$span = (new CSpan())->setId('filter-arrow');

		if ($this->opened) {
			$span->addClass(ZBX_STYLE_ARROW_UP);
			$button = (new CSimpleButton(
				[_('Filter'), $span]
			))
				->addClass(ZBX_STYLE_FILTER_TRIGGER)
				->addClass(ZBX_STYLE_FILTER_ACTIVE)
				->setId('filter-mode');
		}
		else {
			$span->addClass(ZBX_STYLE_ARROW_DOWN);
			$button = (new CSimpleButton(
				[_('Filter'), $span]
			))
				->addClass(ZBX_STYLE_FILTER_TRIGGER)
				->setId('filter-mode');
			$this->setAttribute('style', 'display: none;');
		}

		$button->onClick('javascript:
			jQuery("#filter-space").toggle();
			jQuery("#filter-mode").toggleClass("filter-active");
			jQuery("#filter-arrow").toggleClass("arrow-up arrow-down");
			updateUserProfile("'.$this->filterid.'", jQuery("#filter-arrow").hasClass("arrow-up") ? 1 : 0);
			if (jQuery(".multiselect").length > 0 && jQuery("#filter-arrow").hasClass("arrow-up")) {
				jQuery(".multiselect").multiSelect("resize");
			}'
		);

		$switch = (new CDiv())
			->addClass(ZBX_STYLE_FILTER_BTN_CONTAINER)
			->addItem($button);

		return $switch;
	}

	private function getTable() {
		if (!$this->columns) {
			return null;
		}

		$row = (new CDiv())->addClass(ZBX_STYLE_ROW);
		foreach ($this->columns as $column) {
			$row->addItem($column);
		}

		return (new CDiv())
			->addClass(ZBX_STYLE_TABLE)
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem($row);
	}

	private function getButtons() {
		if (!$this->columns) {
			return null;
		}

		$url = new CUrl();
		$url->removeArgument('filter_set');
		$url->removeArgument('ddreset');
		$url->setArgument('filter_rst', 1);

		return (new CDiv())
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem(
				(new CSubmit('filter_set', _('Filter')))
					->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
			)
			->addItem(
				(new CRedirectButton(_('Reset'), $url->getUrl()))
					->addClass(ZBX_STYLE_BTN_ALT)
					->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
			);

		return $buttons;
	}

	public function startToString() {
		$ret = $this->getHeader()->toString();
		$ret .= parent::startToString();
		return $ret;
	}

	public function endToString() {
		$this->form->addItem($this->getTable());

		if ($this->show_buttons) {
			$this->form->addItem($this->getButtons());
		}

		if ($this->navigator) {
			$this->form->addItem((new CDiv())->setId('scrollbar_cntr'));
		}

		if ($this->footer !== null) {
			$this->form->addItem($this->footer);
		}

		$ret = $this->form->toString();

		$ret .= parent::endToString();
		return $ret;
	}
}
