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


class CControllerWidgetIteratorGraphPrototypeView extends CControllerWidgetIterator {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_GRAPH_PROTOTYPE);
		$this->setValidationRules([
//			'name' => 'string',
//			'uniqueid' => 'required|string',
//			'initial_load' => 'in 0,1',
//			'edit_mode' => 'in 0,1',
//			'dashboardid' => 'db dashboard.dashboardid',
//			'fields' => 'json',
//			'dynamic_hostid' => 'db hosts.hostid',
//			'content_width' => 'int32',
//			'content_height' => 'int32'
		]);
	}

	protected function doAction() {
		$widgets = [];
		for ($i = 0; $i < 7; $i++) {
			$widgets[] = [
				"widgetid" => 'child-' . $i,
				"type" => "clock",
				"header" => "YADA - {$i}",
				"scrollable" => true,
				"padding" => true,
				"fields" => [
					'time_type' => 0,
				],
			];
		}

		$output = [
			'header' => 'demo-controller-header',
			'widgets_of_iterator' => $widgets,

		];

//		usleep(500000);

		echo (new CJson())->encode($output);
	}
}
