<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

class CControllerDashboardWidget extends CController {

	protected function checkInput() {
		$widgets = array(
			WIDGET_SYSTEM_STATUS, WIDGET_ZABBIX_STATUS, WIDGET_LAST_ISSUES,
			WIDGET_WEB_OVERVIEW, WIDGET_DISCOVERY_STATUS, WIDGET_HOST_STATUS,
			WIDGET_FAVOURITE_GRAPHS, WIDGET_FAVOURITE_MAPS, WIDGET_FAVOURITE_SCREENS
		);

		$fields = array(
			'widget' =>			'fatal|required|in '.implode(',', $widgets),
			'refreshrate' =>	'fatal         |in 10,30,60,120,600,900',
			'state' =>			'fatal         |in 0,1'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(array('main_block' => '')));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$widget = $this->getInput('widget');

		$data = array(
			'main_block' => ''
		);

		// refresh rate
		if ($this->hasInput('refreshrate')) {
			$refreshrate = $this->getInput('refreshrate');

			CProfile::update('web.dashboard.widget.'.$widget.'.rf_rate', $refreshrate, PROFILE_TYPE_INT);

			$data['main_block'] =
				'PMasters["dashboard"].dolls["'.$widget.'"].frequency('.CJs::encodeJson($refreshrate).');'."\n".
				'PMasters["dashboard"].dolls["'.$widget.'"].restartDoll();';
		}

		// widget state
		if ($this->hasInput('state')) {
			CProfile::update('web.dashboard.widget.'.$widget.'.state', $this->getInput('state'), PROFILE_TYPE_INT);
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
