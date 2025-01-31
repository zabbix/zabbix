<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerProfileUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'idx' =>		'required|string',
			'value_int' =>	'int32',
			'value_str' =>	'string',
			'idx2' =>		'array_id'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			switch ($this->getInput('idx')) {
				case 'web.action.list.filter.active':
				case 'web.actionlog.filter.active':
				case 'web.auditacts.filter.active':
				case 'web.auditlog.filter.active':
				case 'web.availabilityreport.filter.active':
				case 'web.charts.filter.active':
				case 'web.connector.filter.active':
				case 'web.correlation.filter.active':
				case 'web.dashboard.filter.active':
				case 'web.dashboard.hostid':
				case 'web.dashboard.last_widget_type':
				case 'web.discovery.filter.active':
				case 'web.discoveryconf.filter.active':
				case 'web.hostgroups.filter.active':
				case 'web.hostinventories.filter.active':
				case 'web.hostinventoriesoverview.filter.active':
				case 'web.hosts.filter.active':
				case 'web.hosts.graphs.filter.active':
				case 'web.hosts.host_discovery.filter.active':
				case 'web.hosts.httpconf.filter.active':
				case 'web.hosts.items.list.filter.active':
				case 'web.hosts.trigger.list.filter.active':
				case 'web.hostsmon.filter.active':
				case 'web.httpdetails.filter.active':
				case 'web.item.graph.filter.active':
				case 'web.layout.mode':
				case 'web.maintenance.filter.active':
				case 'web.media_types.filter.active':
				case 'web.modules.filter.active':
				case 'web.problem.filter.active':
				case 'web.proxies.filter.active':
				case 'web.proxygroups.filter.active':
				case 'web.scheduledreport.filter.active':
				case 'web.scripts.filter.active':
				case 'web.search.hats.'.SECTION_SEARCH_HOSTS.'.state':
				case 'web.search.hats.'.SECTION_SEARCH_TEMPLATES.'.state':
				case 'web.search.hats.'.SECTION_SEARCH_HOSTGROUP.'.state':
				case 'web.service.filter.active':
				case 'web.service_actions.filter.active':
				case 'web.sidebar.mode':
				case 'web.sla.list.filter.active':
				case 'web.slareport.list.filter.active':
				case 'web.sysmapconf.filter.active':
				case 'web.templategroups.filter.active':
				case 'web.templates.filter.active':
				case 'web.templates.graphs.filter.active':
				case 'web.templates.host_discovery.filter.active':
				case 'web.templates.httpconf.filter.active':
				case 'web.templates.items.list.filter.active':
				case 'web.templates.trigger.list.filter.active':
				case 'web.token.filter.active':
				case 'web.toptriggers.filter.active':
				case 'web.tr_events.hats.'.SECTION_HAT_EVENTACTIONS.'.state':
				case 'web.tr_events.hats.'.SECTION_HAT_EVENTLIST.'.state':
				case 'web.user.filter.active':
				case 'web.user.token.filter.active':
				case 'web.usergroup.filter.active':
				case 'web.web.filter.active':
					$ret = true;
					break;

				case 'web.dashboard.widget.geomap.default_view':
				case 'web.dashboard.widget.geomap.severity_filter':
				case (bool) preg_match('/web.dashboard.widget.navtree.item-\d+.toggle/', $this->getInput('idx')):
				case 'web.dashboard.widget.navtree.item.selected':
					$ret = $this->hasInput('idx2');
					break;

				default:
					$ret = false;
			}
		}

		if ($ret) {
			switch ($this->getInput('idx')) {
				case 'web.dashboard.last_widget_type':
				case 'web.dashboard.widget.geomap.default_view':
				case 'web.dashboard.widget.geomap.severity_filter':
					$ret = $this->hasInput('value_str');
					break;

				default:
					$ret = $this->hasInput('value_int');
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

		DBstart();
		switch ($idx) {
			// PROFILE_TYPE_STR
			case 'web.dashboard.last_widget_type':
				$value_str = $this->getInput('value_str');
				if ($value_str === '') {
					CProfile::delete($idx);
				}
				else {
					CProfile::update($idx, $value_str, PROFILE_TYPE_STR);
				}
				break;
			case 'web.dashboard.widget.geomap.default_view':
			case 'web.dashboard.widget.geomap.severity_filter':
				$value_str = $this->getInput('value_str');
				if ($value_str === '') { // default value
					CProfile::delete($idx, $this->getInput('idx2'));
				}
				else {
					foreach ($this->getInput('idx2') as $idx2) {
						CProfile::update($idx, $value_str, PROFILE_TYPE_STR, $idx2);
					}
				}
				break;

			// PROFILE_TYPE_INT
			case (bool) preg_match('/web.dashboard.widget.navtree.item-\d+.toggle/', $this->getInput('idx')):
				$value_int = $this->getInput('value_int');
				if ($value_int == 1) { // default value
					CProfile::delete($idx, $this->getInput('idx2'));
				}
				else {
					foreach ($this->getInput('idx2') as $idx2) {
						CProfile::update($idx, $value_int, PROFILE_TYPE_INT, $idx2);
					}
				}
				break;

			case 'web.dashboard.widget.navtree.item.selected':
				$value_int = $this->getInput('value_int');
				foreach ($this->getInput('idx2') as $idx2) {
					CProfile::update($idx, $value_int, PROFILE_TYPE_INT, $idx2);
				}
				break;

			case 'web.layout.mode':
				$value_int = $this->getInput('value_int');
				CViewHelper::saveLayoutMode($value_int);
				break;

			case 'web.sidebar.mode':
				$value_int = $this->getInput('value_int');
				CViewHelper::saveSidebarMode($value_int);
				break;

			default:
				$value_int = $this->getInput('value_int');
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
