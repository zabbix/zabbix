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


class CControllerActionDelete extends CController {

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

		// todo: FIX wrong error messaging
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
		// check permission to actions -> are they writable?
		// check permissions to actions ???
		return true;
	}

	protected function doAction(): void {
		$eventsource = getRequest('eventsource');

		if (hasRequest('g_actionid')) {
			$actionids = getRequest('g_actionid', []);
			$actions_count = count($actionids);

			$result = API::Action()->delete($actionids);

			if ($result) {
				CMessageHelper::setSuccessTitle(_n('Selected action deleted', 'Selected actions deleted', $actions_count));
			}
			else {
				CMessageHelper::setErrorTitle(_n('Cannot delete selected action', 'Cannot delete selected actions', $actions_count));
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
