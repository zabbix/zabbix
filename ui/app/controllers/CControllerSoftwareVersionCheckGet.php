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
			$check_data = CSettingsHelper::getSoftwareUpdateCheckData() + ['nextcheck' => 0];

			$data['now'] = time();
			$data['nextcheck'] = $check_data['nextcheck'];
			$data['major_version'] = ZABBIX_EXPORT_VERSION;
			$data['check_hash'] = CSettingsHelper::getPrivate(CSettingsHelper::SOFTWARE_UPDATE_CHECKID);
			$data['_csrf_token'] = CCsrfTokenHelper::get('softwareversioncheck');
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
