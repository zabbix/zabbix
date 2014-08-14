<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/maintenances.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of maintenance periods');
$page['file'] = 'maintenance.php';
$page['hist_arg'] = array('groupid');
$page['scripts'] = array('class.calendar.js');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'hosts' =>								array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groups' =>								array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostids' =>							array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groupids' =>							array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'groupid' =>							array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// maintenance
	'maintenanceid' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
	'maintenanceids' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 		null),
	'mname' =>								array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Name')),
	'maintenance_type' =>					array(T_ZBX_INT, O_OPT, null,	null,		'isset({save})'),
	'description' =>						array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'active_since' =>						array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	'isset({save})'),
	'active_till' =>						array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	'isset({save})'),
	'active_since_day' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_since_month' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_since_year' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_since_hour' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_since_minute' =>				array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_till_day' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_till_month' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_till_year' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_till_hour' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'active_till_minute' =>					array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_timeperiod_start_date_day' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_timeperiod_start_date_month' =>	array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_timeperiod_start_date_year' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_timeperiod_start_date_hour' =>		array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_timeperiod_start_date_minute' =>	array(T_ZBX_STR, O_OPT, null, 	NOT_EMPTY,	null),
	'new_timeperiod' =>						array(T_ZBX_STR, O_OPT, null,	null,		'isset({add_timeperiod})'),
	'timeperiods' =>						array(T_ZBX_STR, O_OPT, null,	null,		null),
	'del_timeperiodid' =>					array(null,      O_OPT, P_ACT,	DB_ID,		null),
	'edit_timeperiodid' =>					array(null,      O_OPT, P_ACT,	DB_ID,		null),
	'twb_groupid' =>						array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	// actions
	'go' =>									array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_timeperiod' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel_new_timeperiod' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>								array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>								array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>								array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>								array(T_ZBX_STR, O_OPT, P_SYS,		 null,	null),
	// form
	'form' =>								array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>						array(T_ZBX_INT, O_OPT, null,	null,		null)
);

check_fields($fields);

validate_sort_and_sortorder('name', ZBX_SORT_UP, array('name', 'maintenance_type', 'active_since', 'active_till'));

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isWritable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (isset($_REQUEST['maintenanceid'])) {
	$dbMaintenance = API::Maintenance()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'selectTimeperiods' => API_OUTPUT_EXTEND,
		'editable' => true,
		'maintenanceids' => getRequest('maintenanceid'),
	));
	if (empty($dbMaintenance)) {
		access_deny();
	}
}
if (isset($_REQUEST['go']) && (!isset($_REQUEST['maintenanceids']) || !is_array($_REQUEST['maintenanceids']))) {
	access_deny();
}
$_REQUEST['go'] = getRequest('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['maintenanceid'])) {
	unset($_REQUEST['maintenanceid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['cancel_new_timeperiod'])) {
	unset($_REQUEST['new_timeperiod']);
}
elseif (isset($_REQUEST['save'])) {
	if (isset($_REQUEST['maintenanceid'])) {
		$messageSuccess = _('Maintenance updated');
		$messageFailed = _('Cannot update maintenance');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$messageSuccess = _('Maintenance added');
		$messageFailed = _('Cannot add maintenance');
		$auditAction = AUDIT_ACTION_ADD;
	}

	$result = true;

	DBstart();

	if (!validateDateTime($_REQUEST['active_since_year'],
			$_REQUEST['active_since_month'],
			$_REQUEST['active_since_day'],
			$_REQUEST['active_since_hour'],
			$_REQUEST['active_since_minute'])) {
		info(_s('Invalid date "%s".', _('Active since')));
		$result = false;
	}
	if (!validateDateInterval($_REQUEST['active_since_year'],
			$_REQUEST['active_since_month'],
			$_REQUEST['active_since_day'])) {
		info(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active since')));
		$result = false;
	}
	if (!validateDateTime($_REQUEST['active_till_year'],
				$_REQUEST['active_till_month'],
				$_REQUEST['active_till_day'],
				$_REQUEST['active_till_hour'],
				$_REQUEST['active_till_minute'])) {
		info(_s('Invalid date "%s".', _('Active till')));
		$result = false;
	}
	if (!validateDateInterval($_REQUEST['active_till_year'], $_REQUEST['active_till_month'], $_REQUEST['active_till_day'])) {
		info(_s('"%s" must be between 1970.01.01 and 2038.01.18.', _('Active till')));
		$result = false;
	}

	if ($result) {
		if (isset($_REQUEST['active_since'])) {
			$activeSince = mktime(
				$_REQUEST['active_since_hour'],
				$_REQUEST['active_since_minute'],
				0,
				$_REQUEST['active_since_month'],
				$_REQUEST['active_since_day'],
				$_REQUEST['active_since_year']
			);
		}
		if (isset($_REQUEST['active_till'])) {
			$activeTill = mktime(
				$_REQUEST['active_till_hour'],
				$_REQUEST['active_till_minute'],
				0,
				$_REQUEST['active_till_month'],
				$_REQUEST['active_till_day'],
				$_REQUEST['active_till_year']
			);
		}

		$maintenance = array(
			'name' => $_REQUEST['mname'],
			'maintenance_type' => $_REQUEST['maintenance_type'],
			'description' => $_REQUEST['description'],
			'active_since' => $activeSince,
			'active_till' => $activeTill,
			'timeperiods' => getRequest('timeperiods', array()),
			'hostids' => getRequest('hostids', array()),
			'groupids' => getRequest('groupids', array())
		);

		if (isset($_REQUEST['maintenanceid'])) {
			$maintenance['maintenanceid'] = $_REQUEST['maintenanceid'];
			$result = API::Maintenance()->update($maintenance);
		}
		else {
			$result = API::Maintenance()->create($maintenance);
		}
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_MAINTENANCE, _('Name').NAME_DELIMITER.$_REQUEST['mname']);
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete']) || $_REQUEST['go'] == 'delete') {
	$maintenanceids = getRequest('maintenanceid', array());
	if (isset($_REQUEST['maintenanceids'])) {
		$maintenanceids = $_REQUEST['maintenanceids'];
	}

	zbx_value2array($maintenanceids);

	$maintenances = array();

	DBstart();

	foreach ($maintenanceids as $id => $maintenanceid) {
		$maintenances[$maintenanceid] = get_maintenance_by_maintenanceid($maintenanceid);
	}

	$result = API::Maintenance()->delete($maintenanceids);
	if ($result) {
		foreach ($maintenances as $maintenanceid => $maintenance) {
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MAINTENANCE,
				'Id ['.$maintenanceid.'] '._('Name').' ['.$maintenance['name'].']'
			);
		}
		unset($_REQUEST['form'], $_REQUEST['maintenanceid']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Maintenance deleted'), _('Cannot delete maintenance'));
}
elseif (isset($_REQUEST['add_timeperiod']) && isset($_REQUEST['new_timeperiod'])) {
	$new_timeperiod = $_REQUEST['new_timeperiod'];
	if ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
		$new_timeperiod['start_date'] = mktime($_REQUEST['new_timeperiod_start_date_hour'],
			$_REQUEST['new_timeperiod_start_date_minute'],
			0,
			$_REQUEST['new_timeperiod_start_date_month'],
			$_REQUEST['new_timeperiod_start_date_day'],
			$_REQUEST['new_timeperiod_start_date_year']);
	}

	// start time
	$new_timeperiod['start_time'] = ($new_timeperiod['hour'] * SEC_PER_HOUR) + ($new_timeperiod['minute'] * SEC_PER_MIN);

	// period
	$new_timeperiod['period'] = ($new_timeperiod['period_days'] * SEC_PER_DAY) + ($new_timeperiod['period_hours'] * SEC_PER_HOUR) + ($new_timeperiod['period_minutes'] * SEC_PER_MIN);

	// days of week
	if (!isset($new_timeperiod['dayofweek'])) {
		$dayofweek =  (!isset($new_timeperiod['dayofweek_su'])) ? '0' : '1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_sa'])) ? '0' : '1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_fr'])) ? '0' : '1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_th'])) ? '0' : '1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_we'])) ? '0' : '1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_tu'])) ? '0' : '1';
		$dayofweek .= (!isset($new_timeperiod['dayofweek_mo'])) ? '0' : '1';
		$new_timeperiod['dayofweek'] = bindec($dayofweek);
	}

	// months
	if (!isset($new_timeperiod['month'])) {
		$month =  (!isset($new_timeperiod['month_dec'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_nov'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_oct'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_sep'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_aug'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_jul'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_jun'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_may'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_apr'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_mar'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_feb'])) ? '0' : '1';
		$month .= (!isset($new_timeperiod['month_jan'])) ? '0' : '1';
		$new_timeperiod['month'] = bindec($month);
	}

	if ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
		if ($new_timeperiod['month_date_type'] > 0) {
			$new_timeperiod['day'] = 0;
		}
		else {
			$new_timeperiod['every'] = 0;
			$new_timeperiod['dayofweek'] = 0;
		}
	}

	$_REQUEST['timeperiods'] = getRequest('timeperiods', array());

	$result = false;
	if ($new_timeperiod['period'] < 300) {
		info(_('Incorrect maintenance period (minimum 5 minutes)'));
	}
	elseif ($new_timeperiod['hour'] > 23 || $new_timeperiod['minute'] > 59) {
		info(_('Incorrect maintenance period'));
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
		if (!validateDateTime($_REQUEST['new_timeperiod_start_date_year'],
				$_REQUEST['new_timeperiod_start_date_month'],
				$_REQUEST['new_timeperiod_start_date_day'],
				$_REQUEST['new_timeperiod_start_date_hour'],
				$_REQUEST['new_timeperiod_start_date_minute'])) {
			error(_('Invalid maintenance period'));
		}
		elseif (!validateDateInterval($_REQUEST['new_timeperiod_start_date_year'],
				$_REQUEST['new_timeperiod_start_date_month'],
				$_REQUEST['new_timeperiod_start_date_day'])) {
			error(_('Incorrect maintenance - date must be between 1970.01.01 and 2038.01.18'));
		}
		else {
			$result = true;
		}
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY && $new_timeperiod['every'] < 1) {
		info(_('Incorrect maintenance day period'));
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) {
		if ($new_timeperiod['every'] < 1) {
			info(_('Incorrect maintenance week period'));
		}
		elseif ($new_timeperiod['dayofweek'] < 1) {
			info(_('Incorrect maintenance days of week'));
		}
		else {
			$result = true;
		}
	}
	elseif ($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
		if ($new_timeperiod['month'] < 1) {
			info(_('Incorrect maintenance month period'));
		}
		elseif ($new_timeperiod['day'] == 0 && $new_timeperiod['dayofweek'] < 1) {
			info(_('Incorrect maintenance days of week'));
		}
		elseif (($new_timeperiod['day'] < 1 || $new_timeperiod['day'] > 31) && $new_timeperiod['dayofweek'] == 0) {
			info(_('Incorrect maintenance date'));
		}
		else {
			$result = true;
		}
	}
	else {
		$result = true;
	}
	show_messages();

	if ($result) {
		if (!isset($new_timeperiod['id'])) {
			if (!str_in_array($new_timeperiod, $_REQUEST['timeperiods'])) {
				array_push($_REQUEST['timeperiods'], $new_timeperiod);
			}
		}
		else {
			$id = $new_timeperiod['id'];
			unset($new_timeperiod['id']);
			$_REQUEST['timeperiods'][$id] = $new_timeperiod;
		}
		unset($_REQUEST['new_timeperiod']);
	}
}
elseif (isset($_REQUEST['del_timeperiodid'])) {
	$_REQUEST['timeperiods'] = getRequest('timeperiods', array());
	$delTimeperiodId = array_keys($_REQUEST['del_timeperiodid']);
	$delTimeperiodId = reset($delTimeperiodId);
	unset($_REQUEST['timeperiods'][$delTimeperiodId]);
}
elseif (isset($_REQUEST['edit_timeperiodid'])) {
	$_REQUEST['edit_timeperiodid'] = array_keys($_REQUEST['edit_timeperiodid']);
	$edit_timeperiodid = $_REQUEST['edit_timeperiodid'] = array_pop($_REQUEST['edit_timeperiodid']);
	$_REQUEST['timeperiods'] = getRequest('timeperiods', array());

	if (isset($_REQUEST['timeperiods'][$edit_timeperiodid])) {
		$_REQUEST['new_timeperiod'] = $_REQUEST['timeperiods'][$edit_timeperiodid];
		$_REQUEST['new_timeperiod']['id'] = $edit_timeperiodid;
		$_REQUEST['new_timeperiod']['start_date'] = $_REQUEST['timeperiods'][$edit_timeperiodid]['start_date'];
	}
}

$options = array(
	'groups' => array('editable' => 1),
	'groupid' => getRequest('groupid')
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;

/*
 * Display
 */
$data = array(
	'form' => getRequest('form')
);

if (!empty($data['form'])) {
	$data['maintenanceid'] = getRequest('maintenanceid');
	$data['form_refresh'] = getRequest('form_refresh', 0);

	if (isset($data['maintenanceid']) && !isset($_REQUEST['form_refresh'])) {
		$dbMaintenance = reset($dbMaintenance);
		$data['mname'] = $dbMaintenance['name'];
		$data['maintenance_type'] = $dbMaintenance['maintenance_type'];
		$data['active_since'] = $dbMaintenance['active_since'];
		$data['active_till'] = $dbMaintenance['active_till'];
		$data['description'] = $dbMaintenance['description'];

		// time periods
		$data['timeperiods'] = $dbMaintenance['timeperiods'];
		CArrayHelper::sort($data['timeperiods'], array('timeperiod_type', 'start_date'));

		// get hosts
		$data['hostids'] = API::Host()->get(array(
			'maintenanceids' => $data['maintenanceid'],
			'real_hosts' => true,
			'output' => array('hostid'),
			'editable' => true
		));
		$data['hostids'] = zbx_objectValues($data['hostids'], 'hostid');

		// get groupids
		$data['groupids'] = API::HostGroup()->get(array(
			'maintenanceids' => $data['maintenanceid'],
			'real_hosts' => true,
			'output' => array('groupid'),
			'editable' => true
		));
		$data['groupids'] = zbx_objectValues($data['groupids'], 'groupid');
	}
	else {
		$data['mname'] = getRequest('mname', '');
		$data['maintenance_type'] = getRequest('maintenance_type', 0);
		if (isset($_REQUEST['active_since'])) {
			$data['active_since'] = mktime($_REQUEST['active_since_hour'],
					$_REQUEST['active_since_minute'],
					0,
					$_REQUEST['active_since_month'],
					$_REQUEST['active_since_day'],
					$_REQUEST['active_since_year']);
		}
		else {
			$data['active_since'] = strtotime('today');
		}
		if (isset($_REQUEST['active_till'])) {
			$data['active_till'] = mktime($_REQUEST['active_till_hour'],
					$_REQUEST['active_till_minute'],
					0,
					$_REQUEST['active_till_month'],
					$_REQUEST['active_till_day'],
					$_REQUEST['active_till_year']);
		}
		else {
			$data['active_till'] = strtotime('tomorrow');
		}
		$data['description'] = getRequest('description', '');
		$data['timeperiods'] = getRequest('timeperiods', array());
		$data['hostids'] = getRequest('hostids', array());
		$data['groupids'] = getRequest('groupids', array());
	}

	// get groups
	$data['all_groups'] = API::HostGroup()->get(array(
		'editable' => true,
		'output' => array('groupid', 'name'),
		'real_hosts' => true,
		'preservekeys' => true
	));
	order_result($data['all_groups'], 'name');

	$data['twb_groupid'] = getRequest('twb_groupid', 0);
	if (!isset($data['all_groups'][$data['twb_groupid']])) {
		$twb_groupid = reset($data['all_groups']);
		$data['twb_groupid'] = $twb_groupid['groupid'];
	}

	// get hosts from selected twb group
	$data['hosts'] = API::Host()->get(array(
		'output' => array('hostid', 'name'),
		'real_hosts' => true,
		'editable' => true,
		'groupids' => $data['twb_groupid']
	));

	// selected hosts
	$hostsSelected = API::Host()->get(array(
		'output' => array('hostid', 'name'),
		'real_hosts' => true,
		'editable' => true,
		'hostids' => $data['hostids']
	));
	$data['hosts'] = array_merge($data['hosts'], $hostsSelected);
	$data['hosts'] = zbx_toHash($data['hosts'], 'hostid');
	order_result($data['hosts'], 'name');

	// render view
	$maintenanceView = new CView('configuration.maintenance.edit', $data);
	$maintenanceView->render();
	$maintenanceView->show();
}
else {
	// get maintenances
	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();

	$options = array(
		'output' => array('maintenanceid'),
		'editable' => true,
		'sortfield' => $sortfield,
		'sortorder' => $sortorder,
		'limit' => $config['search_limit'] + 1
	);

	if ($pageFilter->groupsSelected && $pageFilter->groupid > 0) {
		$options['groupids'] = $pageFilter->groupid;
	}
	else {
		$options['groupids'] = $config['dropdown_first_entry'] ? null : array();
	}

	$data['maintenances'] = API::Maintenance()->get($options);

	$data['paging'] = getPagingLine($data['maintenances']);

	// get list of maintenances
	$data['maintenances'] = API::Maintenance()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'maintenanceids' => zbx_objectValues($data['maintenances'], 'maintenanceid')
	));

	foreach ($data['maintenances'] as $key => $maintenance) {
		if ($maintenance['active_till'] < time()) {
			$data['maintenances'][$key]['status'] = MAINTENANCE_STATUS_EXPIRED;
		}
		elseif ($maintenance['active_since'] > time()) {
			$data['maintenances'][$key]['status'] = MAINTENANCE_STATUS_APPROACH;
		}
		else {
			$data['maintenances'][$key]['status'] = MAINTENANCE_STATUS_ACTIVE;
		}
	}

	order_result($data['maintenances'], $sortfield, $sortorder);

	$data['pageFilter'] = $pageFilter;

	// render view
	$maintenanceView = new CView('configuration.maintenance.list', $data);
	$maintenanceView->render();
	$maintenanceView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
