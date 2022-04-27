<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerWidgetNavTreeItemEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' => 'required|string',
			'sysmapid' => 'required|db sysmaps.sysmapid',
			'depth' => 'required|ge 1|le '.WIDGET_NAVIGATION_TREE_MAX_DEPTH
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$sysmapid = $this->getInput('sysmapid');

		$sysmap = ['sysmapid' => $sysmapid, 'name' => ''];

		if ($sysmapid != 0) {
			$sysmaps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => [$sysmapid]
			]);

			if ($sysmaps) {
				$sysmap = $sysmaps[0];
			}
			else {
				$sysmap['name'] = _('Inaccessible map');
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
