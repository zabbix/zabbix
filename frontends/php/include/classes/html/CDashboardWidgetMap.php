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
	private $sysmap_data;
	private $fullscreen;
	private $error;

	public function __construct(array $options = [], $fullscreen = 0) {
		parent::__construct();

		$this->sysmap_conf = $options;
		$this->fullscreen = $fullscreen;
		$this->severity_min = 0;

		$options = [
			'severity_min' => $this->severity_min,
			'fullscreen' => $this->fullscreen
		];

		if ($this->sysmap_conf['sysmapid']) {
			$this->sysmap_data = CMapHelper::get($this->sysmap_conf['sysmapid'], $options);
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
					'widgetid: '.(int)$this->sysmap_conf['widgetid'].','.
					'sourceWidgetReference: "'.$reference.'",'.
					'callback: function(widget, data) {'.
						'if(data[0].mapid !== +data[0].mapid) return;'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
							'\'setWidgetFieldValue\', widget.widgetid, \'sysmapid\', data[0].mapid);'.
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid('.
							'\'refreshWidget\', widget.widgetid);'.
					'}'.
				'});';
		}

		if ($this->sysmap_data) {
			$this->sysmap_data['container'] = "#map_{$this->sysmap_conf['widgetid']}";

			$script_run .= 'jQuery(document).ready(function(){'.
				'new SVGMap('.zbx_jsvalue($this->sysmap_data).')'.
			'});';
		}

		return $script_run;
	}

	private function build() {
		$this->addClass(ZBX_STYLE_SYSMAP);
		$this->setId(uniqid());

		if ($this->error === null) {
			$map_div = (new CDiv())
				->setId('map_'.$this->sysmap_conf['widgetid'])
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
			'js/vector/class.svg.map.js'
		];
	}
}
