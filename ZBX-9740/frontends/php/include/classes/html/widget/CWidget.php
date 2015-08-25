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


class CWidget {

	public $flicker_state;
	private $css_class;
	private $pageHeaders;
	private $headers;
	private $flicker = array();

	/**
	 * The contents of the body of the widget.
	 *
	 * @var array
	 */
	protected $body = array();

	/**
	 * The class of the root div element.
	 *
	 * @var string
	 */
	protected $rootClass;

	/**
	 * The ID of the div, containing the body of the widget.
	 *
	 * @var string
	 */
	protected $bodyId;

	public function __construct($bodyId = null, $rootClass = null) {
		if (is_null($bodyId)) {
			list($usec, $sec) = explode(' ', microtime());
			$bodyId = 'widget_'.(int)($sec % 10).(int)($usec * 1000);
		}
		$this->bodyId = $bodyId;
		$this->flicker_state = 1; // 0 - closed, 1 - opened
		$this->css_class = 'header_wide';
		$this->setRootClass($rootClass);
	}

	public function setClass($class = null) {
		if (is_string($class)) {
			$this->css_class = $class;
		}
	}

	public function addPageHeader($left = SPACE, $right = SPACE) {
		zbx_value2array($right);

		$this->pageHeaders[] = array('left' => $left, 'right' => $right);
	}

	public function addHeader($left = SPACE, $right = SPACE) {
		zbx_value2array($right);

		$this->headers[] = array('left' => $left, 'right' => $right);
	}

	public function addHeaderRowNumber($right = SPACE) {
		$numRows = new CDiv();
		$numRows->setAttribute('name', 'numrows');
		$this->addHeader($numRows, $right);
	}

	public function addFlicker($items = null, $flickerState = false) {
		if (!is_null($items)) {
			$this->flicker[] = $items;
		}

		$this->flicker_state = $flickerState;
	}

	public function addItem($items = null) {
		if (!is_null($items)) {
			$this->body[] = $items;
		}
	}

	public function get() {
		$widget = array();
		if (!empty($this->pageHeaders)) {
			$widget[] = $this->createPageHeader();
		}
		if (!empty($this->headers)) {
			$widget[] = $this->createHeader();
		}
		if (!empty($this->flicker)) {
			$flicker_domid = 'flicker_'.$this->bodyId;
			$flicker_tab = new CTable();
			$flicker_tab->setAttribute('width', '100%');
			$flicker_tab->setCellPadding(0);
			$flicker_tab->setCellSpacing(0);

			$div = new CDiv($this->flicker, null, $flicker_domid);
			if (!$this->flicker_state) {
				$div->setAttribute('style', 'display: none;');
			}

			$icon_l = new CDiv(SPACE.SPACE, ($this->flicker_state ? 'dbl_arrow_up' : 'dbl_arrow_down'), 'flicker_icon_l');
			$icon_l->setAttribute('title', _('Maximize').'/'._('Minimize'));

			$icon_r = new CDiv(SPACE.SPACE, ($this->flicker_state ? 'dbl_arrow_up' : 'dbl_arrow_down'), 'flicker_icon_r');
			$icon_r->setAttribute('title', _('Maximize').'/'._('Minimize'));

			$icons_row = new CTable(null, 'textwhite');

			$flickerTitleWhenVisible = _('Hide filter');
			$flickerTitleWhenHidden = _('Show filter');

			$flickerTitle = $this->flicker_state ? $flickerTitleWhenVisible : $flickerTitleWhenHidden;

			$icons_row->addRow(array($icon_l, new CSpan(SPACE.$flickerTitle.SPACE, null, 'flicker_title'), $icon_r));

			$thin_tab = $this->createFlicker($icons_row);
			$thin_tab->attr('id', 'filter_icon');
			$thin_tab->addAction('onclick', "javascript: changeFlickerState(".
				"'".$flicker_domid."', ".
				CJs::encodeJson($flickerTitleWhenVisible).", ".
				CJs::encodeJson($flickerTitleWhenHidden).
			");");

			$flicker_tab->addRow($thin_tab, 'textcolorstyles pointer');
			$flicker_tab->addRow($div);

			$widget[] = $flicker_tab;
		}
		$div = new CDiv($this->body, 'w');
		$div->setAttribute('id', $this->bodyId);

		$widget[] = $div;

		return new CDiv($widget, $this->getRootClass());
	}

	public function show() {
		echo $this->toString();
	}

	public function toString() {
		$tab = $this->get();

		return unpack_object($tab);
	}

	private function createPageHeader() {
		$pageHeader = array();

		foreach ($this->pageHeaders as $header) {
			$pageHeader[] = get_table_header($header['left'], $header['right']);
		}

		return new CDiv($pageHeader);
	}

	private function createHeader() {
		$header = reset($this->headers);

		$columnRights = array();

		if (!is_null($header['right'])) {
			foreach ($header['right'] as $right) {
				$columnRights[] = new CDiv($right, 'floatright');
			}
		}

		if ($columnRights) {
			$columnRights = array_reverse($columnRights);
		}

		// header table
		$table = new CTable(null, $this->css_class.' maxwidth');
		$table->setCellSpacing(0);
		$table->setCellPadding(1);
		$table->addRow($this->createHeaderRow($header['left'], $columnRights), 'first');

		if ($this->css_class != 'header_wide') {
			$table->addClass('ui-widget-header ui-corner-all');
		}

		foreach ($this->headers as $num => $header) {
			if ($num > 0) {
				$table->addRow($this->createHeaderRow($header['left'], $header['right']), 'next');
			}
		}

		return new CDiv($table);
	}

	private function createHeaderRow($col1, $col2 = SPACE) {
		$td_r = new CCol($col2, 'header_r right');
		$row = array(new CCol($col1, 'header_l left'), $td_r);
		return $row;
	}

	private function createFlicker($col1, $col2 = null) {
		$table = new CTable(null, 'textwhite maxwidth middle flicker');
		$table->setCellSpacing(0);
		$table->setCellPadding(1);
		if (!is_null($col2)) {
			$td_r = new CCol($col2, 'flicker_r');
			$td_r->setAttribute('align','right');
			$table->addRow(array(new CCol($col1,'flicker_l'), $td_r));
		}
		else {
			$td_c = new CCol($col1, 'flicker_c');
			$td_c->setAttribute('align', 'center');
			$table->addRow($td_c);
		}
		return $table;
	}

	public function setRootClass($rootClass) {
		$this->rootClass = $rootClass;
	}

	public function getRootClass() {
		return $this->rootClass;
	}
}
