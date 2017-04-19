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

class CControllerWidgetUrlView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {

		$data = [];
		$error = null;

		// Default values
		$default = [
			'url' => '',
			'width' => '100%',
			'height' => '98%',
			'host_id' => 0,
			'isTemplatedDashboard' => false, // TODO VM: will dashboards be templated? Most likely - yes.
			'dynamic' => WIDGET_SIMPLE_ITEM
		];

		// Get URL widget configuration for dashboard
		// ------------------------ START OF TEST DATA -------------------
		// TODO (VM): replace test data with data from configuration
		$data['url'] = 'http://www.zabbix.com';
		// ------------------------ END OF TEST DATA -------------------

		// Apply defualt value for data
		foreach ($default as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		if ($data['dynamic'] == WIDGET_DYNAMIC_ITEM && $data['host_id'] == 0) {
			$error = _('No host selected.');
		}
		else {
			$resolveHostMacros = ($data['dynamic'] == WIDGET_DYNAMIC_ITEM || $data['isTemplatedDashboard']);

			$resolved_url = CMacrosResolverHelper::resolveWidgetURL([
				'config' => $resolveHostMacros ? 'widgetURL' : 'widgetURLUser',
				'url' => $data['url'],
				'hostid' => $resolveHostMacros ? $data['host_id'] : 0
			]);

			$data['url'] = $resolved_url ? $resolved_url : $data['url'];
		}

		$this->setResponse(new CControllerResponseData([
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'url' => [
				'url' => $data['url'],
				'width' => $data['width'],
				'height' => $data['height'],
				'error' => $error
			]
		]));
	}
}
