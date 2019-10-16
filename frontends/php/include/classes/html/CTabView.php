<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CTabView extends CDiv {

	protected $id = 'tabs';
	protected $tabs = [];
	protected $headers = [];
	protected $footer = null;
	protected $selectedTab = null;

	/**
	 * Script for tab change event.
	 */
	private $tab_change_js = '';

	/**
	 * Disabled tabs IDs, tab option
	 *
	 * @var array
	 */
	protected $disabledTabs = [];

	public function __construct($data = []) {
		if (isset($data['id'])) {
			$this->id = $data['id'];
		}
		if (isset($data['selected'])) {
			$this->setSelected($data['selected']);
		}
		if (isset($data['disabled'])) {
			$this->setDisabled($data['disabled']);
		}
		parent::__construct();
		$this->setId(zbx_formatDomId($this->id));
		$this->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER);
	}

	public function setSelected($selected) {
		$this->selectedTab = $selected;
		return $this;
	}

	/**
	 * Set javascript on tab change event.
	 *
	 * @param string $value  Script body.
	 *
	 * @return CTabView
	 */
	public function onTabChange($value) {
		$this->tab_change_js = $value;

		return $this;
	}

	/**
	 * Disable tabs
	 *
	 * @param array		$disabled	disabled tabs IDs (first tab - 0, second - 1...)
	 */
	public function setDisabled($disabled) {
		$this->disabledTabs = $disabled;
		return $this;
	}

	public function addTab($id, $header, $body) {
		$this->headers[$id] = $header;
		$this->tabs[$id] = new CDiv($body);
		$this->tabs[$id]->setId(zbx_formatDomId($id));
		return $this;
	}

	public function setFooter($footer) {
		$this->footer = $footer;
		return $this;
	}

	public function toString($destroy = true) {
		// No header if we have only one Tab
		if (count($this->tabs) == 1) {
			$tab = reset($this->tabs);
			$this->addItem($tab);
		}
		else {
			$headersList = (new CList())->addClass(ZBX_STYLE_TABS_NAV);

			foreach ($this->headers as $id => $header) {
				$tabLink = (new CLink($header, '#'.$id))
					->setId('tab_'.$id);
				$headersList->addItem($tabLink);
			}

			$this->addItem($headersList);
			$this->addItem($this->tabs);

			zbx_add_post_js($this->makeJavascript());
		}

		$this->addItem($this->footer);

		return parent::toString($destroy);
	}

	public function makeJavascript() {
		if ($this->selectedTab === null) {
			$active_tab = (int) get_cookie('tab', 0);
			$create_event = '';
		}
		else {
			$active_tab = $this->selectedTab;
			$create_event = 'create: function() { jQuery.cookie("tab", '.CJs::encodeJson($this->selectedTab).'); },';
		}

		$disabled_tabs = ($this->disabledTabs === null) ? '' : 'disabled: '.CJs::encodeJson($this->disabledTabs).',';

		return
			'jQuery("#'.$this->id.'").tabs({'.
				$create_event.
				$disabled_tabs.
				'active: '.CJs::encodeJson($active_tab).','.
				'activate: function(event, ui) {'.
					'jQuery.cookie("tab", ui.newTab.index().toString());'.
					$this->tab_change_js.
				'}'.
			'})'.
			'.css("visibility", "visible");';
	}
}
