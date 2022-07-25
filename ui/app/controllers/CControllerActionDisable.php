<?php declare(strict_types = 0);
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


class CControllerActionDisable extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource'=> 'in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY,
					EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL
				]),
			'g_actionid' => 'array_id|required|not_empty'
		];
		// something else?

		$ret = $this->validateInput($fields);

		// TODO: fix error messaging
		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		$eventsource = $this->getInput('eventsource');
		$check_actionids = [];
		$check_actionids += array_fill_keys(getRequest('g_actionid'), true);

		if ($check_actionids) {
			$actions = API::Action()->get([
				'output' => [],
				'actionids' => array_keys($check_actionids),
				'filter' => [
					'eventsource' => $eventsource
				],
				'editable' => true
			]);

			return (count($actions) == count($check_actionids));
		}

		return false;
	}

	protected function doAction(): void {
		$eventsource = getRequest('eventsource');

		if (hasRequest('g_actionid')) {
			$actionids = getRequest('g_actionid', []);
			$actions_count = count($actionids);
			$actions = [];

			foreach ($actionids as $actionid) {
				$actions[] = ['actionid' => $actionid, 'status' => ACTION_STATUS_DISABLED];
			}

			$result = API::Action()->update($actions);

			if ($result && array_key_exists('actionids', $result)) {
				CMessageHelper::setSuccessTitle(_n('Action disabled', 'Actions disabled', $actions_count));
			}
			else {
				CMessageHelper::setErrorTitle(_n('Cannot disable action', 'Cannot disable actions', $actions_count));
			}

			uncheckTableRows($eventsource);

			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'action.list')
				->setArgument('eventsource', $eventsource)
			);

			$this->setResponse($response);
		}
	}
}
