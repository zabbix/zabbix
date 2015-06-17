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


require_once dirname(__FILE__).'/../../blocks.inc.php';

class CScreenHostgroupTriggers extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$params = [
			'groupids' => null,
			'hostids' => null,
			'maintenance' => null,
			'severity' => null,
			'limit' => $this->screenitem['elements'],
			'backUrl' => $this->pageFile
		];

		// by default triggers are sorted by date desc, do we need to override this?
		switch ($this->screenitem['sort_triggers']) {
			case SCREEN_SORT_TRIGGERS_DATE_DESC:
				$params['sortfield'] = 'lastchange';
				$params['sortorder'] = ZBX_SORT_DOWN;
				break;
			case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
				$params['sortfield'] = 'priority';
				$params['sortorder'] = ZBX_SORT_DOWN;
				break;
			case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
				// a little black magic here - there is no such field 'hostname' in 'triggers',
				// but API has a special case for sorting by hostname
				$params['sortfield'] = 'hostname';
				$params['sortorder'] = ZBX_SORT_UP;
				break;
		}

		if ($this->screenitem['resourceid'] > 0) {
			$hostgroup = API::HostGroup()->get([
				'groupids' => $this->screenitem['resourceid'],
				'output' => API_OUTPUT_EXTEND
			]);
			$hostgroup = reset($hostgroup);

			$item = (new CSpan(_('Group').NAME_DELIMITER.$hostgroup['name']))->addClass('white');
			$params['groupids'] = $hostgroup['groupid'];
		}
		else {
			$groupid = getRequest('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
			$hostid = getRequest('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

			CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
			CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

			// get groups
			$groups = API::HostGroup()->get([
				'monitored_hosts' => true,
				'output' => API_OUTPUT_EXTEND
			]);
			order_result($groups, 'name');

			// get hosts
			$options = [
				'monitored_hosts' => true,
				'output' => API_OUTPUT_EXTEND
			];
			if ($groupid > 0) {
				$options['groupids'] = $groupid;
			}
			$hosts = API::Host()->get($options);
			$hosts = zbx_toHash($hosts, 'hostid');
			order_result($hosts, 'host');

			if (!isset($hosts[$hostid])) {
				$hostid = 0;
			}

			if ($groupid > 0) {
				$params['groupids'] = $groupid;
			}
			if ($hostid > 0) {
				$params['hostids'] = $hostid;
			}

			$item = new CForm(null, $this->pageFile);

			$groupComboBox = new CComboBox('tr_groupid', $groupid, 'submit()');
			$groupComboBox->addItem(0, _('all'));
			foreach ($groups as $group) {
				$groupComboBox->addItem($group['groupid'], $group['name']);
			}

			$hostComboBox = new CComboBox('tr_hostid', $hostid, 'submit()');
			$hostComboBox->addItem(0, _('all'));
			foreach ($hosts as $host) {
				$hostComboBox->addItem($host['hostid'], $host['host']);
			}

			if ($this->mode == SCREEN_MODE_EDIT) {
				$groupComboBox->setAttribute('disabled', 'disabled');
				$hostComboBox->setAttribute('disabled', 'disabled');
			}

			$item->addItem([_('Group').SPACE, $groupComboBox]);
			$item->addItem([SPACE._('Host').SPACE, $hostComboBox]);
		}

		$params['screenid'] = $this->screenid;

		$output = new CUiWidget('hat_htstatus', make_latest_issues($params));
		$output->setDoubleHeader([_('HOST GROUP ISSUES'), SPACE, '['.zbx_date2str(TIME_FORMAT_SECONDS).']', SPACE],
			$item
		);

		return $this->getOutput($output);
	}
}
