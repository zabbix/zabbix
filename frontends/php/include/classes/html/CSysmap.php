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

class CSysmap extends CDiv {
	private $error;
	private $script_file;
	private $script_run;
	private $sysmap_data;
	private $sysmap_canva;
	private $severity_min;
	private $fullscreen;

	public function __construct($config_data) {
		parent::__construct();
		$this->error = null;
		$this->sysmap_conf = $config_data;
		$this->severity_min = 0;
		$this->fullscreen = 0;

		$this->setId(uniqid());
		$this->addClass(ZBX_STYLE_SYSMAP);

		if ($this->sysmap_conf['sysmapid']) {
			$this->sysmap_data = CMapHelper::get($this->sysmap_conf['sysmapid'], $this->severity_min);

			if ($this->sysmap_data) {
				$this->sysmap_data['container'] = "#map_{$this->sysmap_conf['widgetid']}";
			}
		}

		$this->sysmap_canva = $this->getScreenMap([
			'dataId' => 'mapimg',
			'screenitem' => [
				'screenitemid' => $this->sysmap_conf['widgetid'],
				'resourceid' => $this->sysmap_conf['widgetid'],
				'screenid' => null,
				'width' => null,
				'height' => null,
				'severity_min' => $this->severity_min,
				'fullscreen' => $this->fullscreen
			]
		]);

		// TODO miks: let them be loaded only once.
		$this->script_file = [
			'js/gtlc.js',
			'js/flickerfreescreen.js',
			'js/vector/class.svg.canvas.js',
			'js/vector/class.svg.map.js'
		];
		$this->script_run = '';
	}

	public function setError($value) {
		$this->error = $value;

		return $this;
	}

	public function getScriptFile() {
		return $this->script_file;
	}

	public function getScriptRun() {
		if ($this->error === null) {
			if ($this->sysmap_conf['source_type'] == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
				$reference = null;
				if (array_key_exists('filter_widget_reference', $this->sysmap_conf)) {
					$reference = $this->sysmap_conf['filter_widget_reference'];
				}

				if ($reference) {
					$this->script_run =
						'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'registerAsSharedDataReceiver\', {'
						. 'widgetid: '.(int)$this->sysmap_conf['widgetid'].','
						. 'sourceWidgetReference: "'.$reference.'",'
						. 'callback: function(widget, data) {'
						. ' if(data[0].mapid !== +data[0].mapid) return;'
						. '	jQuery(".dashbrd-grid-widget-container")'
						.			'.dashboardGrid(\'setWidgetFieldValue\', widget.widgetid, \'sysmap_id\', data[0].mapid);'
						.	' jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'refreshWidget\', widget.widgetid);'
						. '}'
						.'});';
				}
				else {
					info(_('Filter widget does not exist.'));
				}
			}

			if ($this->sysmap_data) {
				$this->script_run .= 'jQuery(document).ready(function(){'.
					$this->sysmap_canva->getFlickerfreeJs($this->sysmap_data).
				'});';
			}
		}

		return $this->script_run;
	}

	protected function getScreenMap(array $options = []) {
		// get resourcetype from screenitem
		if (!array_key_exists('screenitem', $options) && array_key_exists('screenitemid', $options)) {
			if (array_key_exists('hostid', $options) && $options['hostid'] > 0) {
				$options['screenitem'] = API::TemplateScreenItem()->get([
					'screenitemids' => $options['screenitemid'],
					'hostids' => $options['hostid'],
					'output' => API_OUTPUT_EXTEND
				]);
			}
			else {
				$options['screenitem'] = API::ScreenItem()->get([
					'screenitemids' => $options['screenitemid'],
					'output' => API_OUTPUT_EXTEND
				]);
			}
			$options['screenitem'] = reset($options['screenitem']);
		}

		if (array_key_exists('screenitem', $options) && array_key_exists('resourcetype', $options['screenitem'])) {
			$options['resourcetype'] = $options['screenitem']['resourcetype'];
		}

		return new CDashboardWidgetMap($options, $this->sysmap_conf['widgetid']);
	}

	private function build() {
		$map_div = (new CDiv())
			->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
			->addClass(ZBX_STYLE_SYSMAP)
			->addItem($this->sysmap_canva->get());

		if ($this->error !== null) {
			$map_div->addClass(ZBX_STYLE_DISABLED);
		}

		$this->addItem($map_div);
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
