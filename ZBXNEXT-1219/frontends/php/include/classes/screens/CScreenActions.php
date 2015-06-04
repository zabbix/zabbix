<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		$sorttitle = _('Time');

		switch ($this->screenitem['sort_triggers']) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				$sortfield = 'clock';
				$sortorder = ZBX_SORT_UP;
				$sorttitle = _('Time');
				break;

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
				$sortfield = 'clock';
				$sortorder = ZBX_SORT_DOWN;
				$sorttitle = _('Time');
				break;

			case SCREEN_SORT_TRIGGERS_TYPE_ASC:
				$sortfield = 'description';
				$sortorder = ZBX_SORT_UP;
				$sorttitle = _('Type');
				break;

			case SCREEN_SORT_TRIGGERS_TYPE_DESC:
				$sortfield = 'description';
				$sortorder = ZBX_SORT_DOWN;
				$sorttitle = _('Type');
				break;

			case SCREEN_SORT_TRIGGERS_STATUS_ASC:
				$sortfield = 'status';
				$sortorder = ZBX_SORT_UP;
				$sorttitle = _('Status');
				break;

			case SCREEN_SORT_TRIGGERS_STATUS_DESC:
				$sortfield = 'status';
				$sortorder = ZBX_SORT_DOWN;
				$sorttitle = _('Status');
				break;

			case SCREEN_SORT_TRIGGERS_RECIPIENT_ASC:
				$sortfield = 'sendto';
				$sortorder = ZBX_SORT_UP;
				$sorttitle = _('Recipient(s)');
				break;

			case SCREEN_SORT_TRIGGERS_RECIPIENT_DESC:
				$sortfield = 'sendto';
				$sortorder = ZBX_SORT_DOWN;
				$sorttitle = _('Recipient(s)');
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

		if ($alerts) {
			$dbUsers = API::User()->get([
				'output' => ['userid', 'alias', 'name', 'surname'],
				'userids' => zbx_objectValues($alerts, 'userid'),
				'preservekeys' => true
			]);
		}

		// indicator of sort field
		$sortfieldSpan = new CSpan([$sorttitle, SPACE]);
		$sortorderSpan = new CSpan(SPACE, ($sortorder === ZBX_SORT_DOWN) ? 'icon_sortdown default_cursor' : 'icon_sortup default_cursor');

		// create alert table
		$actionTable = new CTableInfo();
		$actionTable->setHeader([
			($sortfield === 'clock') ? [$sortfieldSpan, $sortorderSpan] : _('Time'),
			_('Action'),
			($sortfield === 'description') ? [$sortfieldSpan, $sortorderSpan] : _('Type'),
			($sortfield === 'sendto') ? [$sortfieldSpan, $sortorderSpan] : _('Recipient(s)'),
			_('Message'),
			($sortfield === 'status') ? [$sortfieldSpan, $sortorderSpan] : _('Status'),
			_('Info')
		]);

		$actions = API::Action()->get([
			'output' => ['actionid', 'name'],
			'actionids' => array_unique(zbx_objectValues($alerts, 'actionid')),
			'preservekeys' => true
		]);

		foreach ($alerts as $alert) {
			if ($alert['status'] == ALERT_STATUS_SENT) {
				$status = new CSpan(_('Sent'), ZBX_STYLE_GREEN);
			}
			elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
				$status = new CSpan([
					_('In progress').':',
					BR(),
					_n('%1$s retry left', '%1$s retries left', ALERT_MAX_RETRIES - $alert['retries']),
				], ZBX_STYLE_ORANGE);
			}
			else {
				$status = new CSpan(_('Not sent'), ZBX_STYLE_RED);
			}

			$recipient = $alert['userid']
				? [bold(getUserFullname($dbUsers[$alert['userid']])), BR(), $alert['sendto']]
				: $alert['sendto'];

			$message = [
				bold(_('Subject').':'),
				br(),
				$alert['subject'],
				br(),
				br(),
				bold(_('Message').':'),
				br(),
				$alert['message']
			];

			if (zbx_empty($alert['error'])) {
				$info = '';
			}
			else {
				$info = new CDiv(SPACE, 'status_icon iconerror');
				$info->setHint($alert['error'], ZBX_STYLE_RED);
			}

			$actionTable->addRow([
				new CCol(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $alert['clock'])),
				new CCol($actions[$alert['actionid']]['name']),
				new CCol(($alert['mediatypeid'] == 0) ? '' : $alert['description']),
				new CCol($recipient),
				new CCol($message),
				new CCol($status),
				new CCol($info)
			]);
		}

		return $this->getOutput($actionTable);
	}
}
