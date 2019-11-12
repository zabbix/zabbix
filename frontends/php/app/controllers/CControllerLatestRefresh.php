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


class CControllerLatestRefresh extends CControllerLatest {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'page' =>				'ge 1',

			// Filter inputs.
			'groupids' =>			'array_id',
			'hostids' =>			'array_id',
			'application' =>		'string',
			'select' =>				'string',
			'show_without_data' =>	'in 1',
			'show_details' =>		'in 1',

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
		$filter = [
			'groupids' => $this->hasInput('groupids') ? $this->getInput('groupids') : null,
			'hostids' => $this->hasInput('hostids') ? $this->getInput('hostids') : null,
			'application' => $this->getInput('application', ''),
			'select' => $this->getInput('select', ''),
			'show_without_data' => $this->getInput('show_without_data', 0),
			'show_details' => $this->getInput('show_details', 0)
		];

		$sortField = $this->getInput('sort', 'name');
		$sortOrder = $this->getInput('sortorder', ZBX_SORT_UP);

		$view_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'latest.view')
			->setArgument('groupids', $filter['groupids'])
			->setArgument('hostids', $filter['hostids'])
			->setArgument('application', $filter['application'])
			->setArgument('select', $filter['select'])
			->setArgument('show_without_data', $filter['showWithoutData'] ? 1 : null)
			->setArgument('show_details', $filter['showDetails'] ? 1 : null)
			->setArgument('filter_set', 1)
			->setArgument('sort', $sortField)
			->setArgument('sortorder', $sortOrder);

		/*
		 * Display
		 */
		$data = [
			'filter' => $filter,
			'sortField' => $sortField,
			'sortOrder' => $sortOrder,
			'view_curl' => $view_curl
		] + parent::prepareData($filter, $sortField, $sortOrder);

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
