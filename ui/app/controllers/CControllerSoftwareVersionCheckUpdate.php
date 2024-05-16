<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

		$ret = $this->validateInput($fields) && $this->validateVersions();

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

			if (!preg_match('/^\d+\.\d+$/', $version['version'])) {
				error('Invalid version.');

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
				error('Invalid timestamp.');

				return false;
			}

			if (!preg_match('/^\d+\.\d+\.\d+(?:(alpha|beta|rc)\d+)?$/', $version['latest_release']['release'])) {
				error('Invalid release version.');

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
		$versions = $this->getInput('versions');

		if ($versions) {
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

		$result = CSettings::updatePrivate($settings);

		$output = [];

		if ($result) {
			$output['delay'] = $delay + random_int(1, SEC_PER_MIN);
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
