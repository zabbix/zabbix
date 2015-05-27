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

	public function __construct($filterid) {
		parent::__construct('div', 'yes');
		$this->setAttribute('class', 'filter-container');
		$this->setAttribute('id', 'filter-space');
		$this->filterid = $filterid;
		$this->columns = [];

		$this->form = new CForm('get');
		$this->form->setAttribute('name', $this->name);
		$this->form->setAttribute('id', $this->name);
		$this->form->addVar('ddreset', 1);
		$this->form->addVar('uncheck', 1);

	}

	public function getName() {
		return $this->name;
	}

	public function addColumn($column) {
		$this->columns[] = new CDiv($column, 'cell');
	}

	public function setFooter($footer) {
		$this->footer = $footer;
	}

	public function addNavigator() {
		$this->navigator = true;
	}

	public function addVar($name, $value) {
		$this->form->addVar($name, $value);
	}

	private function getHeader() {
		$switch = new CDiv(null, 'filter-btn-container');
		$button = new CSimpleButton([_('Filter'), new CSpan(null, 'arrow-up', 'filter-arrow')], 'filter-trigger filter-active');
		$button->setAttribute('id', 'filter-mode');
		$button->onClick('javascript: jQuery("#filter-space").toggle(); jQuery("#filter-mode").toggleClass("filter-active"); jQuery("#filter-arrow").toggleClass("arrow-up arrow-down");');
		$switch->addItem($button);

		return $switch;
	}

	private function getTable() {
		$row = new CDiv(null, 'row');
		foreach ($this->columns as $column) {
			$row->addItem($column);
		}
		$table = new CDiv(null, 'table filter-forms');

		$table->addItem($row);

		return $table;
	}

	private function getButtons() {
		if (count($this->columns) == 0) {
			return null;
		}

		$buttons = new CDiv(null, 'filter-forms');

		$url = new cUrl();
		$url->removeArgument('sid');
		$url->removeArgument('filter_set');
		$url->setArgument('filter_rst', 1);
		$resetButton = new CRedirectButton(_('Reset'), $url->getUrl());
		$resetButton->addClass('btn-alt');
		$resetButton->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();');

		$filterButton = new CSubmit('filter_set', _('Filter'));
		$filterButton->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();');

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
			$this->form->addItem(new CDiv(null, null, 'scrollbar_cntr'));
		}
		if($this->footer !== null) {
			$this->form->addItem($this->footer);
		}

		$ret = $this->form->toString();

		$ret .= parent::endToString();
		return $ret;
	}
}
