<?php
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


class CControllerTrigDisplayUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'custom_color' =>			'required|db config.custom_color|in '.EVENT_CUSTOM_COLOR_DISABLED.','.EVENT_CUSTOM_COLOR_ENABLED,
			'problem_unack_color' =>	'db config.problem_unack_color|rgb',
			'problem_ack_color' =>		'db config.problem_ack_color|rgb',
			'ok_unack_color' =>			'db config.ok_unack_color|rgb',
			'ok_ack_color' =>			'db config.ok_ack_color|rgb',
			'problem_unack_style' =>	'required|db config.problem_unack_style|in 0,1',
			'problem_ack_style' =>		'required|db config.problem_ack_style|in 0,1',
			'ok_unack_style' =>			'required|db config.ok_unack_style|in 0,1',
			'ok_ack_style' =>			'required|db config.ok_ack_style|in 0,1',
			'ok_period' =>				'required|db config.ok_period|not_empty|time_unit '.implode(':', [0, SEC_PER_DAY]),
			'blink_period' =>			'required|db config.blink_period|not_empty|time_unit '.implode(':', [0, SEC_PER_DAY]),
			'severity_name_0' =>		'required|db config.severity_name_0|not_empty',
			'severity_color_0' =>		'required|db config.severity_color_0|rgb',
			'severity_name_1' =>		'required|db config.severity_name_1|not_empty',
			'severity_color_1' =>		'required|db config.severity_color_1|rgb',
			'severity_name_2' =>		'required|db config.severity_name_2|not_empty',
			'severity_color_2' =>		'required|db config.severity_color_2|rgb',
			'severity_name_3' =>		'required|db config.severity_name_3|not_empty',
			'severity_color_3' =>		'required|db config.severity_color_3|rgb',
			'severity_name_4' =>		'required|db config.severity_name_4|not_empty',
			'severity_color_4' =>		'required|db config.severity_color_4|rgb',
			'severity_name_5' =>		'required|db config.severity_name_5|not_empty',
			'severity_color_5' =>		'required|db config.severity_color_5|rgb'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$response = new CControllerResponseRedirect(
				(new CUrl('zabbix.php'))->setArgument('action', 'trigdisplay.edit')
			);

			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update configuration'));

			$this->setResponse($response);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$settings = [
			CSettingsHelper::CUSTOM_COLOR => $this->getInput('custom_color', EVENT_CUSTOM_COLOR_DISABLED),
			CSettingsHelper::PROBLEM_UNACK_STYLE => $this->getInput('problem_unack_style'),
			CSettingsHelper::PROBLEM_ACK_STYLE => $this->getInput('problem_ack_style'),
			CSettingsHelper::OK_UNACK_STYLE => $this->getInput('ok_unack_style'),
			CSettingsHelper::OK_ACK_STYLE => $this->getInput('ok_ack_style'),
			CSettingsHelper::OK_PERIOD => trim($this->getInput('ok_period')),
			CSettingsHelper::BLINK_PERIOD => trim($this->getInput('blink_period')),
			CSettingsHelper::SEVERITY_NAME_0 => $this->getInput('severity_name_0'),
			CSettingsHelper::SEVERITY_COLOR_0 => $this->getInput('severity_color_0'),
			CSettingsHelper::SEVERITY_NAME_1 => $this->getInput('severity_name_1'),
			CSettingsHelper::SEVERITY_COLOR_1 => $this->getInput('severity_color_1'),
			CSettingsHelper::SEVERITY_NAME_2 => $this->getInput('severity_name_2'),
			CSettingsHelper::SEVERITY_COLOR_2 => $this->getInput('severity_color_2'),
			CSettingsHelper::SEVERITY_NAME_3 => $this->getInput('severity_name_3'),
			CSettingsHelper::SEVERITY_COLOR_3 => $this->getInput('severity_color_3'),
			CSettingsHelper::SEVERITY_NAME_4 => $this->getInput('severity_name_4'),
			CSettingsHelper::SEVERITY_COLOR_4 => $this->getInput('severity_color_4'),
			CSettingsHelper::SEVERITY_NAME_5 => $this->getInput('severity_name_5'),
			CSettingsHelper::SEVERITY_COLOR_5 => $this->getInput('severity_color_5')
		];

		if ($settings[CSettingsHelper::CUSTOM_COLOR] == EVENT_CUSTOM_COLOR_ENABLED) {
			$settings[CSettingsHelper::PROBLEM_UNACK_COLOR] = $this->getInput('problem_unack_color');
			$settings[CSettingsHelper::PROBLEM_ACK_COLOR] = $this->getInput('problem_ack_color');
			$settings[CSettingsHelper::OK_UNACK_COLOR] = $this->getInput('ok_unack_color');
			$settings[CSettingsHelper::OK_ACK_COLOR] = $this->getInput('ok_ack_color');
		}

		$result = API::Settings()->update($settings);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'trigdisplay.edit')
		);

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Configuration updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update configuration'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
