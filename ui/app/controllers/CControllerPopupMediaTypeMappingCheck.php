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


class CControllerPopupMediaTypeMappingCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'userdirectory_mediaid' => 	'id',
			'mediatypeid' =>			'required|db media_type.mediatypeid',
			'name' =>					'required|string|not_empty',
			'attribute' =>				'required|string|not_empty',
			'period' =>					'required|time_periods',
			'severity' =>				'array',
			'active' =>					'in '.implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid media type mapping configuration.'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'name' => '',
			'attribute' => '',
			'period' => ZBX_DEFAULT_INTERVAL,
			'active' => MEDIA_STATUS_DISABLED
		];
		$this->getInputs($data, array_keys($data));

		if ($this->hasInput('userdirectory_mediaid')) {
			$data['userdirectory_mediaid'] = $this->getInput('userdirectory_mediaid');
		}

		$data['severity'] = 0;
		foreach ($this->getInput('severity', []) as $severity) {
			$data['severity'] += pow(2, $severity);
		}

		$media_type = API::MediaType()->get([
			'output' => ['name', 'mediatypeid'],
			'mediatypeids' => $this->getInput('mediatypeid')
		]);

		if ($media_type) {
			$data['mediatype_name'] = $media_type[0]['name'];
			$data['mediatypeid'] = $media_type[0]['mediatypeid'];
		}
		else {
			$data['error'] = [
				'messages' => [_('No permissions to referred object or it does not exist!')]
			];
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
