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

class CControllerDashbrdWidgetUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'widgets' =>	'required|array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$widgetids = [
				WIDGET_SYSTEM_STATUS, WIDGET_ZABBIX_STATUS, WIDGET_LAST_ISSUES, WIDGET_WEB_OVERVIEW,
				WIDGET_DISCOVERY_STATUS, WIDGET_HOST_STATUS, WIDGET_FAVOURITE_GRAPHS, WIDGET_FAVOURITE_MAPS,
				WIDGET_FAVOURITE_SCREENS
			];

			/*
			 * @var array  $widgets
			 * @var string $widget[]['widgetid']
			 * @var int    $widget[]['rf_rate']        (optional)
			 * @var array  $widget[]['pos']            (optional)
			 * @var int    $widget[]['pos']['row']
			 * @var int    $widget[]['pos']['col']
			 * @var int    $widget[]['pos']['height']
			 * @var int    $widget[]['pos']['width']
			 */
			foreach ($this->getInput('widgets') as $widget) {
				// TODO: validation
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		foreach ($this->getInput('widgets') as $widget) {
			$widgetid = $widget['widgetid'];

			if (array_key_exists('rf_rate', $widget)) {
				CProfile::update('web.dashbrd.widget.'.$widgetid.'.rf_rate', $widget['rf_rate'], PROFILE_TYPE_INT);
			}

			if (array_key_exists('pos', $widget)) {
				CProfile::update('web.dashbrd.widget.'.$widgetid.'.row', $widget['pos']['row'], PROFILE_TYPE_INT);
				CProfile::update('web.dashbrd.widget.'.$widgetid.'.col', $widget['pos']['col'], PROFILE_TYPE_INT);
				CProfile::update('web.dashbrd.widget.'.$widgetid.'.height', $widget['pos']['height'], PROFILE_TYPE_INT);
				CProfile::update('web.dashbrd.widget.'.$widgetid.'.width', $widget['pos']['width'], PROFILE_TYPE_INT);
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
	}
}
