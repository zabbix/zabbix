<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerPopupServices extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'title' =>				'string|required',
			'filter_name' =>		'string',
			'exclude_serviceids' =>	'array_db services.serviceid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$exclude_serviceids = $this->getInput('exclude_serviceids', []);

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + count($exclude_serviceids);

		$services = API::Service()->get([
			'output' => ['serviceid', 'name', 'algorithm'],
			'selectProblemTags' => ['tag', 'value'],
			'search' => ['name' => $this->hasInput('filter_name') ? $this->getInput('filter_name') : null],
			'limit' => $limit,
			'preservekeys' => true
		]);

		$services = array_diff_key($services, array_flip($exclude_serviceids));
		$services = array_slice($services, 0, $limit);

		$data = [
			'title' => $this->getInput('title'),
			'filter' => [
				'name' => $this->getInput('filter_name', '')
			],
			'exclude_serviceids' => $exclude_serviceids,
			'services' => $services,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
