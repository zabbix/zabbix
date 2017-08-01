<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CDashboardWidgetMap extends CDiv {
	private $filter_widget_reference;
	private $previous_map;
	private $sysmap_data;
	private $current_sysmapid;
	private $source_type;
	private $initial_load;
	private $uniqueid;
	private $error;

	public function __construct(array $sysmap_data, array $widget_settings) {
		parent::__construct();

		$this->error = $widget_settings['error'];
		$this->sysmap_data = $sysmap_data;
		$this->current_sysmapid = $widget_settings['current_sysmapid'];
		$this->filter_widget_reference = $widget_settings['filter_widget_reference'];
		$this->source_type = $widget_settings['source_type'];
		$this->previous_map = $widget_settings['previous_map'];
		$this->initial_load = $widget_settings['initial_load'];
		$this->uniqueid = $widget_settings['uniqueid'];
	}

	public function getScriptRun() {
		$script_run = '';

		if ($this->current_sysmapid !== null && $this->initial_load) {
			// this should be before other scripts
			$script_run .= 'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
				'\'setWidgetStorageValue\', "'.$this->uniqueid.'", \'current_sysmapid\', '.$this->current_sysmapid.
			');';
		}

		if ($this->initial_load) {
			$script_run .=
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "timer_refresh", '.
					'"zbx_sysmap_widget_trigger", "'.$this->uniqueid.'", {'.
						'parameters: ["onWidgetRefresh"],'.
						'grid: {widget: 1},'.
						'trigger_name: "map_widget_timer_refresh_'.$this->uniqueid.'"'.
					'}'.
				');';
		}

		if ($this->source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER && $this->filter_widget_reference
				&& $this->initial_load) {
			$script_run .=
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'registerDataExchange\', {'.
					'uniqueid: "'.$this->uniqueid.'",'.
					'linkedto: "'.$this->filter_widget_reference.'",'.
					'data_name: "selected_mapid",'.
					'callback: function(widget, data) {'.
						'if (data[0].mapid !== +data[0].mapid) {'.
							'return;'.
						'}'.

						'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'setWidgetStorageValue\', '.
							'widget.uniqueid, \'current_sysmapid\', data[0].mapid'.
						');'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'setWidgetStorageValue\', '.
							'widget.uniqueid, \'previous_maps\', ""'.
						');'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'refreshWidget\', widget.widgetid);'.
					'}'.
				'});'.

				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("callWidgetDataShare");'.

				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "onEditStart", '.
					'"zbx_sysmap_widget_trigger", "'.$this->uniqueid.'", {'.
						'parameters: ["onEditStart"],'.
						'grid: {widget: 1},'.
					'trigger_name: "map_widget_on_edit_start_'.$this->uniqueid.'"'.
				'});';
		}

		if ($this->sysmap_data && $this->error === null) {
			$this->sysmap_data['container'] = "#map_{$this->uniqueid}";

			$script_run .= 'jQuery(function($) {'.
				'$("#'.$this->getId().'").zbx_mapwidget({'.
					'uniqueid: "'.$this->uniqueid.'",'.
					'map_options: '.zbx_jsvalue($this->sysmap_data).
				'})'.
			'});';
		}

		return $script_run;
	}

	private function build() {
		$this->addClass(ZBX_STYLE_SYSMAP);
		$this->setId(uniqid());

		if ($this->error === null) {
			$this->setAttribute('data-uniqueid', $this->uniqueid);

			if ($this->previous_map) {
				$go_back_div = (new CDiv())
					->setAttribute('style', 'padding: 5px 10px; border-bottom: 1px solid #ebeef0;')
					->addItem(
						(new CLink(_s('Go back to %1$s', $this->previous_map['name']), 'javascript: navigateToSubmap('.
							$this->previous_map['sysmapid'].', "'.$this->uniqueid.'", true);'
						))
					);

				$this->addItem($go_back_div);
			}

			$map_div = (new CDiv())
				->setId('map_'.$this->uniqueid)
				->addClass('sysmap-widget-container');

			$this->addStyle('position:relative;');
			$this->addItem($map_div);
		}
		else {
			$this->addItem((new CTableInfo())->setNoDataMessage($this->error));
		}
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}

	public function getScriptFile() {
		return [
			'js/vector/class.svg.canvas.js',
			'js/vector/class.svg.map.js',
			'js/class.mapWidget.js',
		];
	}
}
