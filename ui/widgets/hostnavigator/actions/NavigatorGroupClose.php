<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


namespace Widgets\HostNavigator\Actions;

use CController,
	CControllerResponseData,
	CProfile;

class NavigatorGroupClose extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'group_path' => 'required|string',
			'widgetid' =>	'required|db widget.widgetid'
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

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$widgetid = $this->getInput('widgetid');
		$closed_group = json_decode($this->getInput('group_path'), true);
		$open_groupids = CProfile::findByIdxPattern('web.dashboard.widget.open.%', $widgetid);

		$open_groups = [];

		foreach ($open_groupids as $open_groupid) {
			$open_group = CProfile::get($open_groupid, [], $widgetid);

			if ($open_group) {
				$open_groups[$open_groupid] = $open_group;
			}
		}

		foreach ($open_groups as $key => $open_group) {
			$open_group = json_decode($open_group, true);

			$group_match = array_slice($open_group, 0, count($closed_group)) == $closed_group;

			if ($group_match) {
				CProfile::delete($key, $widgetid);
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode([])]));
	}
}
