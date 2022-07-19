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


class CControllerActionEnable extends CController {

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction()
	{
		$eventsource = getRequest('eventsource');
		$output = [];
		if (str_in_array(getRequest('action'), ['action.enable', 'action.massdisable']) && hasRequest('g_actionid')) {
			$status = (getRequest('action') == 'action.enable') ? ACTION_STATUS_ENABLED : ACTION_STATUS_DISABLED;
			$actionids = (array)getRequest('g_actionid', []);
			$actions_count = count($actionids);
			$actions = [];

			foreach ($actionids as $actionid) {
				$actions[] = ['actionid' => $actionid, 'status' => $status];
			}

			$result = API::Action()->update($actions);

			if ($result && array_key_exists('actionids', $result)) {
				$message = $status == ACTION_STATUS_ENABLED
					? _n('Action enabled', 'Actions enabled', $actions_count)
					: _n('Action disabled', 'Actions disabled', $actions_count);

				show_messages(true, $message);
				uncheckTableRows($eventsource);
				var_dump($message);
			} else {
				$message = $status == ACTION_STATUS_ENABLED
					? _n('Cannot enable action', 'Cannot enable actions', $actions_count)
					: _n('Cannot disable action', 'Cannot disable actions', $actions_count);

				show_messages(false, null, $message);

			}

//			if ($result && array_key_exists('actionids', $result)) {
//				$output['success']['title'] = _n('Action enabled', 'Actions enabled', $actions_count);
//				if ($messages = get_and_clear_messages()) {
//					$output['success']['messages'] = array_column($messages, 'message');
//				}
//			}
//			else {
//				$output['error'] = [
//					'title' =>  _n('Cannot enable action', 'Cannot enable actions', $actions_count),
//					'messages' => array_column(get_and_clear_messages(), 'message')
//				];
//			}
//			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
		}
	}
}
