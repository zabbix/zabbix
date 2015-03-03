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
	private $columns = array();
	private $form;

	public function __construct($filterid) {
		parent::__construct('div', 'yes');
		$this->attr('class', 'table-forms-container');
		$this->attr('id', 'filter-space');
		$this->filterid = $filterid;
		$this->columns = array();

		$this->form = new CForm('get');
		$this->form->setAttribute('name', 'zbx_filter');
		$this->form->setAttribute('id', 'zbx_filter');
		$this->form->addVar('ddreset', 1);
		$this->form->addVar('uncheck', 1);

	}

	public function addColumn($column) {
		$this->columns[] = $column;
	}

	public function addVar($name, $value) {
		$this->form->addVar($name, $value);
	}

	private function getHeader() {
		$switch = new CDiv(null, 'filter-container');
		$button = new CSimpleButton(array(_('Filter'), new CSpan(null, 'arrow-up', 'filter-arrow')), 'filter-trigger filter-active');
		$button->setAttribute('id', 'filter-mode');
		$button->addAction('onclick', 'javascript: jQuery("#filter-space").toggle(); jQuery("#filter-mode").toggleClass("filter-active"); jQuery("#filter-arrow").toggleClass("arrow-up arrow-down");');
		$switch->addItem($button);

		return $switch;
	}

	private function getTable() {
		$row = new CDiv(null, 'row');
		foreach ($this->columns as $column) {
			$column->addClass('cell');
			$row->addItem($column);
		}
		$table = new CDiv(null, 'table filter-forms');

		$table->addItem($row);

		return $table;
	}

	private function getButtons() {
		$buttons = new CDiv(null, 'filter-forms');

		$url = new cUrl();
		$url->removeArgument('sid');
		$url->removeArgument('filter_set');
		$url->setArgument('filter_rst', 1);
		$resetButton = new CRedirectButton(_('Reset'), $url->getUrl());
		$resetButton->addAction('onclick', 'javascript: chkbxRange.clearSelectedOnFilterChange();');

		$filterButton = new CSubmit('filter_set', _('Filter'));
		$filterButton->addAction('onclick', 'javascript: chkbxRange.clearSelectedOnFilterChange();');

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

		$ret = $this->form->toString();

		$ret .= parent::endToString();
		return $ret;
	}
}
