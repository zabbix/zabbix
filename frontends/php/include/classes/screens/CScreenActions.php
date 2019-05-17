<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CScreenActions extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$sortfield = 'clock';
		$sortorder = ZBX_SORT_DOWN;

		switch ($this->screenitem['sort_triggers']) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				$sortfield = 'clock';
				$sortorder = ZBX_SORT_UP;
				break;

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
				$sortfield = 'clock';
				$sortorder = ZBX_SORT_DOWN;
				break;

			case SCREEN_SORT_TRIGGERS_TYPE_ASC:
				$sortfield = 'mediatypeid';
				$sortorder = ZBX_SORT_UP;
				break;

			case SCREEN_SORT_TRIGGERS_TYPE_DESC:
				$sortfield = 'mediatypeid';
				$sortorder = ZBX_SORT_DOWN;
				break;

			case SCREEN_SORT_TRIGGERS_STATUS_ASC:
				$sortfield = 'status';
				$sortorder = ZBX_SORT_UP;
				break;

			case SCREEN_SORT_TRIGGERS_STATUS_DESC:
				$sortfield = 'status';
				$sortorder = ZBX_SORT_DOWN;
				break;

			case SCREEN_SORT_TRIGGERS_RECIPIENT_ASC:
				$sortfield = 'sendto';
				$sortorder = ZBX_SORT_UP;
				break;

			case SCREEN_SORT_TRIGGERS_RECIPIENT_DESC:
				$sortfield = 'sendto';
				$sortorder = ZBX_SORT_DOWN;
				break;
		}

		$alerts = API::Alert()->get([
			'output' => ['clock', 'sendto', 'subject', 'message', 'status', 'retries', 'error', 'userid', 'actionid',
				'mediatypeid', 'alerttype'
			],
			'selectMediatypes' => ['description', 'maxattempts'],
			'filter' => [
				'alerttype' => ALERT_TYPE_MESSAGE
			],
			'time_from' => $this->timeline['from_ts'],
			'time_till' => $this->timeline['to_ts'],
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => $this->screenitem['elements']
		]);

		$userids = [];
		foreach ($alerts as $alert) {
			if ($alert['userid'] != 0) {
				$userids[$alert['userid']] = true;
			}
		}

		$db_users = $userids
			? API::User()->get([
				'output' => ['userid', 'alias', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			])
			: [];

		// indicator of sort field
		$sort_div = (new CSpan())->addClass(($sortorder === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

		// create alert table
		$table = (new CTableInfo())
			->setHeader([
				($sortfield === 'clock') ? [('Time'), $sort_div] : _('Time'),
				_('Action'),
				($sortfield === 'mediatypeid') ? [_('Type'), $sort_div] : _('Type'),
				($sortfield === 'sendto') ? [_('Recipient'), $sort_div] : _('Recipient'),
				_('Message'),
				($sortfield === 'status') ? [_('Status'), $sort_div] : _('Status'),
				_('Info')
			]);

		$actions = API::Action()->get([
			'output' => ['actionid', 'name'],
			'actionids' => array_unique(zbx_objectValues($alerts, 'actionid')),
			'preservekeys' => true
		]);

		foreach ($alerts as $alert) {
			if ($alert['alerttype'] == ALERT_TYPE_MESSAGE && array_key_exists(0, $alert['mediatypes'])
					&& ($alert['status'] == ALERT_STATUS_NOT_SENT || $alert['status'] == ALERT_STATUS_NEW)) {
				$info_icons = makeWarningIcon(_n('%1$s retry left', '%1$s retries left',
					$alert['mediatypes'][0]['maxattempts'] - $alert['retries'])
				);
			}
			elseif ($alert['error'] !== '') {
				$info_icons = makeErrorIcon($alert['error']);
			}
			else {
				$info_icons = null;
			}

			$alert['action_type'] = ZBX_EVENT_HISTORY_ALERT;

			$action_type = '';
			if ($alert['mediatypeid'] != 0 && array_key_exists(0, $alert['mediatypes'])) {
				$action_type = $alert['mediatypes'][0]['description'];
				$alert['maxattempts'] = $alert['mediatypes'][0]['maxattempts'];
			}

			$action_name = '';
			if (array_key_exists($alert['actionid'], $actions)) {
				$action_name = $actions[$alert['actionid']]['name'];
			}

			$table->addRow([
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
				$action_name,
				$action_type,
				makeEventDetailsTableUser($alert, $db_users),
				[bold($alert['subject']), BR(), BR(), zbx_nl2br($alert['message'])],
				makeActionTableStatus($alert),
				makeInformationList($info_icons)
			]);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput((new CUiWidget(uniqid(), [$table, $footer]))->setHeader(_('Action log')));
	}
}
