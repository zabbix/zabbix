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
	private $severity_min;
	private $sysmap_conf;
	private $previous_maps;
	private $fullscreen;
	private $uniqueid;
	private $error;

	public function __construct(array $options = [], array $widget_settings) {
		parent::__construct();

		$this->sysmap_conf = $options;
		$this->previous_maps = array_key_exists('previous_maps', $widget_settings)
			? $widget_settings['previous_maps']
			: '';
		$this->fullscreen = array_key_exists('fullscreen', $widget_settings) ? $widget_settings['fullscreen'] : 0;
		$this->uniqueid = array_key_exists('uniqueid', $widget_settings) ? $widget_settings['uniqueid'] : 0;
		$this->severity_min = 0;

		$options = [
			'severity_min' => $this->severity_min,
			'fullscreen' => $this->fullscreen
		];

		$sysmapid = array_key_exists('sysmapid', $this->sysmap_conf) ? $this->sysmap_conf['sysmapid'] : [];
		$this->sysmap_data = CMapHelper::get($sysmapid, $options);

		if ($sysmapid) {
			foreach ($this->sysmap_data['elements'] as &$elemnet) {
				$actions = json_decode($elemnet['actions'], true);
				if (array_key_exists('gotos', $actions) && array_key_exists('submap', $actions['gotos'])) {
					$actions['navigatetos']['submap'] = $actions['gotos']['submap'];
					$actions['navigatetos']['submap']['widget_uniqueid'] = $this->uniqueid;
					unset($actions['gotos']['submap']);
				}

				$elemnet['actions'] = json_encode($actions);
			}
			unset($elemnet);
		}
	}

	public function getScriptRun() {
		$script_run = '';

		if ($this->sysmap_conf['source_type'] == WIDGET_SYSMAP_SOURCETYPE_FILTER
			&& array_key_exists('filter_widget_reference', $this->sysmap_conf)
		) {
			$reference = $this->sysmap_conf['filter_widget_reference'];

			$script_run =
				'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'registerAsSharedDataReceiver\', {'.
					'uniqueid: "'.$this->uniqueid.'",'.
					'sourceWidgetReference: "'.$reference.'",'.
					'callback: function(widget, data) {'.
						'if(data[0].mapid !== +data[0].mapid) return;'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
							'\'setWidgetFieldValue\', widget.uniqueid, \'sysmapid\', data[0].mapid);'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
							'\'setWidgetFieldValue\', widget.uniqueid, \'previous_maps\', "");'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
							'\'refreshWidget\', widget.widgetid);'.
					'}'.
				'});';
		}

		if ($this->sysmap_data) {
			$this->sysmap_data['container'] = "#map_{$this->uniqueid}";

			$script_run .= 'jQuery(document).ready(function(){'.
				'new SVGMap('.zbx_jsvalue($this->sysmap_data).')'.
			'});';
		}

		return $script_run;
	}

	private function build() {
		$this->addClass(ZBX_STYLE_SYSMAP);
		$this->setAttribute('data-uniqueid', $this->uniqueid);
		$this->setId(uniqid());

		if ($this->error === null) {
			if ($this->previous_maps) {
				$this->previous_maps = array_filter(explode(',', $this->previous_maps), 'is_numeric');

				if ($this->previous_maps) {
					// get previous map
					$maps = API::Map()->get([
						'sysmapids' => [array_pop($this->previous_maps)],
						'output' => ['sysmapid', 'name']
					]);

					if ($maps) {
						if (($map = reset($maps)) !== false) {
							$go_back_div = (new CDiv())
								->setAttribute('style', 'padding:5px 10px; border-bottom: 1px solid #ebeef0;')
								->addItem(
									(new CLink(_s('Go back to %1$s', $map['name']), 'javascript:void(0)'))
										->onClick('javascript: navigateToSubmap('.$map['sysmapid'].', "' .
											$this->uniqueid.'", true);')
								);

							$this->addItem($go_back_div);
						}
					}
				}
			}

			$map_div = (new CDiv())
				->setId('map_'.$this->uniqueid)
				->addStyle('width:'.$this->sysmap_data['canvas']['width'].'px;')
				->addStyle('height:'.$this->sysmap_data['canvas']['height'].'px;')
				->addStyle('overflow:hidden;');

			$this->addStyle('position:relative;');
			$this->addItem($map_div);
		}
		else {
			$this->addClass(ZBX_STYLE_DISABLED);
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
