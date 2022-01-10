<?php declare(strict_types = 1);
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


class CControllerHintboxActionlist extends CController {

	/**
	 * @var array
	 */
	protected $event;

	protected function init(): void {
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'eventid' => 'required|db events.eventid'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$events = API::Event()->get([
				'output' => ['eventid', 'r_eventid', 'clock'],
				'select_acknowledges' => ['userid', 'action', 'message', 'clock', 'new_severity',
					'old_severity'
				],
				'eventids' => (array) $this->getInput('eventid')
			]);

			if (!$events) {
				error(_('No permissions to referred object or it does not exist!'));
				$ret = false;
			}
			else {
				$this->event = $events[0];
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseData([]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$actions = getEventDetailsActions($this->event);

		$users = $actions['userids']
			? API::User()->get([
				'output' => ['username', 'name', 'surname'],
				'userids' => array_keys($actions['userids']),
				'preservekeys' => true
			])
			: [];

		$mediatypes = $actions['mediatypeids']
			? API::MediaType()->get([
				'output' => ['name', 'maxattempts'],
				'mediatypeids' => array_keys($actions['mediatypeids']),
				'preservekeys' => true
			])
			: [];

		$this->setResponse(new CControllerResponseData([
			'actions' => $actions['actions'],
			'users' => $users,
			'mediatypes' => $mediatypes,
			'foot_note' => ($actions['count'] > ZBX_WIDGET_ROWS)
				? _s('Displaying %1$s of %2$s found', ZBX_WIDGET_ROWS, $actions['count'])
				: null
		]));
	}
}
