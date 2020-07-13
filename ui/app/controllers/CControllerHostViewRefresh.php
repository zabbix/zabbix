<?php declare(strict_types = 1);

/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


use Services\FilterCollection;
use Services\DataProviders\HostDataProvider;
/**
 * Controller for the "Host->Monitoring" asynchronous refresh page.
 */
class CControllerHostViewRefresh extends CControllerHostView {

	protected function doAction(): void {
		$config = select_config();
		$filter_collection = new FilterCollection(CWebUser::$data['userid'], 'web.monitoring.hosts');
		$filter_collection->setDefaultProvider(HostDataProvider::PROVIDER_TYPE);
		$filter_collection->init();
		$data_provider = $filter_collection->getActiveDataProvider();
		$filter = [
			'name' => $this->getInput('filter_name', ''),
			'groupids' => $this->hasInput('filter_groupids') ? $this->getInput('filter_groupids') : null,
			'ip' => $this->getInput('filter_ip', ''),
			'dns' => $this->getInput('filter_dns', ''),
			'port' => $this->getInput('filter_port', ''),
			'status' => $this->getInput('filter_status', -1),
			'evaltype' => $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => $this->getInput('filter_tags', []),
			'severities' => $this->getInput('filter_severities', []),
			'show_suppressed' => $this->getInput('filter_show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
			'maintenance_status' => $this->getInput('filter_maintenance_status', HOST_MAINTENANCE_STATUS_ON),
			'page' => $this->hasInput('page') ? $this->getInput('page') : null,
			'sort' => $this->getInput('sort', 'name'),
			'sortorder' => $this->getInput('sortorder', ZBX_SORT_UP),
			'view_curl' => (new CUrl('zabbix.php'))->setArgument('action', 'host.view.refresh'),
			'limit' => $config['search_limit'] + 1
		];
		$filter['view_curl'] = (new CUrl('zabbix.php'))
			->setArgument('action', 'host.view.refresh')
			->setArgument('sort', $filter['sort'])
			->setArgument('sortorder', $filter['sortorder']);

		$data_provider->updateFields($filter);
		$hosts = $data_provider->getData();
		$tags = makeTags($hosts, true, 'hostid', ZBX_TAG_COUNT_DEFAULT, $filter['tags']);

		foreach ($hosts as &$host) {
			$host['tags'] = $tags[$host['hostid']];
		}
		unset($host);

		$response = new CControllerResponseData([
			'filter' => $filter,
			'paging' => $data_provider->getPaging(),
			'hosts' => $hosts,
			'config' => $config
		]);
		$this->setResponse($response);
	}
}
