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


class CControllerPopupMediaEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'edit' =>			'in 1',
			'row_index' =>		'required|int32',
			'userid' =>			'required|db users.userid',
			'mediaid' =>		'db media.mediaid',
			'mediatypeid' =>	'db media.mediatypeid',
			'sendto' =>			'db media.sendto',
			'sendto_list' =>	'array',
			'period' =>			'time_periods',
			'severities' =>		'array',
			'active' =>			'in '.implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]),
			'provisioned' =>	'in '.implode(',', [CUser::PROVISION_STATUS_NO, CUser::PROVISION_STATUS_YES])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (bccomp($this->getInput('userid'), CWebUser::$data['userid']) == 0) {
			return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA);
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_USER_MEDIA);
	}

	protected function doAction(): void {
		$db_mediatypes = API::MediaType()->get([
			'output' => ['name', 'type', 'status'],
			'preservekeys' => true
		]);

		CArrayHelper::sort($db_mediatypes, ['name']);

		$form_data = [
			'mediatypeid' => $this->getInput('mediatypeid', 0),
			'sendto' => $this->getInput('sendto', DB::getDefault('media', 'sendto')),
			'sendto_list' => [''],
			'sendto_active_devices' => 1,
			'sendto_device_data' => [],
			'period' => $this->getInput('period', DB::getDefault('media', 'period')),
			'severities' => $this->getInput('severities', $this->hasInput('edit')
				? []
				: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)
			),
			'active' => $this->getInput('active', DB::getDefault('media', 'active'))
		];

		if (array_key_exists($form_data['mediatypeid'], $db_mediatypes)) {
			if ($db_mediatypes[$form_data['mediatypeid']]['type'] == MEDIA_TYPE_EMAIL) {
				$form_data['sendto_list'] = $this->getInput('sendto_list', ['']);
			}
			elseif ($db_mediatypes[$form_data['mediatypeid']]['type'] == MEDIA_TYPE_PUSH) {
				$deviceuuids = $this->getInput('sendto_list', ['']);

				if (count($deviceuuids) > 0 && !in_array('*', $deviceuuids)) {
					$form_data['sendto_active_devices'] = 0;
					$form_data['sendto_device_data'] = [];

					if (CSettingsHelper::isMobileDevicesEnabled()) {
						$devices = API::Device()->get([
							'output' => ['uuid', 'name'],
							'userids' => $this->getInput('userid'),
							'filter' => ['uuid' => $deviceuuids, 'status' => ZBX_DEVICE_STATUS_ACTIVATED]
						]);

						$devices = array_combine(array_column($devices, 'uuid'), $devices);
						$devices = CArrayHelper::renameObjectsKeys($devices, ['uuid' => 'id']);
						$form_data['sendto_device_data'] = $devices;
					}
				}
			}
		}


		$data = [
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'userid' => $this->getInput('userid'),
			'mediaid' => $this->hasInput('mediaid') ? $this->getInput('mediaid') : null,
			'provisioned' => $this->getInput('provisioned', CUser::PROVISION_STATUS_NO),
			'mediatypes' => $db_mediatypes,
			'form' => $form_data,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'js_validation_rules' => (new CFormValidator(CControllerPopupMediaCheck::getValidationRules()))->getRules()
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
