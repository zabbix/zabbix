<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	}

	/**
	 * A javascript that is used as widget's script_inline parameter.
	 *
	 * @return string
	 */
	public function getScriptData() {
		$map_data = [
			'current_sysmapid' => null,
			'filter_widget_reference' => null,
			'map_options' => null
		];

		if ($this->current_sysmapid !== null && $this->initial_load) {
			$map_data['current_sysmapid'] = $this->current_sysmapid;
		}

		if ($this->source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER
				&& $this->filter_widget_reference
				&& $this->initial_load) {
			$map_data['filter_widget_reference'] = $this->filter_widget_reference;
		}

		if ($this->sysmap_data && $this->error === null) {
			$map_data['map_options'] = $this->sysmap_data;
		}
		elseif ($this->error !== null && $this->source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
			$map_data['error_msg'] = (new CTableInfo())
				->setNoDataMessage($this->error)
				->toString();
		}

		return $map_data;
	}

	/**
	 * Build an object of HTML used in widget content.
	 */
	private function build() {
		$this->addClass(ZBX_STYLE_SYSMAP);
		$this->setId(uniqid());

		if ($this->error === null) {
			if ($this->previous_map) {
				$go_back_div = (new CDiv())
					->addClass(ZBX_STYLE_BTN_BACK_MAP_CONTAINER)
					->addItem(
						(new CLink(
							(new CSpan())
								->addClass(ZBX_STYLE_BTN_BACK_MAP)
								->addItem((new CDiv())->addClass(ZBX_STYLE_BTN_BACK_MAP_ICON))
								->addItem((new CDiv())
									->addClass(ZBX_STYLE_BTN_BACK_MAP_CONTENT)
									->addItem(_s('Go back to %1$s', $this->previous_map['name']))
								),
								'#'
						))->addClass('js-previous-map')
					);

				$this->addItem($go_back_div);
			}

			$map_div = (new CDiv((new CDiv($this->sysmap_data['aria_label']))->addClass(ZBX_STYLE_INLINE_SR_ONLY)))
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
}
