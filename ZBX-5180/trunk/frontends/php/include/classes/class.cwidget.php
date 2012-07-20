<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CWidget {

	public $state;
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
		$this->css_class = is_null($this->state) ? 'header_wide' : 'header';
		$this->setRootClass($rootClass);
	}

	public function setClass($class = null) {
		if (is_string($class)) {
			$this->css_class = $class;
		}
	}

	public function addPageHeader($header, $headerright = SPACE) {
		zbx_value2array($headerright);
		if (is_null($header) && !is_null($headerright)) {
			$header = SPACE;
		}
		$this->pageHeaders[] = array('left' => $header, 'right' => $headerright);
	}

	public function addHeader($header = null, $headerright = SPACE) {
		zbx_value2array($headerright);
		if (is_null($header) && !is_null($headerright)) {
			$header = SPACE;
		}
		$this->headers[] = array('left' => $header, 'right' => $headerright);
	}

	public function addHeaderRowNumber($headerright = SPACE) {
		$numRows = new CDiv();
		$numRows->setAttribute('name', 'numrows');
		$this->addHeader($numRows, $headerright);
	}

	public function addFlicker($items = null, $state = 0) {
		if (!is_null($items)) {
			$this->flicker[] = $items;
		}
		$this->flicker_state = $state;
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
		if (is_null($this->state)) {
			$this->state = true;
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
			$icons_row->addRow(array($icon_l, new CSpan(SPACE._('Filter').SPACE), $icon_r));

			$thin_tab = $this->createFlicker($icons_row);
			$thin_tab->attr('id', 'filter_icon');
			$thin_tab->addAction('onclick', "javascript: change_flicker_state('".$flicker_domid."');");

			$flicker_tab->addRow($thin_tab, 'textcolorstyles pointer');
			$flicker_tab->addRow($div);

			$widget[] = $flicker_tab;
		}
		$div = new CDiv($this->body, 'w');
		$div->setAttribute('id', $this->bodyId);
		if (!$this->state) {
			$div->setAttribute('style', 'display: none;');
		}
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
			$pageHeader[] = $this->createPageHeaderRow($header['left'], $header['right']);
		}
		return new CDiv($pageHeader);
	}

	private function createPageHeaderRow($col1, $col2 = SPACE) {
		if (isset($_REQUEST['print'])) {
			hide_form_items($col1);
			hide_form_items($col2);
			if ($col1 == SPACE && $col2 == SPACE) {
				return new CJSscript('');
			}
		}

		$td_l = new CCol(SPACE);
		$td_l->setAttribute('width', '100%');

		$right_row = array($td_l);

		if (!is_null($col2)) {
			if (!is_array($col2)) {
				$col2 = array($col2);
			}
			foreach ($col2 as $r_item) {
				$right_row[] = new CCol($r_item);
			}
		}

		$right_tab = new CTable(null, 'nowrap');
		$right_tab->setAttribute('width', '100%');
		$right_tab->addRow($right_row);

		$table = new CTable(null, 'ui-widget-header ui-corner-all header maxwidth');
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		$td_r = new CCol($right_tab, 'header_r right');
		$table->addRow(array(new CCol($col1, 'header_l left'), $td_r));
		return $table;
	}

	private function createHeader() {
		$header = reset($this->headers);

		$td_l = new CCol(SPACE);
		$td_l->setAttribute('width', '100%');

		$right_row = array($td_l);

		if (!is_null($header['right'])) {
			foreach ($header['right'] as $r_item) {
				$right_row[] = new CCol($r_item);
			}
		}
		if (!is_null($this->state)) {
			$icon = new CIcon(_('Show').'/'._('Hide'), ($this->state ? 'arrowup' : 'arrowdown'), "change_hat_state(this, '".$this->bodyId."');");
			$icon->setAttribute('id', $this->bodyId.'_icon');
			$right_row[] = new CCol($icon);
		}

		$right_tab = new CTable(null, 'nowrap');
		$right_tab->setAttribute('width', '100%');
		$right_tab->addRow($right_row, 'textblackwhite');
		$header['right'] = $right_tab;

		$header_tab = new CTable(null, $this->css_class.' maxwidth');
		if ($this->css_class != 'header_wide') {
			$header_tab->addClass('ui-widget-header ui-corner-all');
		}
		$header_tab->setCellSpacing(0);
		$header_tab->setCellPadding(1);
		$header_tab->addRow($this->createHeaderRow($header['left'], $right_tab), 'first');

		foreach ($this->headers as $num => $header) {
			if ($num == 0) {
				continue;
			}
			$header_tab->addRow($this->createHeaderRow($header['left'], $header['right']), 'next');
		}
		return new CDiv($header_tab);
	}

	private function createHeaderRow($col1, $col2 = SPACE) {
		if (isset($_REQUEST['print'])) {
			hide_form_items($col1);
			hide_form_items($col2);

			// if empty header, do not show it
			if ($col1 === SPACE && $col2 === SPACE) {
				return new CJSscript('');
			}
		}
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
