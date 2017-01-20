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


class CControllerProxyList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'sort' =>		'in host',
			'sortorder' =>	'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'uncheck' =>	'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.proxies.php.sort', 'host'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.proxies.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.proxies.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.proxies.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$config = select_config();

		$data = [
			'uncheck' => $this->hasInput('uncheck'),
			'sort' => $sortField,
			'sortorder' => $sortOrder,
			'config' => [
				'max_in_table' => $config['max_in_table']
			]
		];

		$data['proxies'] = API::Proxy()->get([
			'output' => ['proxyid', 'host', 'status', 'lastaccess', 'tls_connect', 'tls_accept'],
			'selectHosts' => ['hostid', 'name', 'status'],
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1,
			'editable' => true,
			'preservekeys' => true
		]);
		// sorting & paging
		order_result($data['proxies'], $sortField, $sortOrder);

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'proxy.list');

		$data['paging'] = getPagingLine($data['proxies'], $sortOrder, $url);

		foreach ($data['proxies'] as &$proxy) {
			order_result($proxy['hosts'], 'name');
		}
		unset($proxy);

		// get proxy IDs for a *selected* page
		$proxyIds = array_keys($data['proxies']);

		if ($proxyIds) {
			// calculate performance
			$dbPerformance = DBselect(
				'SELECT h.proxy_hostid,SUM(1.0/i.delay) AS qps'.
				' FROM hosts h,items i'.
				' WHERE h.hostid=i.hostid'.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND i.delay<>0'.
					' AND i.flags<>'.ZBX_FLAG_DISCOVERY_PROTOTYPE.
					' AND '.dbConditionInt('h.proxy_hostid', $proxyIds).
				' GROUP BY h.proxy_hostid'
			);
			while ($performance = DBfetch($dbPerformance)) {
				$data['proxies'][$performance['proxy_hostid']]['perf'] = round($performance['qps'], 2);
			}

			// get items
			$items = API::Item()->get([
				'proxyids' => $proxyIds,
				'groupCount' => true,
				'countOutput' => true,
				'webitems' => true,
				'monitored' => true
			]);
			foreach ($items as $item) {
				$data['proxies'][$item['proxy_hostid']]['item_count'] = $item['rowscount'];
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of proxies'));
		$this->setResponse($response);
	}
}
