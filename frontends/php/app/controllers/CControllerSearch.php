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

	/**
	 * Validates input according to validation rules.
	 * Only validated fields are readable, remaining fields are unset.
	 *
	 * @return bool
	 */
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
	 * Preforms API request and returns ordered result
	 * based on search query.
	 *
	 * @param string $search  Initial search query.
	 *
	 * @return array|HostGroup[]  Exact matches are hoisted.
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
	 * Preforms API request and returns ordered result
	 * based on search query.
	 *
	 * @param string $search  Initial search query.
	 *
	 * @return array|Template[]  Exact matches are hoisted.
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
	 * Preforms API request and returns ordered result
	 * based on search query.
	 *
	 * @param string $search  Initial search query.
	 *
	 * @return array|Host[]  Exact matches are hoisted.
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

	/**
	 * Performs additional API requests if meta information is needed.
	 * Prepares view-compatible format.
	 *
	 * @param string $search  Initial search query.
	 * @param array &$view  View object that is filled with results.
	 */
	protected function collectTemplatesViewData($search, array &$view) {
		$templates = $this->findTemplates($search);
		if (zbx_empty($templates)) {
			return;
		}
		$rw_templates = API::Template()->get([
			'output' => ['templateid'],
			'templateids' => zbx_objectValues($templates, 'templateid'),
			'editable' => true
		]);
		$view['rows'] = $templates;
		$view['editable_rows'] = zbx_toHash($rw_templates, 'templateid');
		$view['count'] = count($templates);
		$view['overall_count'] = API::Template()->get([
			'search' => ['host' => $search, 'name' => $search], 'countOutput' => true, 'searchByAny' => true
		]);
	}

	/**
	 * Performs additional API requests if meta information is needed.
	 * Prepares view-compatible format.
	 *
	 * @param string $search  Initial search query.
	 * @param array &$view  View object that is filled with results.
	 */
	protected function collectHostsGroupsViewData($search, array &$view) {
		$host_groups = $this->findHostGroups($search);
		if (zbx_empty($host_groups)) {
			return;
		}
		$rw_host_groups = API::HostGroup()->get([
			'output' => ['groupid'],
			'groupids' => zbx_objectValues($host_groups, 'groupid'),
			'editable' => true
		]);
		$view['rows'] = $host_groups;
		$view['editable_rows'] = zbx_toHash($rw_host_groups, 'groupid');
		$view['count'] = count($host_groups);
		$view['overall_count'] = API::HostGroup()->get([
			'search' => ['name' => $search],
			'countOutput' => true
		]);
	}

	/**
	 * Performs additional API requests if meta information is needed.
	 * Prepares view-compatible format.
	 *
	 * @param string $search  Initial search query.
	 * @param array &$view  View object that is filled with results.
	 */
	protected function collectHostsViewData($search, array &$view) {
		$hosts = $this->findHosts($search);
		if (zbx_empty($hosts)) {
			return;
		}
		$rw_hosts = API::Host()->get([
			'output' => ['hostid'],
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'editable' => true
		]);
		$view['rows'] = $hosts;
		$view['count'] = count($hosts);
		$view['editable_rows'] = zbx_toHash($rw_hosts, 'hostid');
		$view['overall_count'] = API::Host()->get([
			'search' => ['host' => $search, 'name' => $search, 'dns' => $search, 'ip' => $search],
			'countOutput' => true, 'searchByAny' => true
		]);
	}

	/**
	 * Based on search query gathers view data.
	 *
	 * @param string $search  Initial search query.
	 *
	 * @return array  View-compatible object.
	 */
	protected function getViewData($search) {
		$view_table = ['rows' => [], 'editable_rows' => [], 'overall_count' => 0, 'count' => 0];
		$view_data = [
			'search' => _('Search pattern is empty'),
			'admin' => $this->admin,
			'hosts' => ['hat' => 'web.search.hats.'.WIDGET_SEARCH_HOSTS.'.state'] + $view_table,
			'host_groups' => ['hat' => 'web.search.hats.'.WIDGET_SEARCH_HOSTGROUP.'.state'] + $view_table,
			'templates' => ['hat' => 'web.search.hats.'.WIDGET_SEARCH_TEMPLATES.'.state'] + $view_table
		];

		if ($search !== '') {
			$view_data['search'] = $search;

			$this->collectHostsViewData($search, $view_data['hosts']);
			$this->collectHostsGroupsViewData($search, $view_data['host_groups']);

			if ($this->admin) {
				$this->collectTemplatesViewData($search, $view_data['templates']);
			}
		}

		return $view_data;
	}

	/**
	 * Creates and sets response object with data.
	 */
	protected function doAction() {
		$search = trim($this->getInput('search', ''));

		$response = new CControllerResponseData($this->getViewData($search));
		$response->setTitle(_('Search'));
		$this->setResponse($response);
	}
}
