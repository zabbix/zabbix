<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerBannerGet extends CController {

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
		global $ALLOW_BANNERS;

		$output = [
			'allow_banners' => $ALLOW_BANNERS
		];

		if ($output['allow_banners']) {
			$now = time();
			$check_data = CSettingsHelper::getBannerData() + [
				'lastcheck' => 0,
				'lastcheck_success' => 0,
				'nextcheck' => 0
			];

			if ($check_data['nextcheck'] > $now) {
				$output += [
					'delay' => $check_data['nextcheck'] - $now + mt_rand(1, SEC_PER_MIN),
					'banners' => $check_data['banners'] ?? []
				];
			}
			else {
				$output += [
					'language' => CWebUser::$data['lang'],
					'csrf_token' => CCsrfTokenHelper::get('banner')
				];

				$check_data['nextcheck'] = $now + SEC_PER_MIN;

				CSettings::updatePrivate(['banner_data' => $check_data]);
			}
		}

		$output += [
			'storage_idx' => 'web.banner.dismissed_ids',
			'dismissed_banner_ids' => json_decode(CProfile::get('web.banner.dismissed_ids', '[]'), true)
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
