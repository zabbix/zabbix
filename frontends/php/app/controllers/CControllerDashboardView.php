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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerDashboardView extends CController {

	private $widget_config;

	public function __construct() {
		parent::__construct();

		$this->widget_config = new CWidgetConfig();
	}

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'fullscreen' =>	'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$grid_widgets = [];

		// these fields should not appear in fields array of widget in dasjboard.js
		$fields_to_unset = ['widgetid', 'row', 'col', 'height', 'width'];

		$widgets_config = $this->widget_config->getAllWidgetConfig();
		$widget_names = $this->widget_config->getKnownWidgetTypesWNames($this->getUserType());

		foreach ($widgets_config as  $widget_config) {
			$widgetid = (int) $widget_config['widgetid'];
			$default_rf_rate = $this->widget_config->getDefaultRfRate($widget_config['type']);

			$grid_widgets[$widgetid]['widgetid'] = $widgetid;
			$grid_widgets[$widgetid]['type'] = $widget_config['type'];
			$grid_widgets[$widgetid]['header'] = $widget_names[$widget_config['type']];
			$grid_widgets[$widgetid]['pos'] = [];
			$grid_widgets[$widgetid]['pos']['row'] = (int) $widget_config['row'];
			$grid_widgets[$widgetid]['pos']['col'] = (int) $widget_config['col'];
			$grid_widgets[$widgetid]['pos']['height'] = (int) $widget_config['height'];
			$grid_widgets[$widgetid]['pos']['width'] = (int) $widget_config['width'];
			$grid_widgets[$widgetid]['rf_rate'] = (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.rf_rate', $default_rf_rate);

			foreach($fields_to_unset as $field) {
				unset($widget_config[$field]);
			}
			$grid_widgets[$widgetid]['fields'] = $widget_config;
		}
		// TODO VM: delete refresh rate from all user profiles when deleting widget

		$data = [
			'fullscreen' => $this->getInput('fullscreen', 0),
			'filter_enabled' => CProfile::get('web.dashconf.filter.enable', 0),
			'grid_widgets' => $grid_widgets
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboard'));
		$this->setResponse($response);
	}
}
