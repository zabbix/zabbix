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
		$widgetids = [
			WIDGET_SYSTEM_STATUS, WIDGET_ZABBIX_STATUS, WIDGET_LAST_ISSUES,
			WIDGET_WEB_OVERVIEW, WIDGET_DISCOVERY_STATUS, WIDGET_HOST_STATUS,
			WIDGET_FAVOURITE_GRAPHS, WIDGET_FAVOURITE_MAPS, WIDGET_FAVOURITE_SCREENS
		];

		$fields = [
			'widgets' =>	'required|array'
//			'widgetid' =>		'fatal|required|in '.implode(',', $widgetids),
//			'refreshrate' =>	'fatal         |in 10,30,60,120,600,900',
//			'state' =>			'fatal         |in 0,1',
//			'row' =>			'fatal         |ge 0',
//			'col' =>			'fatal         |ge 0',
//			'height' =>			'fatal         |ge 1',
//			'width' =>			'fatal         |ge 1'
		];

// TODO: validation

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {

/*		if ($this->hasInput('refreshrate')) {
			$refreshrate = $this->getInput('refreshrate');

			CProfile::update('web.dashbrd.widget.'.$widgetid.'.rf_rate', $refreshrate, PROFILE_TYPE_INT);

			$data['main_block'] =
				'PMasters["dashboard"].dolls["'.$widgetid.'"].frequency('.CJs::encodeJson($refreshrate).');'."\n".
				'PMasters["dashboard"].dolls["'.$widgetid.'"].restartDoll();';
		}

		if ($this->hasInput('state')) {
			CProfile::update('web.dashbrd.widget.'.$widgetid.'.state', $this->getInput('state'), PROFILE_TYPE_INT);
		}
*/
		foreach ($this->getInput('widgets') as $widget) {
			$widgetid = $widget['widgetid'];

			CProfile::update('web.dashbrd.widget.'.$widgetid.'.row', $widget['row'], PROFILE_TYPE_INT);
			CProfile::update('web.dashbrd.widget.'.$widgetid.'.col', $widget['col'], PROFILE_TYPE_INT);
			CProfile::update('web.dashbrd.widget.'.$widgetid.'.height', $widget['height'], PROFILE_TYPE_INT);
			CProfile::update('web.dashbrd.widget.'.$widgetid.'.width', $widget['width'], PROFILE_TYPE_INT);
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
	}
}
