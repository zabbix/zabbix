<?php
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


class CControllerWebView extends CController {

	/**
	 * @var array
	 */
	protected $groups = [];

	/**
	 * @var array
	 */
	protected $hosts = [];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'filter_groupids' => 'array_db hstgrp.groupid',
			'filter_hostids'  => 'array_db hosts.hostid',
			'sort'            => 'in hostname,name',
			'sortorder'       => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'filter_rst'      => 'in 1',
			'filter_set'      => 'in 1',
			'page'            => 'ge 1'
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

		if ($this->getInput('filter_groupids', [])) {
			$this->groups = API::HostGroup()->get([
				'output' => ['name', 'groupid'],
				'groupids' => $this->getInput('filter_groupids')
			]);

			if (count($this->groups) != count($this->getInput('filter_groupids'))) {
				return false;
			}
		}

		if ($this->getInput('filter_hostids', [])) {
			$this->hosts = API::Host()->get([
				'output' => ['name', 'hostid'],
				'hostids' => $this->getInput('filter_hostids')
			]);

			if (count($this->hosts) != count($this->getInput('filter_hostids'))) {
				return false;
			}
		}

		return true;
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.httpmon.php.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.httpmon.php.sortorder', ZBX_SORT_UP));

		if ($this->hasInput('filter_set')) {
			/* CProfile::get('web.httpmon.php.sort', 'name'); */
			/* $this->getInput('filter_hostids'); */
			// TODO:
		}

		if ($this->hasInput('filter_rst')) {
			// TODO:
		}

		CProfile::update('web.httpmon.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.httpmon.sortorder', $sort_order, PROFILE_TYPE_STR);

		CView::$has_web_layout_mode = true;

		$data = [];
		$data['ms_hosts'] = $this->hosts;
		$data['ms_groups'] = $this->groups;

		$data['screen_view'] = CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_HTTPTEST,
			'mode' => SCREEN_MODE_JS,
			'dataId' => 'httptest',
			'groupid' => $this->hasInput('filter_groupids') ? $this->getInput('filter_groupids') : null,
			'hostid' => $this->hasInput('filter_hostids') ? $this->getInput('filter_hostids') : null,
			'page' => $this->getInput('page', 1),
			'data' => [
				'sort' => $sort_field,
				'sortorder' => $sort_order,
				'groupids' => $this->hasInput('filter_groupids') ? $this->getInput('filter_groupids') : null,
			]
		])->get();

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Web monitoring'));
		$this->setResponse($response);
	}
}
