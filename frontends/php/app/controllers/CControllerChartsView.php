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
class CControllerChartsView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'from' =>'range_time',
			'to' =>'range_time',
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$timeselector_options = [
			'profileIdx' => 'web.graphs.filter',
			'profileIdx2' => null,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$data = [
			'action' => getRequest('action', HISTORY_GRAPH),
			'actions' => [
				HISTORY_GRAPH => _('Graph'),
				HISTORY_VALUES => _('Values')
			],
			'ms_hosts' => [],
			'ms_graphs' => [],
			'ms_graph_patterns' => [],
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'page' => getRequest('page', 1),
			'active_tab' => CProfile::get('web.graphs.filter.active', 1),
			'search_type' => ZBX_SEARCH_TYPE_STRICT
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Custom graphs'));

		$this->setResponse($response);
	}
}
