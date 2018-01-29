<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class CControllerWidgetDiscoveryView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_DISCOVERY_STATUS);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			$drules = API::DRule()->get([
				'output' => ['druleid', 'name'],
				'selectDHosts' => ['status'],
				'filter' => ['status' => DHOST_STATUS_ACTIVE]
			]);
			CArrayHelper::sort($drules, ['name']);

			foreach ($drules as &$drule) {
				$drule['up'] = 0;
				$drule['down'] = 0;

				foreach ($drule['dhosts'] as $dhost){
					if ($dhost['status'] == DRULE_STATUS_DISABLED) {
						$drule['down']++;
					}
					else {
						$drule['up']++;
					}
				}
			}
			unset($drule);

			$error = null;
		}
		else {
			$drules = [];
			$error = _('No permissions to referred object or it does not exist!');
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'drules' => $drules,
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
