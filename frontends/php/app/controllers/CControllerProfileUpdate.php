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
				case 'web.actionconf.filter.active':
				case 'web.auditacts.filter.active':
				case 'web.auditlogs.filter.active':
				case 'web.avail_report.filter.active':
				case 'web.charts.filter.active':
				case 'web.correlation.filter.active':
				case 'web.discoveryconf.filter.active':
				case 'web.groups.filter.active':
				case 'web.hostinventories.filter.active':
				case 'web.hostscreen.filter.active':
				case 'web.history.filter.active':
				case 'web.httpconf.filter.active':
				case 'web.httpdetails.filter.active':
				case 'web.hosts.filter.active':
				case 'web.items.filter.active':
				case 'web.item.graph.filter.active':
				case 'web.latest.filter.active':
				case 'web.maintenance.filter.active':
				case 'web.media_types.filter.active':
				case 'web.overview.filter.active':
				case 'web.problem.filter.active':
				case 'web.proxies.filter.active':
				case 'web.scripts.filter.active':
				case 'web.templates.filter.active':
				case 'web.toptriggers.filter.active':
				case 'web.triggers.filter.active':
				case 'web.screens.filter.active':
				case 'web.screenconf.filter.active':
				case 'web.slides.filter.active':
				case 'web.slideconf.filter.active':
				case 'web.sysmapconf.filter.active':
				case 'web.user.filter.active':
				case 'web.usergroup.filter.active':
				case 'web.dashbrd.filter.active':
					$ret = true;
					break;

				case 'web.latest.toggle':
				case 'web.latest.toggle_other':
				case 'web.problem.filter':
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
