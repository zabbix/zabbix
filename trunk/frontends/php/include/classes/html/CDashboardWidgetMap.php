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


/**
 * Dashboard Map widget class. Creates all widget specific JavaScript and HTML content for map widget's view.
 */
class CDashboardWidgetMap extends CDiv {

	/**
	 * Reference of linked map navigation tree widget.
	 *
	 * @var string
	 */
	private $filter_widget_reference;

	/**
	 * Map that will be linked to 'go back to [previous map name]' link in dashboard map widget.
	 *
	 * @var array|null - array must contain at least integer value 'sysmapid' and string 'name'.
	 */
	private $previous_map;

	/**
	 * Response array of CMapHelper::get() that represents currently opened map.
	 *
	 * @var array|null
	 */
	private $sysmap_data;

	/**
	 * Requested sysmapid.
	 *
	 * @var int
	 */
	private $current_sysmapid;

	/**
	 * The type of source of map widget.
	 *
	 * @var int	- allowed values are WIDGET_SYSMAP_SOURCETYPE_MAP and WIDGET_SYSMAP_SOURCETYPE_FILTER.
	 */
	private $source_type;

	/**
	 * Represents either this is initial or repeated load of map widget.
	 *
	 * @var int	- allowed values are 0 and 1.
	 */
	private $initial_load;

	/**
	 * Unique ID of widget.
	 *
	 * @var string
	 */
	private $uniqueid;

	/**
	 * The error message displayed in map widget.
	 *
	 * @var string|null
	 */
	private $error;

	/**
	 * Class constructor.
	 *
	 * @param array			$sysmap_data		An array of requested map in the form created by CMapHelper::get()
	 *											method.
	 * @param array			$widget_settings	An array contains widget settings.
	 * @param string|null	$widget_settings['error']			A string of error message or null in case if error is
	 *															not detected.
	 * @param int			$widget_settings['current_sysmapid'] An integer of requested sysmapid.
	 * @param string		$widget_settings['filter_widget_reference'] A string of linked map navigation tree
	 *															reference.
	 * @param int			$widget_settings['source_type']		The type of source of map widget.
	 * @param array|null	$widget_settings['previous_map']	Sysmapid and name of map linked as previous.
	 * @param int			$widget_settings['initial_load']	Integer represents either this is initial load or
	 *															repeated.
	 * @param string		$widget_settings['uniqueid']		A string of widget's unique id assigned by dashboard.
	 */
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

	/**
	 * A javascript that is used as widget's script_inline parameter.
	 *
	 * @return string
	 */
	public function getScriptRun() {
		$script_run = '';

		if ($this->current_sysmapid !== null && $this->initial_load) {
			// This should be before other scripts.
			$script_run .=
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
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

			$script_run .=
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "afterUpdateWidgetConfig", '.
					'"zbx_sysmap_widget_trigger", "'.$this->uniqueid.'", {'.
						'parameters: ["afterUpdateWidgetConfig"],'.
						'grid: {widget: 1},'.
						'trigger_name: "after_map_widget_config_update_'.$this->uniqueid.'"'.
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
					'});'.
					// Hack for Safari to manually accept parent container height in pixels when map widget is loaded.
					'if (SF) {'.
						'$("#'.$this->getId().'").height($("#'.$this->getId().'").parent().height())'.
					'}'.
				'});';
		}
		elseif ($this->error !== null && $this->source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
			$error_msg_html = (new CTableInfo())->setNoDataMessage($this->error);
			$script_run .=
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid("addAction", "onDashboardReady", '.
					'"zbx_sysmap_widget_trigger", "'.$this->uniqueid.'", {'.
						'parameters: ["onDashboardReady", {html: "'. addslashes($error_msg_html).'"}],'.
						'grid: {widget: 1},'.
						'priority: 10,'.
						'trigger_name: "on_dashboard_ready_'.$this->uniqueid.'"'.
					'}'.
				');';
		}

		return $script_run;
	}

	/**
	 * Build an object of HTML used in widget content.
	 */
	private function build() {
		$this->addClass(ZBX_STYLE_SYSMAP);
		$this->setId(uniqid());

		if ($this->error === null) {
			$this->setAttribute('data-uniqueid', $this->uniqueid);

			if ($this->previous_map) {
				$go_back_div = (new CDiv())
					->addClass('btn-back-map-container')
					->addItem(
						(new CLink(
							(new CSpan())
								->addClass('btn-back-map')
								->addItem((new CDiv())->addClass('btn-back-map-icon'))
								->addItem((new CDiv())
									->addClass('btn-back-map-content')
									->addItem(_s('Go back to %1$s', $this->previous_map['name']))
								),
								'javascript: navigateToSubmap('.$this->previous_map['sysmapid'].', "'.
									$this->uniqueid.'", true);'
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
		elseif ($this->source_type == WIDGET_SYSMAP_SOURCETYPE_MAP) {
			$this->addItem((new CTableInfo())->setNoDataMessage($this->error));
		}
	}

	/**
	 * Gets string representation of widget HTML content.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}

	/**
	 * Returns a list of javascript files that are requested to load map widget.
	 *
	 * @return array
	 */
	public function getScriptFile() {
		return [
			'js/vector/class.svg.canvas.js',
			'js/vector/class.svg.map.js',
			'js/class.mapWidget.js'
		];
	}
}
