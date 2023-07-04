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
			'context' =>							'required|in '.implode(',', ['host', 'template']),
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
				'output' => API_OUTPUT_EXTEND,
				'triggerids' => $this->getInput('triggerid'),
				'selectHosts' => ['hostid'],
				'selectDiscoveryRule' => ['itemid', 'name', 'templateid'],
				'selectTriggerDiscovery' => ['parent_triggerid'],
				'selectDependencies' => ['triggerid'],
				'selectTags' => ['tag', 'value']
			]);

			if (!$this->trigger) {
				return false;
			}
		}
		else {
			$this->trigger = null;
		}

		return true;
	}

	protected function doAction()
	{
		$data = [
			'hostid' => $this->getInput('hostid', 0),
			'dependencies' => [],
			'context' => $this->getInput('context'),
			'expression' => '',
			'recovery_expression' => '',
			'expression_full' => '',
			'recovery_expression_full' => '',
			'manual_close' => 1,
			'correlation_mode' => 0,
			'correlation_tag' => '',
			'description' => '',
			'opdata' => '',
			'priority' => '0',
			'recovery_mode' => 0,
			'type' => '0',
			'event_name' => '',
			'db_dependencies' => [],
			'limited' => false,
			'tags' => [],
			'recovery_expression_field_readonly' => false,
			'triggerid' => null
		];


		if ($this->trigger) {
			$triggers = CMacrosResolverHelper::resolveTriggerExpressions($this->trigger,
				['sources' => ['expression', 'recovery_expression']]
			);

			$data = array_merge($data, reset($triggers));

			$data['db_dependencies'] = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'flags'],
				'selectHosts' => ['hostid', 'name'],
				'triggerids' => array_column($data['dependencies'], 'triggerid'),
				'preservekeys' => true
			]);

			foreach ($data['db_dependencies'] as &$dependency) {
				order_result($dependency['hosts'], 'name', ZBX_SORT_UP);
			}
			unset($dependency);

			order_result($data['db_dependencies'], 'description');

			$data['limited'] = ($data['templateid'] != 0);
			$data['expression_full'] = $data['expression'];
			$data['recovery_expression_full'] = $data['recovery_expression'];
		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
