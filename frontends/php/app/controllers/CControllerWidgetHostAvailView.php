<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerWidgetHostAvailView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_HOST_AVAIL);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json',
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$filter_groupids = $fields['groupids'] ? getSubGroups($fields['groupids']) : null;
		$filter_maintenance = ($fields['maintenance'] == HOST_MAINTENANCE_STATUS_OFF) ? HOST_MAINTENANCE_STATUS_OFF : null;

		$hostsids = $this->getHostIds($filter_groupids, $filter_maintenance);

		$hosts_count = $this->getHostStatusCount($hostsids);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'layout' => $fields['layout'],
			'hosts' => $hosts_count,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	protected function getHostIds($groups, $maintenance) {
		$hosts = API::Host()->get([
			'output' => ['hostid'],
			'groupids' => $groups,
			'filter' => ['maintenance_status' => $maintenance],
			'preservekeys' => true
		]);

		return array_keys($hosts);
	}

	protected function getHostStatusCount(array $ids) {
		$arr = [];

		$query = DBselect(
			'SELECT COUNT(DISTINCT h.hostid) AS cnt, h.available'.
			' FROM hosts h'.
			' WHERE '.dbConditionInt('h.available', [HOST_AVAILABLE_TRUE, HOST_AVAILABLE_FALSE, HOST_AVAILABLE_UNKNOWN]).
				' AND '.dbConditionInt('h.status', [HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED]).
				' AND '.dbConditionInt('h.hostid', $ids).
			' GROUP BY h.available'
		);
		while ($row = DBfetch($query)) {
			$arr[$row['available']] = $row['cnt'];
		}

		return $arr;
	}
}
