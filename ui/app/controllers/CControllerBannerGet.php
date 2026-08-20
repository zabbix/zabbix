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
		global $ZBX_FEATURE_FLAGS;

		$output = [
			'allow_banners' => $ZBX_FEATURE_FLAGS['banners_enabled'],
			'dismissed_banner_ids' => CBannerHelper::getDismissedIds(),
			'language' => CWebUser::$data['lang'],
			'storage_idx' => 'web.banner.dismissed_ids'
		];

		if ($output['allow_banners']) {
			$now = time();
			$banner_data = CBannerHelper::getData() + [
				'lastcheck' => 0,
				'lastcheck_success' => 0,
				'nextcheck' => 0
			];

			if ($banner_data['nextcheck'] > $now) {
				$output += [
					'delay' => $banner_data['nextcheck'] - $now + mt_rand(1, SEC_PER_MIN),
					'banners' => $banner_data['banners'] ?? []
				];
			}
			else {
				$output += [
					'csrf_token' => CCsrfTokenHelper::get('banner')
				];

				$banner_data['nextcheck'] = $now + SEC_PER_MIN;

				CProfile::update('web.banner.data', json_encode($banner_data), PROFILE_TYPE_STR);
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
