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


class CControllerLatestView extends CControllerLatest {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			// Filter inputs.
			'groupids' =>			'array_id',
			'hostids' =>			'array_id',
			'application' =>		'string',
			'select' =>				'string',
			'show_without_data' =>	'in 1',
			'show_details' =>		'in 1',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',

			// Table sorting inputs.
			'sort' =>				'in host,name,lastclock',
			'sortorder' =>			'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->hasInput('groupids') && !isReadableHostGroups($this->getInput('groupids'))) {
			return false;
		}

		if ($this->hasInput('hostids') && !isReadableHosts($this->getInput('hostids'))) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		/*
		 * Filter
		 */
		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.latest.filter.groupids', $this->getInput('groupids', []), PROFILE_TYPE_STR);
			CProfile::updateArray('web.latest.filter.hostids', $this->getInput('hostids', []), PROFILE_TYPE_STR);
			CProfile::update('web.latest.filter.application', $this->getInput('application', ''), PROFILE_TYPE_STR);
			CProfile::update('web.latest.filter.select', $this->getInput('select', ''), PROFILE_TYPE_STR);
			CProfile::update('web.latest.filter.show_without_data', $this->getInput('show_without_data', 0), PROFILE_TYPE_INT);
			CProfile::update('web.latest.filter.show_details', $this->getInput('show_details', 0), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			DBStart();
			CProfile::deleteIdx('web.latest.filter.groupids');
			CProfile::deleteIdx('web.latest.filter.hostids');
			CProfile::delete('web.latest.filter.application');
			CProfile::delete('web.latest.filter.select');
			CProfile::delete('web.latest.filter.show_without_data');
			CProfile::delete('web.latest.filter.show_details');
			DBend();
		}

		$filter = [
			'groupids' => CProfile::getArray('web.latest.filter.groupids'),
			'hostids' => CProfile::getArray('web.latest.filter.hostids'),
			'application' => CProfile::get('web.latest.filter.application', ''),
			'select' => CProfile::get('web.latest.filter.select', ''),
			'showWithoutData' => CProfile::get('web.latest.filter.show_without_data', 1),
			'showDetails' => CProfile::get('web.latest.filter.show_details')
		];

		$sortField = $this->getInput('sort', CProfile::get('web.latest.sort', 'name'));
		$sortOrder = $this->getInput('sortorder', CProfile::get('web.latest.sortorder', ZBX_SORT_UP));

		CProfile::update('web.latest.sort', $sortField, PROFILE_TYPE_STR);
		CProfile::update('web.latest.sortorder', $sortOrder, PROFILE_TYPE_STR);

		$view_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'latest.view')
			->getUrl();

		$refresh_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'latest.refresh')
			->setArgument('groupids', $filter['groupids'])
			->setArgument('hostids', $filter['hostids'])
			->setArgument('application', $filter['application'])
			->setArgument('select', $filter['select'])
			->setArgument('show_without_data', $filter['showWithoutData'])
			->setArgument('show_details', $filter['showDetails'])
			->setArgument('filter_set', 1)
			->setArgument('sort', $sortField)
			->setArgument('sortorder', $sortOrder)
			->getUrl();

		/*
		 * Display
		 */
		$data = [
			'filter' => $filter,
			'sortField' => $sortField,
			'sortOrder' => $sortOrder,
			'active_tab' => CProfile::get('web.latest.filter.active', 1),
			'view_url' => $view_url,
			'refresh_url' => $refresh_url,
			'refresh_interval' => CWebUser::getRefresh() * 1000
		] + parent::prepareData($filter, $sortField, $sortOrder);

		CView::$has_web_layout_mode = true;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Latest data'));
		$this->setResponse($response);
	}
}
