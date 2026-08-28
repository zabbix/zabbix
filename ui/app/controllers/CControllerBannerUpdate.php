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


class CControllerBannerUpdate extends CController {

	private const NEXTCHECK_DELAY =  86400; // 24 hours
	private const NEXTCHECK_DELAY_ON_FAIL =  86400; // 24 hours

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'number_of_attempts' => 'required|int32',
			'banners' => 'required|array'
		];

		return $this->validateInput($fields);
	}

	protected function checkPermissions(): bool {
		return !CWebUser::isGuest();
	}

	protected function doAction(): void {
		$lastcheck = time();
		$number_of_attempts = $this->getInput('number_of_attempts');
		$previous_check_data = CBannerHelper::getData() + ['lastcheck_success' => 0];

		if ($number_of_attempts > 0) {
			$delay = self::NEXTCHECK_DELAY_ON_FAIL;
			$lastcheck_success = $previous_check_data['lastcheck_success'];
		}
		else {
			$delay = self::NEXTCHECK_DELAY;
			$lastcheck_success = $lastcheck;
		}

		$nextcheck = $lastcheck + $delay;

		$parsedown = (new Parsedown())->setSafeMode(true);

		$banners = $this->getInput('banners');
		foreach ($banners as &$banner) {
			if (array_key_exists('content', $banner) && is_array($banner['content'])) {
				foreach ($banner['content'] as $lang => $text) {
					$banner['content'][$lang] = $parsedown->text($text);
				}
			}
		}
		unset($banner);

		$banner_data = [
			'lastcheck' => $lastcheck,
			'lastcheck_success' => $lastcheck_success,
			'nextcheck' => $nextcheck,
			'banners' => $banners
		];

		CProfile::update('web.banner.data', json_encode($banner_data), PROFILE_TYPE_STR);

		$output = [
			'banners' => $banners,
			'delay' => self::NEXTCHECK_DELAY
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
