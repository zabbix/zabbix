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


class CFilter extends CDiv {

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

	protected $id;
	protected $headers = [];
	protected $tabs = [];
	// jQuery.tabs disabled tabs list.
	protected $tabs_disabled = [];
	// jQuery.tabs initialization options.
	protected $tabs_options = [
		'collapsible' => true
	];

	public function __construct($filterid) {
		parent::__construct();

		$this->setId('filter-space');
		// TODO: check where id is used, selenium tests?!.
		$this->filterid = $filterid;

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
		throw new Exception(__FUNCTION__);
		$this->columns[] = (new CDiv($column))->addClass(ZBX_STYLE_CELL);
		return $this;
	}

	public function setFooter($footer) {
		throw new Exception(__FUNCTION__);
		$this->footer = $footer;
		return $this;
	}

	public function removeButtons() {
		throw new Exception(__FUNCTION__);
		$this->show_buttons = false;
		return $this;
	}

	public function addNavigator() {
		throw new Exception(__FUNCTION__);
		$this->navigator = true;
		return $this;
	}

	public function addVar($name, $value) {
		$this->form->addVar($name, $value);
		return $this;
	}

	public function setHidden() {
		throw new Exception(__FUNCTION__);
		$this->hidden = true;
		$this->addStyle('display: none;');

		return $this;
	}

	/**
	 * Add tab with filter form.
	 *
	 * @param string $header    Tab header title string.
	 * @param array  $columns   Array of filter columns markup.
	 *
	 * @return CFilter
	 */
	public function addFilterTab($header, $columns) {
		$body = [];
		$row = (new CDiv())->addClass(ZBX_STYLE_ROW);

		foreach ($columns as $column) {
			$row->addItem((new CDiv($column))->addClass(ZBX_STYLE_CELL));
		}

		$body[] = (new CDiv())
			->addClass(ZBX_STYLE_TABLE)
			->addClass(ZBX_STYLE_FILTER_FORMS)
			->addItem($row);

		$url = (new CUrl())
			->removeArgument('filter_set')
			->removeArgument('ddreset')
			->setArgument('filter_rst', 1);

		$body[] = (new CDiv())
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

		return $this->addTab($header, $body);
	}

	/**
	 * Add tab.
	 *
	 * @param string $header    Tab header title string.
	 * @param array  $body      Array of body elements.
	 *
	 * @return CFilter
	 */
	public function addTab($header, $body) {
		$this->headers[] = $header;
		$this->tabs[] = $body;

		return $this;
	}

	/**
	 * Add time selector specific tab. Should be called before any tab is added. Adds two tabs:
	 * - time selector range changes: back, zoom out, forward.
	 * - time selector range form with predefined ranges.
	 *
	 * @param string $header    Header text. (ex: Last 7 days)
	 *
	 * @return CFilter
	 */
	public function addTimeSelector($header) {
		$this->tabs_disabled[] = count($this->tabs);

		$this->addTab([
			(new CSimpleButton())->addClass('btn-time-left'),
			(new CSimpleButton(_('Zoom out')))->addClass('btn-time-out'),
			(new CSimpleButton())->addClass('btn-time-right')
		], null);

		$this->addTab((new CSimpleButton([$header, (new CSpan())->addClass('btn-time-icon')]))->addClass('btn-time'),
			(new CDiv())->setId('scrollbar_cntr')
		);

		return $this;
	}

	/**
	 * Return javascript code for jquery-ui initialization.
	 *
	 * @return string
	 */
	public function getJS() {
		return 'jQuery("#'.$this->getId().'").tabs('.
			CJs::encodeJson(array_merge($this->tabs_options, ['disabled' => $this->tabs_disabled]), true).
		')';
	}

	/**
	 * Render current CFilter object as HTML string.
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$headers = (new CList())->addClass(ZBX_STYLE_FILTER_BTN_CONTAINER);

		foreach ($this->headers as $index => $header) {
			$id = 'tab_'.$index;
			$headers->addItem(new CLink($header, '#'.$id));

			if ($this->tabs[$index] !== null) {
				$this->tabs[$index] = (new CDiv($this->tabs[$index]))
					->addClass(ZBX_STYLE_FILTER_CONTAINER)
					->setId($id);
			}
		}

		$this->form->addItem($this->tabs);

		$this->addItem($headers)
			->addItem($this->form);

		zbx_add_post_js($this->getJS());

		return parent::toString($destroy);
	}
}
