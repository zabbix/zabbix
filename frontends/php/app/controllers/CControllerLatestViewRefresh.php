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


/**
 * Controller for the "Latest data" asynchronous refresh page.
 */
class CControllerLatestViewRefresh extends CControllerLatest {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'page' =>						'ge 1',

			// filter inputs
			'filter_groupids' =>			'array_id',
			'filter_hostids' =>				'array_id',
			'filter_application' =>			'string',
			'filter_select' =>				'string',
			'filter_show_without_data' =>	'in 1',
			'filter_show_details' =>		'in 1',

			// table sorting inputs
			'sort' =>						'in host,name,lastclock',
			'sortorder' =>					'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP
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

	protected function doAction() {
		// filter
		$filter = [
			'groupids' => $this->hasInput('filter_groupids') ? $this->getInput('filter_groupids') : null,
			'hostids' => $this->hasInput('filter_hostids') ? $this->getInput('filter_hostids') : null,
			'application' => $this->getInput('filter_application', ''),
			'select' => $this->getInput('filter_select', ''),
			'show_without_data' => $this->getInput('filter_show_without_data', 0),
			'show_details' => $this->getInput('filter_show_details', 0)
		];

		$sort_field = $this->getInput('sort', 'name');
		$sort_order = $this->getInput('sortorder', ZBX_SORT_UP);

		$view_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'latest.view')
			->setArgument('filter_groupids', $filter['groupids'])
			->setArgument('filter_hostids', $filter['hostids'])
			->setArgument('filter_application', $filter['application'])
			->setArgument('filter_select', $filter['select'])
			->setArgument('filter_show_without_data', $filter['show_without_data'] ? 1 : null)
			->setArgument('filter_show_details', $filter['show_details'] ? 1 : null)
			->setArgument('filter_set', 1)
			->setArgument('sort', $sort_field)
			->setArgument('sortorder', $sort_order);

		// display
		$data = [
			'filter' => $filter,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'view_curl' => $view_curl
		] + $this->prepareData($filter, $sort_field, $sort_order);

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
