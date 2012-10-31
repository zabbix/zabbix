<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
			case SCREEN_SORT_TRIGGERS_RETRIES_LEFT_ASC:
				$sortfield = 'retries';
				$sortorder = ZBX_SORT_UP;
				$sorttitle = _('Retries left');
				break;
			case SCREEN_SORT_TRIGGERS_RETRIES_LEFT_DESC:
				$sortfield = 'retries';
				$sortorder = ZBX_SORT_DOWN;
				$sorttitle = _('Retries left');
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

		$available_triggers = get_accessible_triggers(PERM_READ, array());

		$sql = 'SELECT a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,a.status,a.retries,a.error'.
				' FROM events e,alerts a'.
					' LEFT JOIN media_type mt ON mt.mediatypeid=a.mediatypeid '.
				' WHERE e.eventid=a.eventid'.
					' AND alerttype IN ('.ALERT_TYPE_MESSAGE.') '.
					' AND '.DBcondition('e.objectid', $available_triggers).
					' AND '.DBin_node('a.alertid').' '.
				' ORDER BY '.$sortfield.' '.$sortorder;
		$alerts = DBfetchArray(DBselect($sql, $this->screenitem['elements']));

		order_result($alerts, $sortfield, $sortorder);

		// indicator of sort field
		$sortfieldSpan = new CSpan(array($sorttitle, SPACE));
		$sortorderSpan = new CSpan(SPACE, ($sortorder == ZBX_SORT_DOWN) ? 'icon_sortdown default_cursor' : 'icon_sortup default_cursor');

		// create alert table
		$actionTable = new CTableInfo(_('No actions found.'));
		$actionTable->setHeader(array(
			is_show_all_nodes() ? _('Nodes') : null,
			($sortfield == 'clock') ? array($sortfieldSpan, $sortorderSpan) : _('Time'),
			($sortfield == 'description') ? array($sortfieldSpan, $sortorderSpan) : _('Type'),
			($sortfield == 'status') ? array($sortfieldSpan, $sortorderSpan) : _('Status'),
			($sortfield == 'retries') ? array($sortfieldSpan, $sortorderSpan) : _('Retries left'),
			($sortfield == 'sendto') ? array($sortfieldSpan, $sortorderSpan) : _('Recipient(s)'),
			_('Message'),
			_('Error')
		));

		foreach ($alerts as $alert) {
			if ($alert['status'] == ALERT_STATUS_SENT) {
				$status = new CSpan(_('sent'), 'green');
				$retries = new CSpan(SPACE, 'green');
			}
			elseif ($alert['status'] == ALERT_STATUS_NOT_SENT) {
				$status = new CSpan(_('In progress'), 'orange');
				$retries = new CSpan(ALERT_MAX_RETRIES - $alert['retries'], 'orange');
			}
			else {
				$status = new CSpan(_('not sent'), 'red');
				$retries = new CSpan(0, 'red');
			}

			$message = array(
				bold(_('Subject').': '),
				br(),
				$alert['subject'],
				br(),
				br(),
				bold(_('Message').': '),
				br(),
				$alert['message']
			);

			$error = empty($alert['error']) ? new CSpan(SPACE, 'off') : new CSpan($alert['error'], 'on');

			$actionTable->addRow(array(
				get_node_name_by_elid($alert['alertid']),
				new CCol(zbx_date2str(HISTORY_OF_ACTIONS_DATE_FORMAT, $alert['clock']), 'top'),
				new CCol(!empty($alert['description']) ? $alert['description'] : '-', 'top'),
				new CCol($status, 'top'),
				new CCol($retries, 'top'),
				new CCol($alert['sendto'], 'top'),
				new CCol($message, 'top pre'),
				new CCol($error, 'wraptext top')
			));
		}

		return $this->getOutput($actionTable);
	}
}
