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


class CControllerProfileUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'idx' =>		'required|string',
			'value_int' =>	'required|int32',
			'idx2' =>		'array_id'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('idx')) {
				case 'web.actionconf.filter.state':
				case 'web.auditacts.filter.state':
				case 'web.auditlogs.filter.state':
				case 'web.avail_report.filter.state':
				case 'web.charts.filter.state':
				case 'web.correlation.filter.state':
				case 'web.discoveryconf.filter.state':
				case 'web.groups.filter.state':
				case 'web.hostinventories.filter.state':
				case 'web.hostscreen.filter.state':
				case 'web.history.filter.state':
				case 'web.httpconf.filter.state':
				case 'web.httpdetails.filter.state':
				case 'web.hosts.filter.state':
				case 'web.items.filter.state':
				case 'web.latest.filter.state':
				case 'web.maintenance.filter.state':
				case 'web.media_types.filter.state':
				case 'web.overview.filter.state':
				case 'web.problem.filter.state':
				case 'web.proxies.filter.state':
				case 'web.scripts.filter.state':
				case 'web.templates.filter.state':
				case 'web.toptriggers.filter.state':
				case 'web.triggers.filter.state':
				case 'web.screens.filter.state':
				case 'web.screenconf.filter.state':
				case 'web.slides.filter.state':
				case 'web.slideconf.filter.state':
				case 'web.sysmapconf.filter.state':
				case 'web.user.filter.state':
				case 'web.usergroup.filter.state':
				case 'web.dashbrd.filter.state':
					$ret = true;
					break;

				case 'web.latest.toggle':
				case 'web.latest.toggle_other':
				case 'web.dashbrd.timelinefixed':
				case 'web.screens.timelinefixed':
				case 'web.graphs.timelinefixed':
				case 'web.httptest.timelinefixed':
				case 'web.problem.timeline':
				case 'web.auditacts.timelinefixed':
				case 'web.auditlogs.timelinefixed':
				case 'web.item.graph.timelinefixed':
				case 'web.slides.timelinefixed':
				case 'web.dashbrd.navtree.item.selected':
				case !!preg_match('/web.dashbrd.navtree-\d+.toggle/', $this->getInput('idx')):
					$ret = $this->hasInput('idx2');
					break;

				default:
					$ret = false;
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

		DBstart();
		switch ($idx) {
			case 'web.latest.toggle':
			case 'web.latest.toggle_other':
			case 'web.dashbrd.timelinefixed':
			case !!preg_match('/web.dashbrd.navtree-\d+.toggle/', $this->getInput('idx')):
				if ($value_int == 1) { // default value
					CProfile::delete($idx, $this->getInput('idx2'));
				}
				else {
					foreach ($this->getInput('idx2') as $idx2) {
						CProfile::update($idx, $value_int, PROFILE_TYPE_INT, $idx2);
					}
				}
				break;

			case 'web.dashbrd.navtree.item.selected':
				foreach ($this->getInput('idx2') as $idx2) {
					CProfile::update($idx, $value_int, PROFILE_TYPE_INT, $idx2);
				}
				break;

			default:
				if ($value_int == 1) { // default value
					CProfile::delete($idx);
				}
				else {
					CProfile::update($idx, $value_int, PROFILE_TYPE_INT);
				}
		}
		DBend();

		$this->setResponse(new CControllerResponseData(['main_block' => '']));
	}
}
