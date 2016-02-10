<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CControllerProfileUpdate extends CController {

	const VALUE_INT = 0x01;
	const VALUE_STR = 0x02;

	protected function checkInput() {
		$fields = [
			'idx' =>		'fatal|required',
			'value_int' =>	'int32'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$mask = $this->hasInput('value_int') ? self::VALUE_INT : 0x00;
			$mask |= $this->hasInput('value_str') ? self::VALUE_STR : 0x00;
			switch ($this->getInput('idx')) {
				case 'web.auditacts.filter.state':
				case 'web.auditlogs.filter.state':
				case 'web.avail_report.filter.state':
				case 'web.charts.filter.state':
				case 'web.events.filter.state':
				case 'web.hostinventories.filter.state':
				case 'web.hostscreen.filter.state':
				case 'web.history.filter.state':
				case 'web.httpconf.filter.state':
				case 'web.httpdetails.filter.state':
				case 'web.hosts.filter.state':
				case 'web.items.filter.state':
				case 'web.latest.filter.state':
				case 'web.overview.filter.state':
				case 'web.toptriggers.filter.state':
				case 'web.triggers.filter.state':
				case 'web.tr_status.filter.state':
				case 'web.screens.filter.state':
				case 'web.screenconf.filter.state':
				case 'web.slides.filter.state':
				case 'web.slideconf.filter.state':
				case 'web.sysmapconf.filter.state':
					$ret = ($mask == self::VALUE_INT && in_array($this->getInput('value_int'), [0, 1]));
					break;
				default:
					$ret = false;
					break;
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => '']));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$idx = $this->getInput('idx');
		$value_int = $this->getInput('value_int');

		$data = [];

		DBstart();
		CProfile::update($idx, $value_int, PROFILE_TYPE_INT);
		$result = DBend();

		if ($result) {
			$data['main_block'] = '';
		}
		else {
			$data['main_block'] = '';
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
