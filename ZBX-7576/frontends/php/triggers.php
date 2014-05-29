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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of triggers');
$page['file'] = 'triggers.php';
$page['hist_arg'] = array('hostid', 'groupid');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'hostid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'triggerid' =>			array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'(isset({form})&&({form}=="update"))'),
	'copy_type' =>			array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	'isset({copy})'),
	'copy_mode' =>			array(T_ZBX_INT, O_OPT, P_SYS,	IN('0'),	null),
	'type' =>				array(T_ZBX_INT, O_OPT, null,	IN('0,1'),	null),
	'description' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})', _('Name')),
	'expression' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})'),
	'priority' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), 'isset({save})'),
	'comments' =>			array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'url' =>				array(T_ZBX_STR, O_OPT, null,	null,		'isset({save})'),
	'status' =>				array(T_ZBX_STR, O_OPT, null,	null,		null),
	'input_method' =>		array(T_ZBX_INT, O_OPT, null,	NOT_EMPTY,	'isset({toggle_input_method})'),
	'expr_temp' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({add_expression})||isset({and_expression})||isset({or_expression})||isset({replace_expression}))', _('Expression')),
	'expr_target_single' => array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'(isset({and_expression})||isset({or_expression})||isset({replace_expression}))'),
	'dependencies' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'new_dependency' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID.'{}>0', 'isset({add_dependency})'),
	'g_triggerid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'copy_targetid' =>		array(T_ZBX_INT, O_OPT, null,	DB_ID,		null),
	'filter_groupid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({copy})&&(isset({copy_type})&&({copy_type}==0))'),
	'showdisabled' =>		array(T_ZBX_INT, O_OPT, P_SYS,	IN('0,1'),	null),
	'massupdate' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'visible' =>			array(T_ZBX_STR, O_OPT, null,	null,		null),
	// actions
	'go' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'toggle_input_method' =>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_expression' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'and_expression' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'or_expression' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'replace_expression' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'remove_expression' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'test_expression' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'add_dependency' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'group_enable' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'group_disable' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'group_delete' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'copy' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'mass_save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>				array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' =>		array(T_ZBX_INT, O_OPT, null,	null,		null)
);
$_REQUEST['showdisabled'] = get_request('showdisabled', CProfile::get('web.triggers.showdisabled', 1));

check_fields($fields);
validate_sort_and_sortorder('description', ZBX_SORT_UP);

$_REQUEST['status'] = isset($_REQUEST['status']) ? TRIGGER_STATUS_ENABLED : TRIGGER_STATUS_DISABLED;
$_REQUEST['type'] = isset($_REQUEST['type']) ? TRIGGER_MULT_EVENT_ENABLED : TRIGGER_MULT_EVENT_DISABLED;
$_REQUEST['go'] = get_request('go', 'none');

// validate permissions
if (get_request('triggerid', false)) {
	$triggers = API::Trigger()->get(array(
		'triggerids' => $_REQUEST['triggerid'],
		'preservekeys' => true,
		'filter' => array('flags' => ZBX_FLAG_DISCOVERY_NORMAL),
		'editable' => true
	));
	if (empty($triggers)) {
		access_deny();
	}
}
elseif (get_request('hostid', 0) > 0) {
	$hosts = API::Host()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'output' => API_OUTPUT_EXTEND,
		'templated_hosts' => true,
		'editable' => true
	));
	if (empty($hosts)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['add_expression'])) {
	$_REQUEST['expression'] = $_REQUEST['expr_temp'];
	$_REQUEST['expr_temp'] = '';
}
elseif (isset($_REQUEST['and_expression'])) {
	$_REQUEST['expr_action'] = '&';
}
elseif (isset($_REQUEST['or_expression'])) {
	$_REQUEST['expr_action'] = '|';
}
elseif (isset($_REQUEST['replace_expression'])) {
	$_REQUEST['expr_action'] = 'r';
}
elseif (isset($_REQUEST['remove_expression']) && zbx_strlen($_REQUEST['remove_expression'])) {
	$_REQUEST['expr_action'] = 'R';
	$_REQUEST['expr_target_single'] = $_REQUEST['remove_expression'];
}
elseif (isset($_REQUEST['clone']) && isset($_REQUEST['triggerid'])) {
	unset($_REQUEST['triggerid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	$trigger = array(
		'expression' => $_REQUEST['expression'],
		'description' => $_REQUEST['description'],
		'priority' => $_REQUEST['priority'],
		'status' => $_REQUEST['status'],
		'type' => $_REQUEST['type'],
		'comments' => $_REQUEST['comments'],
		'url' => $_REQUEST['url'],
		'dependencies' => zbx_toObject(get_request('dependencies', array()), 'triggerid')
	);

	if (isset($_REQUEST['triggerid'])) {
		$trigger['triggerid'] = $_REQUEST['triggerid'];
		$result = API::Trigger()->update($trigger);
		show_messages($result, _('Trigger updated'), _('Cannot update trigger'));
	}
	else {
		$result = API::Trigger()->create($trigger);
		show_messages($result, _('Trigger added'), _('Cannot add trigger'));
	}
	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['triggerid'])) {
	DBstart();
	$result = API::Trigger()->delete($_REQUEST['triggerid']);
	$result = DBend($result);
	show_messages($result, _('Trigger deleted'), _('Cannot delete trigger'));

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['triggerid']);
	}
}
elseif (isset($_REQUEST['add_dependency']) && isset($_REQUEST['new_dependency'])) {
	if (!isset($_REQUEST['dependencies'])) {
		$_REQUEST['dependencies'] = array();
	}
	foreach ($_REQUEST['new_dependency'] as $triggerid) {
		if (!uint_in_array($triggerid, $_REQUEST['dependencies'])) {
			array_push($_REQUEST['dependencies'], $triggerid);
		}
	}
}
elseif ($_REQUEST['go'] == 'massupdate' && isset($_REQUEST['mass_save']) && isset($_REQUEST['g_triggerid'])) {
	$visible = get_request('visible', array());

	// update triggers
	$triggersToUpdate = array();
	foreach ($_REQUEST['g_triggerid'] as $triggerid) {
		$trigger = array('triggerid' => $triggerid);

		if (isset($visible['priority'])) {
			$trigger['priority'] = get_request('priority');
		}
		if (isset($visible['dependencies'])) {
			$trigger['dependencies'] = zbx_toObject(get_request('dependencies', array()), 'triggerid');
		}

		$triggersToUpdate[] = $trigger;
	}

	DBstart();
	$result = API::Trigger()->update($triggersToUpdate);
	$result = DBend($result);
	show_messages($result, _('Trigger updated'), _('Cannot update trigger'));

	if ($result) {
		unset($_REQUEST['massupdate'], $_REQUEST['form']);
	}
	$go_result = $result;
}
elseif (str_in_array($_REQUEST['go'], array('activate', 'disable')) && isset($_REQUEST['g_triggerid'])) {
	$go_result = true;

	if ($_REQUEST['go'] == 'activate') {
		$status = TRIGGER_STATUS_ENABLED;
		$statusOld = array('status' => TRIGGER_STATUS_DISABLED);
		$statusNew = array('status' => TRIGGER_STATUS_ENABLED);
	}
	else {
		$status = TRIGGER_STATUS_DISABLED;
		$statusOld = array('status' => TRIGGER_STATUS_ENABLED);
		$statusNew = array('status' => TRIGGER_STATUS_DISABLED);
	}

	try {
		DBstart();

		// get requested triggers with permission check
		$db_triggers = API::Trigger()->get(array(
			'triggerids' => $_REQUEST['g_triggerid'],
			'output' => array('triggerid', 'status'),
			'preservekeys' => true,
			'editable' => true
		));

		if ($db_triggers) {
			// triggerids which status must be changed
			$triggerIdsToUpdate = array();
			foreach ($db_triggers as $triggerid => $trigger){
				if ($trigger['status'] != $status) {
					$triggerIdsToUpdate[] = $triggerid;
				}
			}

			// triggerids to gather child triggers
			$childTriggerIds = array_keys($db_triggers);

			do {
				// gather all triggerids which status should be changed including child triggers
				$db_triggers = API::Trigger()->get(array(
					'filter' => array('templateid' => $childTriggerIds),
					'output' => array('triggerid', 'status'),
					'preservekeys' => true,
					'nopermissions' => true
				));
				foreach ($db_triggers as $triggerid => $trigger) {
					if ($trigger['status'] != $status) {
						$triggerIdsToUpdate[] = $triggerid;
					}
				}
				$childTriggerIds = array_keys($db_triggers);
			} while (!empty($childTriggerIds));

			DB::update('triggers', array(
				'values' => array('status' => $status),
				'where' => array('triggerid' => $triggerIdsToUpdate)
			));

			// if disable trigger, unknown event must be created
			if ($status == TRIGGER_STATUS_DISABLED) {
				$valueTriggerIds = array();
				$db_triggers = DBselect(
					'SELECT t.triggerid'.
					' FROM triggers t,functions f,items i,hosts h'.
					' WHERE t.triggerid=f.triggerid'.
						' AND f.itemid=i.itemid'.
						' AND i.hostid=h.hostid'.
						' AND '.dbConditionInt('t.triggerid', $triggerIdsToUpdate).
						' AND t.value_flags='.TRIGGER_VALUE_FLAG_NORMAL.
						' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'
				);
				while ($row = DBfetch($db_triggers)) {
					$valueTriggerIds[] = $row['triggerid'];
				}

				if (!empty($valueTriggerIds)) {
					DB::update('triggers', array(
						'values' => array(
							'value_flags' => TRIGGER_VALUE_FLAG_UNKNOWN,
							'error' => _('Trigger status became "Disabled".')
						),
						'where' => array('triggerid' => $valueTriggerIds)
					));

					addUnknownEvent($valueTriggerIds);
				}
			}

			// get updated triggers with additional data
			$db_triggers = API::Trigger()->get(array(
				'triggerids' => $triggerIdsToUpdate,
				'output' => array('triggerid', 'description'),
				'preservekeys' => true,
				'selectHosts' => API_OUTPUT_EXTEND,
				'nopermissions' => true
			));
			foreach ($db_triggers as $triggerid => $trigger) {
				$host = reset($trigger['hosts']);
				add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, $triggerid,
					$host['host'].': '.$trigger['description'], 'triggers', $statusOld, $statusNew);
			}
		}

		DBend(true);
	}
	catch(Exception $e) {
		DBend(false);
		$go_result = false;
	}

	show_messages($go_result, _('Status updated'), _('Cannot update status'));
}
elseif ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['copy']) && isset($_REQUEST['g_triggerid'])) {
	if (isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type'])) {
		if ($_REQUEST['copy_type'] == 0) { // hosts
			$hosts_ids = $_REQUEST['copy_targetid'];
		}
		else { // groups
			$hosts_ids = array();
			$group_ids = $_REQUEST['copy_targetid'];

			$db_hosts = DBselect(
				'SELECT DISTINCT h.hostid'.
				' FROM hosts h,hosts_groups hg'.
				' WHERE h.hostid=hg.hostid'.
					' AND '.dbConditionInt('hg.groupid', $group_ids)
			);
			while ($db_host = DBfetch($db_hosts)) {
				$hosts_ids[] = $db_host['hostid'];
			}
		}

		DBstart();
		$go_result = copyTriggersToHosts($_REQUEST['g_triggerid'], $hosts_ids, get_request('hostid'));
		$go_result = DBend($go_result);

		show_messages($go_result, _('Trigger added'), _('Cannot add trigger'));
		$_REQUEST['go'] = 'none2';
	}
	else {
		show_error_message(_('No target selected'));
	}
}
elseif ($_REQUEST['go'] == 'delete' && isset($_REQUEST['g_triggerid'])) {
	$go_result = API::Trigger()->delete($_REQUEST['g_triggerid']);
	show_messages($go_result, _('Triggers deleted'), _('Cannot delete triggers'));
}
if ($_REQUEST['go'] != 'none' && !empty($go_result)) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray(\''.$path.'\')');
	$_REQUEST['go'] = 'none';
}

/*
 * Display
 */
if ($_REQUEST['go'] == 'massupdate' && isset($_REQUEST['g_triggerid'])) {
	$triggersView = new CView('configuration.triggers.massupdate', getTriggerMassupdateFormData());
	$triggersView->render();
	$triggersView->show();
}
elseif (isset($_REQUEST['form'])) {
	$triggersView = new CView('configuration.triggers.edit', getTriggerFormData());
	$triggersView->render();
	$triggersView->show();
}
elseif ($_REQUEST['go'] == 'copy_to' && isset($_REQUEST['g_triggerid'])) {
	$triggersView = new CView('configuration.copy.elements', getCopyElementsFormData('g_triggerid', _('CONFIGURATION OF TRIGGERS')));
	$triggersView->render();
	$triggersView->show();
}
else {
	$data = array(
		'showdisabled' => get_request('showdisabled', 1),
		'parent_discoveryid' => null,
		'triggers' => array()
	);
	CProfile::update('web.triggers.showdisabled', $data['showdisabled'], PROFILE_TYPE_INT);

	$data['pageFilter'] = new CPageFilter(array(
		'groups' => array('not_proxy_hosts' => true, 'editable' => true),
		'hosts' => array('templated_hosts' => true, 'editable' => true),
		'triggers' => array('editable' => true),
		'groupid' => get_request('groupid', null),
		'hostid' => get_request('hostid', null),
		'triggerid' => get_request('triggerid', null)
	));
	if ($data['pageFilter']->triggerid > 0) {
		$data['triggerid'] = $data['pageFilter']->triggerid;
	}
	$data['groupid'] = $data['pageFilter']->groupid;
	$data['hostid'] = $data['pageFilter']->hostid;

	// get triggers
	$sortfield = getPageSortField('description');
	if ($data['pageFilter']->hostsSelected) {
		$options = array(
			'editable' => true,
			'output' => API_OUTPUT_SHORTEN,
			'sortfield' => $sortfield,
			'limit' => $config['search_limit'] + 1
		);
		if (empty($data['showdisabled'])) {
			$options['filter']['status'] = TRIGGER_STATUS_ENABLED;
		}
		if ($data['pageFilter']->hostid > 0) {
			$options['hostids'] = $data['pageFilter']->hostid;
		}
		elseif ($data['pageFilter']->groupid > 0) {
			$options['groupids'] = $data['pageFilter']->groupid;
		}
		$data['triggers'] = API::Trigger()->get($options);
	}
	$data['paging'] = getPagingLine($data['triggers']);

	$data['triggers'] = API::Trigger()->get(array(
		'triggerids' => zbx_objectValues($data['triggers'], 'triggerid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => API_OUTPUT_EXTEND,
		'selectItems' => API_OUTPUT_EXTEND,
		'selectFunctions' => API_OUTPUT_EXTEND,
		'selectDependencies' => API_OUTPUT_EXTEND,
		'selectDiscoveryRule' => API_OUTPUT_EXTEND
	));
	order_result($data['triggers'], $sortfield, getPageSortOrder());

	// get real hosts
	$data['realHosts'] = getParentHostsByTriggers($data['triggers']);

	// render view
	$triggersView = new CView('configuration.triggers.list', $data);
	$triggersView->render();
	$triggersView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
