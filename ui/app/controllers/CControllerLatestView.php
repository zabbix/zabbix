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


/**
 * Controller for the "Latest data" page.
 */
class CControllerLatestView extends CControllerLatest {

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
			'filter_set' =>					'in 1',
			'filter_rst' =>					'in 1',

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
		return $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA);
	}

	protected function doAction() {
		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.latest.filter.groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.latest.filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
			CProfile::update('web.latest.filter.application', trim($this->getInput('filter_application', '')),
				PROFILE_TYPE_STR
			);
			CProfile::update('web.latest.filter.select', trim($this->getInput('filter_select', '')), PROFILE_TYPE_STR);
			CProfile::update('web.latest.filter.show_without_data', $this->getInput('filter_show_without_data', 0),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.latest.filter.show_details', $this->getInput('filter_show_details', 0),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.latest.filter.groupids');
			CProfile::deleteIdx('web.latest.filter.hostids');
			CProfile::delete('web.latest.filter.application');
			CProfile::delete('web.latest.filter.select');
			CProfile::delete('web.latest.filter.show_without_data');
			CProfile::delete('web.latest.filter.show_details');
		}

		// Force-check "Show items without data" if there are no hosts selected.
		$filter_hostids = CProfile::getArray('web.latest.filter.hostids');
		$filter_show_without_data = $filter_hostids ? CProfile::get('web.latest.filter.show_without_data', 1) : 1;

		$filter = [
			'groupids' => CProfile::getArray('web.latest.filter.groupids'),
			'hostids' => $filter_hostids,
			'application' => CProfile::get('web.latest.filter.application', ''),
			'select' => CProfile::get('web.latest.filter.select', ''),
			'show_without_data' => $filter_show_without_data,
			'show_details' => CProfile::get('web.latest.filter.show_details', 0)
		];

		$sort_field = $this->getInput('sort', CProfile::get('web.latest.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.latest.sortorder', ZBX_SORT_UP));

		CProfile::update('web.latest.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.latest.sortorder', $sort_order, PROFILE_TYPE_STR);

		$view_curl = (new CUrl('zabbix.php'))->setArgument('action', 'latest.view');

		$refresh_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'latest.view.refresh')
			->setArgument('filter_groupids', $filter['groupids'])
			->setArgument('filter_hostids', $filter['hostids'])
			->setArgument('filter_application', $filter['application'])
			->setArgument('filter_select', $filter['select'])
			->setArgument('filter_show_without_data', $filter['show_without_data'] ? 1 : null)
			->setArgument('filter_show_details', $filter['show_details'] ? 1 : null)
			->setArgument('sort', $sort_field)
			->setArgument('sortorder', $sort_order)
			->setArgument('page', $this->hasInput('page') ? $this->getInput('page') : null);

		// data sort and pager
		$prepared_data = $this->prepareData($filter, $sort_field, $sort_order);

		$paging = CPagerHelper::paginate(getRequest('page', 1), $prepared_data['rows'], ZBX_SORT_UP, $view_curl);

		$this->extendData($prepared_data, $filter['show_without_data']);
		$this->addCollapsedDataFromProfile($prepared_data);

		// display
		$data = [
			'filter' => $filter,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'view_curl' => $view_curl,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'active_tab' => CProfile::get('web.latest.filter.active', 1),
			'paging' => $paging,
			'config' => [
				'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS),
				'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
				'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
				'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)
			]
		] + $prepared_data;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Latest data'));
		$this->setResponse($response);
	}
}
