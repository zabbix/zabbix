<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	protected $tabs = array();
	protected $headers = array();
	protected $selectedTab = null;

	/**
	 * Disabled tabs IDs, tab option
	 *
	 * @var array
	 */
	protected $disabledTabs = array();

	public function __construct($data = array()) {
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
		$this->attr('id', zbx_formatDomId($this->id));
		$this->attr('class', 'tabs');
	}

	public function setSelected($selected) {
		$this->selectedTab = $selected;
	}

	/**
	 * Disable tabs
	 *
	 * @param array		$disabled	disabled tabs IDs (first tab - 0, second - 1...)
	 *
	 * @return void
	 */
	public function setDisabled($disabled) {
		$this->disabledTabs = $disabled;
	}

	public function addTab($id, $header, $body) {
		$this->headers[$id] = $header;
		$this->tabs[$id] = new CDiv($body);
		$this->tabs[$id]->attr('id', zbx_formatDomId($id));
	}

	public function toString($destroy = true) {
		if (count($this->tabs) == 1) {
			$this->setAttribute('class', 'min-width ui-tabs ui-widget ui-widget-content ui-corner-all widget');

			$header = reset($this->headers);
			$header = new CDiv($header);
			$header->addClass('ui-corner-all ui-widget-header header');
			$header->setAttribute('id', 'tab_'.key($this->headers));
			$this->addItem($header);

			$tab = reset($this->tabs);
			$tab->addClass('ui-tabs ui-tabs-panel ui-widget ui-widget-content ui-corner-all widget');
			$this->addItem($tab);
		}
		else {
			$headersList = new CList();

			foreach ($this->headers as $id => $header) {
				$tabLink = new CLink($header, '#'.$id, null, null, false);
				$tabLink->setAttribute('id', 'tab_'.$id);
				$headersList->addItem($tabLink);
			}

			$this->addItem($headersList);
			$this->addItem($this->tabs);

			if ($this->selectedTab === null) {
				$activeTab = get_cookie('tab', 0);
				$createEvent = '';
			}
			else {
				$activeTab = $this->selectedTab;
				$createEvent = 'create: function() { jQuery.cookie("tab", '.$this->selectedTab.'); },';
			}

			$disabledTabs = ($this->disabledTabs === null) ? '' : 'disabled: '.CJs::encodeJson($this->disabledTabs).',';

			zbx_add_post_js('
				jQuery("#'.$this->id.'").tabs({
					'.$createEvent.'
					'.$disabledTabs.'
					active: '.$activeTab.',
					activate: function(event, ui) {
						jQuery.cookie("tab", ui.newTab.index().toString());
					}
				})
				.css("visibility", "visible");'
			);
		}

		return parent::toString($destroy);
	}
}
