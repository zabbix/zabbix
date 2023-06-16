<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once __DIR__ .'/../../include/forms.inc.php';

class CControllerTriggerEdit extends CController {

	/**
	 * @var array
	 */
	private $trigger;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'context' =>							'string',
			'hostid' =>								'db hosts.hostid',
			'triggerid' =>							'db triggers.triggerid'
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
		if ($this->hasInput('triggerid')) {
			$this->trigger = API::Trigger()->get([
				'output' => ['triggerid', 'name'],
				'triggerids' => $this->getInput('triggerid')
			]);

			if (!$this->trigger) {
				return false;
			}

			$this->trigger = $this->trigger[0];
		}
		else {
			$this->trigger = null;
		}

		return true;
	}

	protected function doAction() {
		$data = [
			'triggerid' => $this->getInput('triggerid', 0),
			'hostid' => $this->getInput('hostid', 0)
		];

		$data = getTriggerFormData($data);

		$data['context'] = $this->getInput('context');
		$data['expression'] = array_key_exists('expression', $data) ? $data['expression'] : '';
		$data['recovery_expression'] = array_key_exists('recovery_expression', $data) ? $data['recovery_expression'] : '';
		$data['expression_full'] = $data['expression'];
		$data['recovery_expression_full'] = $data['recovery_expression'];
		$data['manual_close'] = array_key_exists('manual_close', $data) ? $data['manual_close'] : 1;
		$data['correlation_mode'] = array_key_exists('correlation_mode', $data) ? $data['correlation_mode'] : 0;
		$data['correlation_tag'] = array_key_exists('correlation_tag', $data) ? $data['correlation_tag'] : '';
		$data['description'] = array_key_exists('description', $data) ? $data['description'] : '';
		$data['opdata'] = array_key_exists('opdata', $data) ? $data['opdata'] : '';
		$data['priority'] = array_key_exists('priority', $data) ? $data['priority'] : '0';
		$data['recovery_mode'] = array_key_exists('recovery_mode', $data) ? $data['recovery_mode'] : 0;
		$data['type'] = array_key_exists('type', $data) ? $data['type'] : '0';
		$data['event_name'] = array_key_exists('event_name', $data) ? $data['event_name'] : $data['description'];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
