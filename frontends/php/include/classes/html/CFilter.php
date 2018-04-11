<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	private $hidden = false;
	private $timeselector_containerid;

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

		$this->timeselector_containerid = uniqid();
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

	public function setHidden() {
		$this->hidden = true;
		$this->addStyle('display: none;');

		return $this;
	}

	/**
	 * Return markup for filter toggler buttons.
	 *
	 * @return CDiv
	 */
	private function getHeader() {
		if (!$this->opened) {
			$this->setHidden();
		}

		$timeselector = null;
		$filter = null;

		if ($this->navigator) {
			$timeselector = [
				(new CSimpleButton())
					->addClass('btn-time-left')
					->setAttribute('data-event', 'timeselector.range.decrement'),// Set aria-label attribute
				(new CSimpleButton(_('Zoom out')))
					->addClass('btn-time-out')
					->setAttribute('data-event', 'timeselector.range.zoomout'),// Set aria-label attribute
				(new CSimpleButton())
					->addClass('btn-time-right')
					->setAttribute('data-event', 'timeselector.range.increment'),// Set aria-label attribute
				(new CSimpleButton([_('Last 7 days'), (new CSpan())->addClass('btn-time-icon')]))
					->addClass('btn-time')
					->setAttribute('data-event', 'timeselector.toggle')
					->setAttribute('data-filter-toggle', '#'.$this->timeselector_containerid)
					// Add separate profile for timeselector expanded/collapsed state.
					->setAttribute('data-profile', $this->filterid),// Set aria-label attribute
			];
		}

		if ($this->columns) {
			$filter = (new CSimpleButton([_('Filter'), new CSpan()]))
				->addClass(ZBX_STYLE_FILTER_TRIGGER)
				->addClass($this->opened ? ZBX_STYLE_FILTER_ACTIVE : '')
				->addClass('filter-arrow')
				->setAttribute('data-filter-toggle', '#'.$this->getId())
				->setAttribute('data-profile', $this->filterid);
		}

		return (new CDiv([
			$timeselector,
			$filter
		]))->addClass(ZBX_STYLE_FILTER_BTN_CONTAINER);
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

		$url = (new CUrl())
			->removeArgument('filter_set')
			->removeArgument('ddreset')
			->setArgument('filter_rst', 1);

		return (new CDiv())
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem(
				(new CSubmitButton(_('Apply'), 'filter_set', 1))
					->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
			)
			->addItem(
				(new CRedirectButton(_('Reset'), $url->getUrl()))
					->addClass(ZBX_STYLE_BTN_ALT)
					->onClick('javascript: chkbxRange.clearSelectedOnFilterChange();')
			);

		return $buttons;
	}

	protected function startToString() {
		$ret = ($this->hidden == false) ? $this->getHeader()->toString() : '';
		$ret .= parent::startToString();
		return $ret;
	}

	protected function endToString() {
		$this->form->addItem($this->getTable());

		if ($this->show_buttons) {
			$this->form->addItem($this->getButtons());
		}

		if ($this->footer !== null) {
			$this->form->addItem($this->footer);
		}

		$ret = $this->form->toString();

		if ($this->navigator) {
			$ret .= (new CForm('get'))
				->cleanItems()
				->setId($this->timeselector_containerid)
				->addItem(
					(new CDiv())->setId('scrollbar_cntr')
				);
		}

		$ret .= parent::endToString();
		return $ret;
	}
}
