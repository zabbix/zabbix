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


class CControllerSearch extends CController {

	protected function init() {
		$this->disableSIDValidation();

		$this->admin = in_array(CWebUser::$data['type'], [
			USER_TYPE_ZABBIX_ADMIN,
			USER_TYPE_SUPER_ADMIN
		]);
	}

	protected function checkInput() {
		$fields = [
			'search' => 'string'
		];

		$ret = $this->validateInput($fields);
		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	/**
	 * Preforms api request and returns ordered result
	 * based on serach query.
	 *
	 * @param $search string  search term
	 * @return array HostGroup[]  ordered by search term
	 */
	protected function findHostGroups($search) {
		$host_groups = API::HostGroup()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_COUNT,
			'selectTemplates' => API_OUTPUT_COUNT,
			'search' => ['name' => $search],
			'limit' => CWebUser::$data['rows_per_page'],
		]);
		order_result($host_groups, 'name');

		return selectByPattern($host_groups, 'name', $search, CWebUser::$data['rows_per_page']);
	}

	/**
	 * Preforms api request and returns ordered result
	 * based on serach query.
	 *
	 * @param $search string  search term
	 * @return array Template[]  ordered by search term
	 */
	protected function findTemplates($search) {
		$templates = API::Template()->get([
			'output' => ['name', 'host'],
			'selectGroups' => ['groupid'],
			'sortfield' => 'name',
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectApplications' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'search' => [
				'host' => $search,
				'name' => $search
			],
			'searchByAny' => true,
			'limit' => CWebUser::$data['rows_per_page'],
		]);
		order_result($templates, 'name');

		return selectByPattern($templates, 'name', $search, CWebUser::$data['rows_per_page']);
	}

	/**
	 * Preforms api request and returns ordered result
	 * based on serach query.
	 *
	 * @param $search string  search term
	 * @return array Host[]  ordered by search term
	 */
	protected function findHosts($search) {
		$hosts = API::Host()->get([
			'search' => [
				'host' => $search,
				'name' => $search,
				'dns' => $search,
				'ip' => $search
			],
			'limit' => CWebUser::$data['rows_per_page'],
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_COUNT,
			'selectTriggers' => API_OUTPUT_COUNT,
			'selectGraphs' => API_OUTPUT_COUNT,
			'selectApplications' => API_OUTPUT_COUNT,
			'selectScreens' => API_OUTPUT_COUNT,
			'selectHttpTests' => API_OUTPUT_COUNT,
			'selectDiscoveries' => API_OUTPUT_COUNT,
			'output' => ['name', 'status', 'host'],
			'searchByAny' => true
		]);
		order_result($hosts, 'name');

		return selectByPattern($hosts, 'name', $search, CWebUser::$data['rows_per_page']);
	}

	protected function doAction() {
		$search = trim($this->getInput('search', ''));

		$hosts = $this->findHosts($search);
		$rw_hosts = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'editable' => true
		]);
		$view_hosts = [
			'rows' => $hosts,
			'editable_rows' => zbx_toHash($rw_hosts, 'hostid'),
			'count' => count($hosts),
			'overall_count' => API::Host()->get([
				'search' => ['host' => $search, 'name' => $search, 'dns' => $search, 'ip' => $search],
				'countOutput' => true, 'searchByAny' => true
			]),
			'hat' => 'web.search.hats.'.WIDGET_SEARCH_HOSTS.'.state',
		];

		$host_groups = $this->findHostGroups($search);
		$rw_host_groups = API::HostGroup()->get([
			'output' => ['groupid'],
			'groupids' => zbx_objectValues($host_groups, 'groupid'),
			'editable' => true
		]);
		$view_host_groups = [
			'rows' => $host_groups,
			'editable_rows' => zbx_toHash($rw_host_groups, 'groupid'),
			'count' => count($host_groups),
			'overall_count' => API::HostGroup()->get([
				'search' => ['name' => $search],
				'countOutput' => true
			]),
			'hat' => 'web.search.hats.'.WIDGET_SEARCH_HOSTGROUP.'.state',
		];

		if ($this->admin) {
			$templates = $this->findTemplates($search);
			$rw_templates = API::Template()->get([
				'output' => ['templateid'],
				'templateids' => zbx_objectValues($templates, 'templateid'),
				'editable' => true
			]);
			$view_templates = [
				'rows' => $templates,
				'editable_rows' => zbx_toHash($rw_templates, 'templateid'),
				'count' => count($templates),
				'overall_count' => API::Template()->get([
					'search' => ['host' => $search, 'name' => $search], 'countOutput' => true, 'searchByAny' => true
				]),
				'hat' => 'web.search.hats.'.WIDGET_SEARCH_TEMPLATES.'.state',
			];
		}

		$response = new CControllerResponseData([
			'search' => $search === '' ? _('Search pattern is empty') : $search,
			'admin' => $this->admin,
			'hosts' => $view_hosts,
			'host_groups' => $view_host_groups,
			'templates' => $this->admin ? $view_templates : null
		]);
		$response->setTitle(_('Search'));
		$this->setResponse($response);
	}
}
