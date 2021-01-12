<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
			'custom_color'        => 'int32',
			'problem_unack_color' => 'string',
			'problem_ack_color'   => 'string',
			'ok_unack_color'      => 'string',
			'ok_ack_color'        => 'string',
			'problem_unack_style' => 'int32',
			'problem_ack_style'   => 'int32',
			'ok_unack_style'      => 'int32',
			'ok_ack_style'        => 'int32',
			'ok_period'           => 'string',
			'blink_period'        => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$config = select_config();

		$data = [
			'custom_color'        => $this->getInput('custom_color',        $config['custom_color']),
			'problem_unack_color' => $this->getInput('problem_unack_color', $config['problem_unack_color']),
			'problem_ack_color'   => $this->getInput('problem_ack_color',   $config['problem_ack_color']),
			'ok_unack_color'      => $this->getInput('ok_unack_color',      $config['ok_unack_color']),
			'ok_ack_color'        => $this->getInput('ok_ack_color',        $config['ok_ack_color']),
			'problem_unack_style' => $this->getInput('problem_unack_style', $config['problem_unack_style']),
			'problem_ack_style'   => $this->getInput('problem_ack_style',   $config['problem_ack_style']),
			'ok_unack_style'      => $this->getInput('ok_unack_style',      $config['ok_unack_style']),
			'ok_ack_style'        => $this->getInput('ok_ack_style',        $config['ok_ack_style']),
			'ok_period'           => $this->getInput('ok_period',           $config['ok_period']),
			'blink_period'        => $this->getInput('blink_period',        $config['blink_period'])
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of trigger displaying options'));

		$this->setResponse($response);
	}
}
