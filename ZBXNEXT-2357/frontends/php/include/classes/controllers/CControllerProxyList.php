<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CControllerProxyList extends CController {

	private $data;

	public function __construct() {
		parent::__construct();
	}

	protected function checkPermissions() {
	}

	protected function doAction() {
		validate_sort_and_sortorder('host', ZBX_SORT_UP, array('host'));

// TODO select_config() should accept name of config parameter
		$config = select_config();

		$this->data['config']['max_in_table'] = $config['max_in_table'];

		$sortfield = getPageSortField('host');

		$this->data['proxies'] = API::Proxy()->get(array(
			'editable' => true,
			'selectHosts' => array('hostid', 'host', 'name', 'status'),
// TODO list all fields we need
// TODO remove all isset() from view
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		));

		$this->data['proxies'] = zbx_toHash($this->data['proxies'], 'proxyid');

		$proxyIds = array_keys($this->data['proxies']);

		// sorting & paging
		order_result($this->data['proxies'], $sortfield, getPageSortOrder());
		$this->data['paging'] = getPagingLine($this->data['proxies']);

		// calculate performance
		$dbPerformance = DBselect(
			'SELECT h.proxy_hostid,SUM(1.0/i.delay) AS qps'.
			' FROM items i,hosts h'.
			' WHERE i.status='.ITEM_STATUS_ACTIVE.
				' AND i.hostid=h.hostid'.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND i.delay<>0'.
				' AND i.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE.
				' AND '.dbConditionInt('h.proxy_hostid', $proxyIds).
			' GROUP BY h.proxy_hostid'
		);
		while ($performance = DBfetch($dbPerformance)) {
			if (isset($this->data['proxies'][$performance['proxy_hostid']])) {
				$this->data['proxies'][$performance['proxy_hostid']]['perf'] = round($performance['qps'], 2);
			}
		}

		// get items
		$items = API::Item()->get(array(
			'proxyids' => $proxyIds,
			'groupCount' => true,
			'countOutput' => true,
			'webitems' => true,
			'monitored' => true
		));
		foreach ($items as $item) {
			if (isset($this->data['proxies'][$item['proxy_hostid']])) {
				if (!isset($this->data['proxies'][$item['proxy_hostid']]['item_count'])) {
					$this->data['proxies'][$item['proxy_hostid']]['item_count'] = 0;
				}

				$this->data['proxies'][$item['proxy_hostid']]['item_count'] += $item['rowscount'];
			}
		}

		return new CControllerResponseData($this->data);
	}
}
