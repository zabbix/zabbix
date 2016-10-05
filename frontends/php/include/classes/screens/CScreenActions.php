<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
				$sortfield = 'description';
				$sortorder = ZBX_SORT_UP;
				break;

			case SCREEN_SORT_TRIGGERS_TYPE_DESC:
				$sortfield = 'description';
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

		$sql = 'SELECT a.alertid,a.clock,a.sendto,a.subject,a.message,a.status,a.retries,a.error,'.
					'a.userid,a.actionid,a.mediatypeid,mt.description'.
				' FROM events e,alerts a'.
					' LEFT JOIN media_type mt ON mt.mediatypeid=a.mediatypeid'.
				' WHERE e.eventid=a.eventid'.
					' AND alerttype='.ALERT_TYPE_MESSAGE;

		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
			$userid = CWebUser::$data['userid'];
			$userGroups = getUserGroupsByUserId($userid);
			$sql .= ' AND EXISTS ('.
					'SELECT NULL'.
					' FROM functions f,items i,hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
					' WHERE e.objectid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=hgg.hostid'.
					' GROUP BY f.triggerid'.
					' HAVING MIN(r.permission)>'.PERM_DENY.
					')';
		}

		$sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
		$alerts = DBfetchArray(DBselect($sql, $this->screenitem['elements']));

		order_result($alerts, $sortfield, $sortorder);

		$userids = [];

		foreach ($alerts as $alert) {
			if ($alert['userid'] != 0) {
				$userids[$alert['userid']] = true;
			}
		}

		if ($userids) {
			$dbUsers = API::User()->get([
				'output' => ['userid', 'alias', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			]);
		}

		// indicator of sort field
		$sort_div = (new CSpan())->addClass(($sortorder === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);

		// create alert table
		$table = (new CTableInfo())
			->setHeader([
				($sortfield === 'clock') ? [('Time'), $sort_div] : _('Time'),
				_('Action'),
				($sortfield === 'description') ? [_('Type'), $sort_div] : _('Type'),
				($sortfield === 'sendto') ? [_('Recipient(s)'), $sort_div] : _('Recipient(s)'),
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
			if ($alert['status'] == ALERT_STATUS_SENT) {
				$status = (new CSpan(_('Sent')))->addClass(ZBX_STYLE_GREEN);
			}
			elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
				$status = (new CSpan([
					_('In progress').':',
					BR(),
					_n('%1$s retry left', '%1$s retries left', ALERT_MAX_RETRIES - $alert['retries'])])
				)
					->addClass(ZBX_STYLE_YELLOW);
			}
			else {
				$status = (new CSpan(_('Not sent')))->addClass(ZBX_STYLE_RED);
			}

			$recipient = ($alert['userid'] != 0 && array_key_exists($alert['userid'], $dbUsers))
				? [bold(getUserFullname($dbUsers[$alert['userid']])), BR(), $alert['sendto']]
				: $alert['sendto'];

			$table->addRow([
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock']),
				array_key_exists($alert['actionid'], $actions) ? $actions[$alert['actionid']]['name'] : '',
				$alert['mediatypeid'] == 0 ? '' : $alert['description'],
				$recipient,
				[bold($alert['subject']), BR(), BR(), zbx_nl2br($alert['message'])],
				$status,
				$alert['error'] === '' ? '' : makeErrorIcon($alert['error'])
			]);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput((new CUiWidget(uniqid(), [$table, $footer]))->setHeader(_('Action log')));
	}
}
