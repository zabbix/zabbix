<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CControllerWidgetActionLogView extends CController {

	private $form;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>	'string',
			'fields' =>	'array'
		];

		$ret = $this->validateInput($fields);
		if ($ret) {
			/*
			 * @var array $fields
			 * @var int   $fields['sort_triggers']
			 * @var int   $fields['show_lines']
			*/
			$this->form = CWidgetConfig::getForm(WIDGET_ACTION_LOG, $this->getInput('fields', []));
			if (!empty($errors = $this->form->validate())) {
				$ret = false;
			}
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$fields = $this->form->getFieldsData();

		list($sortfield, $sortorder) = $this->getSorting($fields['sort_triggers']);
		$alerts = $this->getAlerts($sortfield, $sortorder, $fields['show_lines']);
		$db_users = $this->getDbUsers($alerts);

		$actions = API::Action()->get([
			'output' => ['actionid', 'name'],
			'actionids' => array_unique(zbx_objectValues($alerts, 'actionid')),
			'preservekeys' => true
		]);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_ACTION_LOG]),
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
		// TODO AV: remove direct SQL requests
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
	 * @return array
	 */
	private function getSorting($sort_triggers)
	{
		switch ($sort_triggers) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				$sortfield = 'clock';
				$sortorder = ZBX_SORT_UP;
				break;

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
			default:
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

		return [$sortfield, $sortorder];
	}
}
