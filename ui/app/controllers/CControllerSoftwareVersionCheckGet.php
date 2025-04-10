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


class CControllerSoftwareVersionCheckGet extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return !CWebUser::isGuest();
	}

	protected function doAction(): void {
		$data = [
			'is_software_update_check_enabled' => CSettingsHelper::isSoftwareUpdateCheckEnabled()
		];

		if ($data['is_software_update_check_enabled']) {
			$now = time();
			$check_data = CSettingsHelper::getSoftwareUpdateCheckData() + [
				'lastcheck' => 0,
				'lastcheck_success' => 0,
				'nextcheck' => 0,
				'versions' => []
			];

			if ($check_data['nextcheck'] > $now) {
				$data['delay'] = $check_data['nextcheck'] - $now + mt_rand(1, SEC_PER_MIN);
			}
			else {
				$check_data['nextcheck'] = $now + SEC_PER_MIN;

				CSettings::updatePrivate(['software_update_check_data' => $check_data]);

				$data['version'] = CSettingsHelper::getServerStatus()['version'];
				$data['check_hash'] = CSettingsHelper::getPrivate(CSettingsHelper::SOFTWARE_UPDATE_CHECKID);
				$data['csrf_token'] = CCsrfTokenHelper::get('softwareversioncheck');
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
