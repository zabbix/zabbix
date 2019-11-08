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
			'groupids' => $this->getInput('groupids', []),
			'hostids' => $this->getInput('hostids', []),
			'application' => $this->getInput('application', ''),
			'select' => $this->getInput('select', ''),
			'showWithoutData' => $this->getInput('show_without_data', 0),
			'showDetails' => $this->getInput('show_details', 0)
		];

		$sortField = $this->getInput('sort', 'name');
		$sortOrder = $this->getInput('sortorder', ZBX_SORT_UP);

		$view_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'latest.view')
			->getUrl();

		/*
		 * Display
		 */
		$data = [
			'filter' => $filter,
			'sortField' => $sortField,
			'sortOrder' => $sortOrder,
			'view_url' => $view_url,
		] + parent::prepareData($filter, $sortField, $sortOrder);

		CView::$has_web_layout_mode = true;

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
