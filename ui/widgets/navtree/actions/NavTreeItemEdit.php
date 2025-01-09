<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


namespace Widgets\NavTree\Actions;

use API,
	CController,
	CControllerResponseData;

use Widgets\NavTree\Widget;

class NavTreeItemEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'name' => 'required|string',
			'sysmapid' => 'required|db sysmaps.sysmapid',
			'depth' => 'required|ge 1|le '.Widget::MAX_DEPTH
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$sysmapid = $this->getInput('sysmapid');

		$sysmap = [];

		if ($sysmapid != 0) {
			$sysmaps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => [$sysmapid]
			]);

			if ($sysmaps) {
				$sysmap = $sysmaps[0];
				$sysmap = [
					'id' => $sysmap['sysmapid'],
					'name' => $sysmap['name']
				];
			}
			else {
				$sysmap = [
					'id' => $sysmapid,
					'name' => _('Inaccessible map'),
					'inaccessible' => true
				];
			}
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name'),
			'sysmap' => $sysmap,
			'depth' => $this->getInput('depth'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
