<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CControllerWebView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'fullscreen' =>	'in 0,1',
			'groupid' =>	'db groups.groupid',
			'hostid' =>		'db hosts.hostid',
			'sort' =>		'in hostname,name',
			'sortorder' =>	'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
			return false;
		}
		if ($this->hasInput('groupid') && $this->getInput('groupid') != 0
				&& !API::HostGroup()->isReadable([$this->getInput('groupid')])) {
			return false;
		}
		if ($this->hasInput('hostid') && $this->getInput('hostid') != 0
				&& !API::Host()->isReadable([$this->getInput('hostid')])) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$sortField = $this->getInput('sort', CProfile::get('web.httpmon.php.sort', 'name'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.httpmon.php.sortorder', ZBX_SORT_UP));

		CProfile::update('web.httpmon.php.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.httpmon.php.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$data = [
			'fullscreen' => $this->getInput('fullscreen', 0),
			'httptests' => [],
			'paging' => null,
			'sort' => $sortField,
			'sortorder' => $sortOrder
		];

		$data['pageFilter'] = new CPageFilter([
			'groups' => [
				'real_hosts' => true,
				'with_httptests' => true
			],
			'hosts' => [
				'with_monitored_items' => true,
				'with_httptests' => true
			],
			'hostid' => $this->getInput('hostid', 0),
			'groupid' => $this->getInput('groupid', 0),
		]);

		$data['hostid']= $data['pageFilter']->hostid;
		$data['groupid'] = $data['pageFilter']->groupid;

		if ($data['pageFilter']->hostsSelected) {
			$config = select_config();

			$options = [
				'output' => ['httptestid', 'name', 'hostid'],
				'selectHosts' => ['name', 'status'],
				'selectSteps' => API_OUTPUT_COUNT,
				'templated' => false,
				'preservekeys' => true,
				'filter' => ['status' => HTTPTEST_STATUS_ACTIVE],
				'limit' => $config['search_limit'] + 1
			];
			if ($data['hostid'] > 0) {
				$options['hostids'] = $data['hostid'];
			}
			elseif ($data['groupid'] > 0) {
				$options['groupids'] = $data['groupid'];
			}
			$httptests = API::HttpTest()->get($options);

			foreach ($httptests as &$httptest) {
				$httptest['host'] = reset($httptest['hosts']);
				$httptest['hostname'] = $httptest['host']['name'];
				unset($httptest['hosts']);
			}
			unset($httptest);

			order_result($httptests, $sortField, $sortOrder);
			$data['paging'] = getPagingLine($httptests, $sortOrder);
			$httptests = resolveHttpTestMacros($httptests, true, false);
			order_result($httptests, $sortField, $sortOrder);

			// fetch the latest results of the web scenario
			$lastHttpTestData = Manager::HttpTest()->getLastData(array_keys($httptests));

			foreach ($httptests as &$httptest) {
				if (isset($lastHttpTestData[$httptest['httptestid']])) {
					$httptest['lastcheck'] = $lastHttpTestData[$httptest['httptestid']]['lastcheck'];
					$httptest['lastfailedstep'] = $lastHttpTestData[$httptest['httptestid']]['lastfailedstep'];
					$httptest['error'] = $lastHttpTestData[$httptest['httptestid']]['error'];
				}

				$data['httptests'][] = $httptest;
			}
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Web monitoring'));
		$this->setResponse($response);
	}
}
