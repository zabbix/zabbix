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


class CControllerSoftwareVersionCheckUpdate extends CController {

	private const NEXTCHECK_DELAY = 28800; // 8 hours
	private const NEXTCHECK_DELAY_ON_FAIL = 259200; // 72 hours
	private const MAX_NO_DATA_PERIOD = 604800; // 1 week

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'versions' =>	'required|array'
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
		return !CWebUser::isGuest();
	}

	protected function doAction(): void {
		$lastcheck = time();
		$versions = $this->getInput('versions');

		if ($versions) {
			$lastcheck_success = $lastcheck;
			$nextcheck = $lastcheck + self::NEXTCHECK_DELAY;
		}
		else {
			$previous_check_data = CSettingsHelper::getSoftwareUpdateCheckData() + [
				'lastcheck_success' => 0,
				'versions' => []
			];

			$lastcheck_success = $previous_check_data['lastcheck_success'];
			$nextcheck = $lastcheck + self::NEXTCHECK_DELAY_ON_FAIL;
			$versions = $lastcheck - $lastcheck_success <= self::MAX_NO_DATA_PERIOD
				? $previous_check_data['versions']
				: [];
		}

		$settings = ['software_update_check_data' => [
			'lastcheck' => $lastcheck,
			'lastcheck_success' => $lastcheck_success,
			'nextcheck' => $nextcheck,
			'versions' => $versions
		]];

		$result = CSettings::updatePrivate($settings);

		$output = [];

		if ($result) {
			$output['nextcheck'] = $nextcheck;
		}
		else {
			$output['error'] = [
				'title' => 'Cannot update software update check data',
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
