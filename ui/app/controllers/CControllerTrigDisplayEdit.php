<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerTrigDisplayEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'custom_color' =>			'db config.custom_color',
			'problem_unack_color' =>	'db config.problem_unack_color',
			'problem_ack_color' =>		'db config.problem_ack_color',
			'ok_unack_color' =>			'db config.ok_unack_color',
			'ok_ack_color' =>			'db config.ok_ack_color',
			'problem_unack_style' =>	'db config.problem_unack_style',
			'problem_ack_style' =>		'db config.problem_ack_style',
			'ok_unack_style' =>			'db config.ok_unack_style',
			'ok_ack_style' =>			'db config.ok_ack_style',
			'ok_period' =>				'db config.ok_period',
			'blink_period' =>			'db config.blink_period',
			'severity_name_0' =>		'db config.severity_name_0',
			'severity_color_0' =>		'db config.severity_color_0',
			'severity_name_1' =>		'db config.severity_name_1',
			'severity_color_1' =>		'db config.severity_color_1',
			'severity_name_2' =>		'db config.severity_name_2',
			'severity_color_2' =>		'db config.severity_color_2',
			'severity_name_3' =>		'db config.severity_name_3',
			'severity_color_3' =>		'db config.severity_color_3',
			'severity_name_4' =>		'db config.severity_name_4',
			'severity_color_4' =>		'db config.severity_color_4',
			'severity_name_5' =>		'db config.severity_name_5',
			'severity_color_5' =>		'db config.severity_color_5'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$data = [
			'custom_color' => $this->getInput('custom_color', CSettingsHelper::get(CSettingsHelper::CUSTOM_COLOR)),
			'problem_unack_color' => $this->getInput('problem_unack_color', CSettingsHelper::get(
				CSettingsHelper::PROBLEM_UNACK_COLOR
			)),
			'problem_ack_color' => $this->getInput('problem_ack_color', CSettingsHelper::get(
				CSettingsHelper::PROBLEM_ACK_COLOR
			)),
			'ok_unack_color' => $this->getInput('ok_unack_color', CSettingsHelper::get(
				CSettingsHelper::OK_UNACK_COLOR
			)),
			'ok_ack_color' => $this->getInput('ok_ack_color', CSettingsHelper::get(CSettingsHelper::OK_ACK_COLOR)),
			'problem_unack_style' => $this->getInput('problem_unack_style', CSettingsHelper::get(
				CSettingsHelper::PROBLEM_UNACK_STYLE
			)),
			'problem_ack_style' => $this->getInput('problem_ack_style', CSettingsHelper::get(
				CSettingsHelper::PROBLEM_ACK_STYLE
			)),
			'ok_unack_style' => $this->getInput('ok_unack_style', CSettingsHelper::get(
				CSettingsHelper::OK_UNACK_STYLE
			)),
			'ok_ack_style' => $this->getInput('ok_ack_style', CSettingsHelper::get(CSettingsHelper::OK_ACK_STYLE)),
			'ok_period' => $this->getInput('ok_period', CSettingsHelper::get(CSettingsHelper::OK_PERIOD)),
			'blink_period' => $this->getInput('blink_period', CSettingsHelper::get(CSettingsHelper::BLINK_PERIOD)),
			'severity_name_0' => $this->getInput('severity_name_0', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_NAME_0
			)),
			'severity_color_0' => $this->getInput('severity_color_0', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_COLOR_0
			)),
			'severity_name_1' => $this->getInput('severity_name_1', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_NAME_1
			)),
			'severity_color_1' => $this->getInput('severity_color_1', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_COLOR_1
			)),
			'severity_name_2' => $this->getInput('severity_name_2', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_NAME_2
			)),
			'severity_color_2' => $this->getInput('severity_color_2', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_COLOR_2
			)),
			'severity_name_3' => $this->getInput('severity_name_3', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_NAME_3
			)),
			'severity_color_3' => $this->getInput('severity_color_3', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_COLOR_3
			)),
			'severity_name_4' => $this->getInput('severity_name_4', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_NAME_4
			)),
			'severity_color_4' => $this->getInput('severity_color_4', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_COLOR_4
			)),
			'severity_name_5' => $this->getInput('severity_name_5', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_NAME_5
			)),
			'severity_color_5' => $this->getInput('severity_color_5', CSettingsHelper::get(
				CSettingsHelper::SEVERITY_COLOR_5
			))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of trigger displaying options'));

		$this->setResponse($response);
	}
}
