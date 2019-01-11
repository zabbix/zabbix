<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CControllerWidgetActionLogView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_ACTION_LOG);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		list($sortfield, $sortorder) = self::getSorting($fields['sort_triggers']);
		$alerts = $this->getAlerts($sortfield, $sortorder, $fields['show_lines']);
		$db_users = $this->getDbUsers($alerts);

		$actions = API::Action()->get([
			'output' => ['actionid', 'name'],
			'actionids' => array_unique(zbx_objectValues($alerts, 'actionid')),
			'preservekeys' => true
		]);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'actions' => $actions,
			'alerts'  => $alerts,
			'db_users' => $db_users,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Get alerts.
	 *
	 * @param string $sortfield
	 * @param string $sortorder
	 * @param int    $show_lines
	 *
	 * @return array
	 */
	private function getAlerts($sortfield, $sortorder, $show_lines)
	{
		$sql = 'SELECT a.alertid,a.clock,a.sendto,a.subject,a.message,a.status,a.retries,a.error,'.
			'a.userid,a.actionid,a.mediatypeid,mt.description,mt.maxattempts'.
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
		$alerts = DBfetchArray(DBselect($sql, $show_lines));
		order_result($alerts, $sortfield, $sortorder);

		return $alerts;
	}

	/**
	 * Get users.
	 *
	 * @param array $alerts
	 *
	 * @return array
	 */
	private function getDbUsers(array $alerts)
	{
		$userids = [];

		foreach ($alerts as $alert) {
			$userids[$alert['userid']] = true;
		}
		unset($userids[0]);

		return $userids
			? API::User()->get([
				'output' => ['userid', 'alias', 'name', 'surname'],
				'userids' => array_keys($userids),
				'preservekeys' => true
			])
			: [];
	}

	/**
	 * Get sorting.
	 *
	 * @param int $sort_triggers
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getSorting($sort_triggers)
	{
		switch ($sort_triggers) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				return ['clock', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
			default:
				return ['clock', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_TYPE_ASC:
				return ['description', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_TYPE_DESC:
				return ['description', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_STATUS_ASC:
				return ['status', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_STATUS_DESC:
				return ['status', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_RECIPIENT_ASC:
				return ['sendto', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_RECIPIENT_DESC:
				return ['sendto', ZBX_SORT_DOWN];
		}
	}
}
