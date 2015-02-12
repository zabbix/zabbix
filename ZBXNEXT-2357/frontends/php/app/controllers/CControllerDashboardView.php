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

class CControllerDashboardView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = array(
			'fullscreen' =>	'in 0,1'
		);

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
		$show_discovery_widget = ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN && (bool) API::DRule()->get(array(
			'output' => array(),
			'filter' => array('status' => DRULE_STATUS_ACTIVE),
			'limit' => 1
		)));

		$data = array(
			'fullscreen' => $this->getInput('fullscreen', 0),
			'filter_enabled' => CProfile::get('web.dashconf.filter.enable', 0),
			'favourite_graphs' => getFavouriteGraphs(),
			'favourite_maps' => getFavouriteMaps(),
			'favourite_screens' => getFavouriteScreens(),
			'show_status_widget' => ($this->getUserType() == USER_TYPE_SUPER_ADMIN),
			'show_discovery_widget' => $show_discovery_widget
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Dashboard'));
		$this->setResponse($response);
	}
}
