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


class CFilter extends CTag {

	private $filterid;
	private $columns = [];
	private $form;
	private $footer = null;
	private $navigator = false;
	private $name = 'zbx_filter';
	private $opened = true;

	public function __construct($filterid) {
		parent::__construct('div', true);
		$this->addClass('filter-container');
		$this->setId('filter-space');
		$this->filterid = $filterid;
		$this->columns = [];

		$this->form = (new CForm('get'))
			->setAttribute('name', $this->name)
			->setId('id', $this->name)
			->addVar('ddreset', 1)
			->addVar('uncheck', 1);

		// filter is opened by default
		$this->opened = (CProfile::get($this->filterid, 1) == 1);
	}

	public function getName() {
		return $this->name;
	}

	public function addColumn($column) {
		$this->columns[] = (new CDiv($column))->addClass('cell');
		return $this;
	}

	public function setFooter($footer) {
		$this->footer = $footer;
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
			$span->addClass('arrow-up');
			$button = (new CSimpleButton(
				[_('Filter'), $span]
			))
				->addClass('filter-trigger')
				->addClass('filter-active')
				->setId('filter-mode');
		}
		else {
			$span->addClass('arrow-down');
			$button = (new CSimpleButton(
				[_('Filter'), $span]
			))
				->addClass('filter-trigger')
				->setId('filter-mode');
			$this->setAttribute('style', 'display: none;');
		}

		$button->onClick('javascript:
			jQuery("#filter-space").toggle();
			jQuery("#filter-mode").toggleClass("filter-active");
			jQuery("#filter-arrow").toggleClass("arrow-up arrow-down");
			updateUserProfile("'.$this->filterid.'", jQuery("#filter-arrow").hasClass("arrow-down") ? 0 : 1);'
		);

		$switch = (new CDiv())
			->addClass('filter-btn-container')
			->addItem($button);

		return $switch;
	}

	private function getTable() {
		$row = (new CDiv())->addClass('row');
		foreach ($this->columns as $column) {
			$row->addItem($column);
		}
		$table = (new CDiv())->addClass('table filter-forms');

		$table->addItem($row);

		return $table;
	}

	private function getButtons() {
		if (count($this->columns) == 0) {
			return null;
		}

		$buttons = (new CDiv())->addClass('filter-forms');

		$url = new cUrl();
		$url->removeArgument('sid');
		$url->removeArgument('filter_set');
		$url->setArgument('filter_rst', 1);
		$resetButton = (new CRedirectButton(_('Reset'), $url->getUrl()))
			->addClass(ZBX_STYLE_BTN_ALT)
			->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();');

		$filterButton = (new CSubmit('filter_set', _('Filter')))
			->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();');

		$buttons->addItem($filterButton);
		$buttons->addItem($resetButton);

		return $buttons;
	}

	public function startToString() {
		$ret = $this->getHeader()->toString();
		$ret .= parent::startToString();
		return $ret;
	}

	public function endToString() {
		$this->form->addItem($this->getTable());

		$this->form->addItem($this->getButtons());

		if($this->navigator) {
			$this->form->addItem((new CDiv())->setId('scrollbar_cntr'));
		}
		if($this->footer !== null) {
			$this->form->addItem($this->footer);
		}

		$ret = $this->form->toString();

		$ret .= parent::endToString();
		return $ret;
	}
}
