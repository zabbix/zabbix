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


class CControllerValuemapList extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'uncheck' => 'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$sortfield = getRequest('sort', CProfile::get('web.valuemap.list.sort', 'name'));
		$sortorder = getRequest('sortorder', CProfile::get('web.valuemap.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.valuemap.list.sort', $sortfield, PROFILE_TYPE_STR);
		CProfile::update('web.valuemap.list.sortorder', $sortorder, PROFILE_TYPE_STR);

		$data = [
			'sort' => $sortfield,
			'sortorder' => $sortorder,
			'uncheck' => $this->hasInput('uncheck')
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['valuemaps'] = API::ValueMap()->get([
			'output' => ['valuemapid', 'name'],
			'selectMappings' => ['value', 'newvalue'],
			'sortfield' => $sortfield,
			'limit' => $limit
		]);

		// data sort and pager
		order_result($data['valuemaps'], $sortfield, $sortorder);

		$data['page'] = getRequest('page', 1);
		CPagerHelper::savePage('valuemap.list', $data['page']);
		$data['paging'] = CPagerHelper::paginate($data['page'], $data['valuemaps'], $sortorder,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		foreach ($data['valuemaps'] as &$valuemap) {
			order_result($valuemap['mappings'], 'value');

			$valuemap['used_in_items'] =
				(bool) API::Item()->get([
					'output' => [],
					'webitems' => true,
					'filter' => ['valuemapid' => $valuemap['valuemapid']],
					'limit' => 1
				])
				|| (bool) API::ItemPrototype()->get([
					'output' => [],
					'filter' => ['valuemapid' => $valuemap['valuemapid']],
					'limit' => 1
				]);
		}
		unset($valuemap);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of value mapping'));

		$this->setResponse($response);
	}
}
