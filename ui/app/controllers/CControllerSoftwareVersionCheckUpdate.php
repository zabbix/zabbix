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


class CControllerSoftwareVersionCheckUpdate extends CController {

	private const NEXTCHECK_DELAY = 28800; // 8 hours
	private const NEXTCHECK_DELAY_ON_FAIL = 259200; // 72 hours
	private const MAX_NO_DATA_PERIOD = 604800; // 1 week

	private array $versions = [];

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'versions' =>	'required|array'
		];

		if ($this->validateInput($fields) && $this->validateVersions()) {
			$this->versions = $this->getInput('versions');
		}

		return true;
	}

	private function validateVersions(): bool {
		foreach (array_values($this->getInput('versions')) as $i => $version) {
			$path = '/versions/'.($i + 1);

			if (!is_array($version)) {
				error(_s('Invalid parameter "%1$s": %2$s.', $path, 'an array is expected'));

				return false;
			}

			$validator = new CNewValidator($version, [
				'version' =>				'required|not_empty|string',
				'end_of_full_support' =>	'required|bool',
				'latest_release' =>			'required|array'
			]);

			foreach ($validator->getAllErrors() as $error) {
				error($error);
			}

			if ($validator->isErrorFatal() || $validator->isError()) {
				return false;
			}

			if (strlen($version['version']) > 5 || !preg_match('/^\d+\.\d+$/', $version['version'])) {
				error(sprintf('Invalid parameter "%1$s": %2$s.', $path.'/version', 'invalid version'));

				return false;
			}

			$validator = new CNewValidator($version['latest_release'], [
				'created' =>	'required|not_empty|string',
				'release' =>	'required|not_empty|string'
			]);

			foreach ($validator->getAllErrors() as $error) {
				error($error);
			}

			if ($validator->isErrorFatal() || $validator->isError()) {
				return false;
			}

			if (!is_numeric($version['latest_release']['created'])
					|| bccomp($version['latest_release']['created'], ZBX_MAX_DATE) > 0) {
				error(sprintf('Invalid parameter "%1$s": %2$s.', $path.'/latest_release/created', 'invalid timestamp'));

				return false;
			}

			if (strlen($version['latest_release']['release']) > 16
					|| !preg_match('/^\d+\.\d+\.\d+(?:(alpha|beta|rc)\d+)?$/', $version['latest_release']['release'])) {
				error(sprintf('Invalid parameter "%1$s": %2$s.', $path.'/latest_release/release',
					'invalid release version'
				));

				return false;
			}
		}

		return true;
	}

	protected function checkPermissions(): bool {
		return !CWebUser::isGuest();
	}

	protected function doAction(): void {
		$lastcheck = time();
		$versions = $this->versions;

		if (in_array(ZABBIX_EXPORT_VERSION, array_column($versions, 'version'))) {
			$delay = self::NEXTCHECK_DELAY;
			$lastcheck_success = $lastcheck;
			$nextcheck = $lastcheck + $delay;
		}
		else {
			$previous_check_data = CSettingsHelper::getSoftwareUpdateCheckData() + [
				'lastcheck_success' => 0,
				'versions' => []
			];

			$delay = self::NEXTCHECK_DELAY_ON_FAIL;
			$lastcheck_success = $previous_check_data['lastcheck_success'];
			$nextcheck = $lastcheck + $delay;
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

		$output = [
			'delay' => CSettings::updatePrivate($settings) ? $delay : self::NEXTCHECK_DELAY_ON_FAIL
		];

		if ($errors = array_column(get_and_clear_messages(), 'message')) {
			$output['error'] = [
				'title' => 'Cannot update software update check data',
				'messages' => $errors
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
