<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

$page['file'] = 'tr_status.php';
$page['title'] = _('Status of triggers');
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array('class.cswitcher.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);


if($page['type'] == PAGE_TYPE_HTML){
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

// js templates
require_once dirname(__FILE__).'/include/views/js/general.script.confirm.js.php';


//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>				array(T_ZBX_INT, O_OPT,	 	P_SYS,	DB_ID, 					null),
		'hostid'=>				array(T_ZBX_INT, O_OPT,	 	P_SYS,	DB_ID, 					null),

		'fullscreen'=>			array(T_ZBX_INT, O_OPT,		P_SYS,	IN('0,1'),				null),
		'btnSelect'=>			array(T_ZBX_STR, O_OPT,  	null,  	null, 					null),
// filter
		'filter_rst'=>			array(T_ZBX_STR, O_OPT,		P_ACT,	null,	NULL),
		'filter_set'=>			array(T_ZBX_STR, O_OPT,		P_ACT,	null,	NULL),
		'show_triggers'=>		array(T_ZBX_INT, O_OPT,  	null, 	null, 	null),
		'show_events'=>			array(T_ZBX_INT, O_OPT,		P_SYS,	null,	null),
		'ack_status'=>			array(T_ZBX_INT, O_OPT,		P_SYS,	null,	null),
		'show_severity'=>		array(T_ZBX_INT, O_OPT,		P_SYS,	null,	null),
		'show_details'=>		array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'show_maintenance'=>	array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'status_change_days'=>	array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'status_change'=>		array(T_ZBX_INT, O_OPT,  	null,	null, 	null),
		'txt_select'=>			array(T_ZBX_STR, O_OPT,  	null,	null, 	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'favstate'=>	array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})')
	);

	check_fields($fields);

	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.tr_status.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit();
	}
//--------

	$config = select_config();

	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
			'with_monitored_triggers' => 1,
		),
		'hosts' => array(
			'monitored_hosts' => 1,
			'with_monitored_triggers' => 1,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;


/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['show_details'] =	0;
		$_REQUEST['show_maintenance'] =	1;
		$_REQUEST['show_triggers'] = TRIGGERS_OPTION_ONLYTRUE;
		$_REQUEST['show_events'] = EVENTS_OPTION_NOEVENT;
		$_REQUEST['ack_status'] = ZBX_ACK_STS_ANY;
		$_REQUEST['show_severity'] = -1;
		$_REQUEST['txt_select'] = '';
		$_REQUEST['status_change'] = 0;
		$_REQUEST['status_change_days'] = 14;
	}
	else{
		if(isset($_REQUEST['filter_set'])){
			$_REQUEST['show_details'] = get_request('show_details', 0);
			$_REQUEST['show_maintenance'] = get_request('show_maintenance', 0);
			$_REQUEST['status_change'] = get_request('status_change', 0);
			$_REQUEST['show_triggers'] = get_request('show_triggers', TRIGGERS_OPTION_ONLYTRUE);
		}
		else{
			$_REQUEST['show_details'] = get_request('show_details',	CProfile::get('web.tr_status.filter.show_details', 0));
			$_REQUEST['show_maintenance'] = get_request('show_maintenance',	CProfile::get('web.tr_status.filter.show_maintenance', 1));
			$_REQUEST['status_change'] = get_request('status_change', CProfile::get('web.tr_status.filter.status_change', 0));
			$_REQUEST['show_triggers'] = TRIGGERS_OPTION_ONLYTRUE;
		}
		$_REQUEST['show_events'] = get_request('show_events', CProfile::get('web.tr_status.filter.show_events', EVENTS_OPTION_NOEVENT));
		$_REQUEST['ack_status'] = get_request('ack_status', CProfile::get('web.tr_status.filter.ack_status', ZBX_ACK_STS_ANY));
		$_REQUEST['show_severity'] = get_request('show_severity', CProfile::get('web.tr_status.filter.show_severity', -1));
		$_REQUEST['status_change_days'] = get_request('status_change_days', CProfile::get('web.tr_status.filter.status_change_days', 14));
		$_REQUEST['txt_select'] = get_request('txt_select', CProfile::get('web.tr_status.filter.txt_select', ''));

		if(EVENT_ACK_DISABLED == $config['event_ack_enable']){
			if(!str_in_array($_REQUEST['show_events'], array(EVENTS_OPTION_NOEVENT, EVENTS_OPTION_ALL))){
				$_REQUEST['show_events'] = EVENTS_OPTION_NOEVENT;
			}
			$_REQUEST['ack_status'] = ZBX_ACK_STS_ANY;
		}
	}

	if(get_request('show_events') != CProfile::get('web.tr_status.filter.show_events')){
		uncheckTableRows();
	}
//--

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.tr_status.filter.show_details', $_REQUEST['show_details'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.show_maintenance', $_REQUEST['show_maintenance'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.show_events', $_REQUEST['show_events'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.ack_status', $_REQUEST['ack_status'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.show_severity', $_REQUEST['show_severity'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.txt_select', $_REQUEST['txt_select'], PROFILE_TYPE_STR);
		CProfile::update('web.tr_status.filter.status_change', $_REQUEST['status_change'], PROFILE_TYPE_INT);
		CProfile::update('web.tr_status.filter.status_change_days', $_REQUEST['status_change_days'], PROFILE_TYPE_INT);
	}

	$show_triggers = $_REQUEST['show_triggers'];
	$show_events = $_REQUEST['show_events'];
	$show_severity = $_REQUEST['show_severity'];
	$ack_status = $_REQUEST['ack_status'];
// --------------
	validate_sort_and_sortorder('lastchange', ZBX_SORT_DOWN);

	$mute = CProfile::get('web.tr_status.mute', 0);
	if(isset($audio) && !$mute){
		play_sound($audio);
	}

	$trigg_wdgt = new CWidget();

	$r_form = new CForm('get');
	$r_form->addItem(array(_('Group') . SPACE, $pageFilter->getGroupsCB(true)));
	$r_form->addItem(array(SPACE . _('Host') . SPACE, $pageFilter->getHostsCB(true)));
	$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);

	$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));
	$trigg_wdgt->addPageHeader(_('STATUS OF TRIGGERS').SPACE.'['.zbx_date2str(_('d M Y H:i:s')).']', $fs_icon);
	$trigg_wdgt->addHeader(_('Triggers'), $r_form);
	$trigg_wdgt->addHeaderRowNumber();

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable(null, null, 'get');//,'tr_status.php?filter_set=1','POST',null,'sform');
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');

	$filterForm->addVar('fullscreen', $_REQUEST['fullscreen']);
	$filterForm->addVar('groupid', $_REQUEST['groupid']);
	$filterForm->addVar('hostid', $_REQUEST['hostid']);

	$tr_select = new CComboBox('show_triggers', $show_triggers);
	$tr_select->addItem(TRIGGERS_OPTION_ALL, _('Any'));
	$tr_select->additem(TRIGGERS_OPTION_ONLYTRUE, _('Problem'));
	$filterForm->addRow(_('Triggers status'), $tr_select);

	if($config['event_ack_enable']){
		$cb_ack_status = new CComboBox('ack_status', $ack_status);
		$cb_ack_status->addItem(ZBX_ACK_STS_ANY, _('Any'));
		$cb_ack_status->additem(ZBX_ACK_STS_WITH_UNACK, _('With unacknowledged events'));
		$cb_ack_status->additem(ZBX_ACK_STS_WITH_LAST_UNACK, _('With last event unacknowledged'));
		$filterForm->addRow(_('Acknowledge status'), $cb_ack_status);
	}

	$ev_select = new CComboBox('show_events', $_REQUEST['show_events']);
	$ev_select->addItem(EVENTS_OPTION_NOEVENT, _('Hide all'));
	$ev_select->addItem(EVENTS_OPTION_ALL, _('Show all').' ('.$config['event_expire'].' '.(($config['event_expire'] > 1) ? _('Days') : _('Day')).')');
	if($config['event_ack_enable']){
		$ev_select->addItem(EVENTS_OPTION_NOT_ACK, _('Show unacknowledged').' ('.$config['event_expire'].' '.(($config['event_expire'] > 1) ? _('Days') : _('Day')).')');
	}
	$filterForm->addRow(_('Events'), $ev_select);

	$severity_select = new CComboBox('show_severity', $show_severity);
	$cb_items = array(
		-1 => _('All'),
		TRIGGER_SEVERITY_NOT_CLASSIFIED => getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED),
		TRIGGER_SEVERITY_INFORMATION => getSeverityCaption(TRIGGER_SEVERITY_INFORMATION),
		TRIGGER_SEVERITY_WARNING => getSeverityCaption(TRIGGER_SEVERITY_WARNING),
		TRIGGER_SEVERITY_AVERAGE => getSeverityCaption(TRIGGER_SEVERITY_AVERAGE),
		TRIGGER_SEVERITY_HIGH => getSeverityCaption(TRIGGER_SEVERITY_HIGH),
		TRIGGER_SEVERITY_DISASTER => getSeverityCaption(TRIGGER_SEVERITY_DISASTER),
	);
	$severity_select->addItems($cb_items);
	$filterForm->addRow(_('Min severity'), $severity_select);

	$action = 'javascript: this.checked ? $("status_change_days").enable() : $("status_change_days").disable()';
	$sts_change_days_cb = new CNumericBox('status_change_days', $_REQUEST['status_change_days'], 4);
	if(!$_REQUEST['status_change']) $sts_change_days_cb->setAttribute('disabled', 'disabled');
    $sts_change_days_cb->addStyle('vertical-align: middle;');

	$cbd = new CCheckBox('status_change', $_REQUEST['status_change'], $action, 1);
	$cbd->addStyle('vertical-align: middle;');

	$spand = new CSpan(_('days'));
	$spand->addStyle('vertical-align: middle;');
	$filterForm->addRow(_('Age less than'), array(
		$cbd,
		$sts_change_days_cb,
		$spand,
	));

	$filterForm->addRow(_('Show details'), new CCheckBox('show_details', $_REQUEST['show_details'], null, 1));

	$filterForm->addRow(_('Filter by name'), new CTextBox('txt_select', $_REQUEST['txt_select'], 40));

	$filterForm->addRow(_('Show hosts in maintenance'), new CCheckBox('show_maintenance', $_REQUEST['show_maintenance'], null, 1));

	$filterForm->addItemToBottomRow(new CSubmit('filter_set', _('Filter')));
	$filterForm->addItemToBottomRow(new CSubmit('filter_rst', _('Reset')));

	$trigg_wdgt->addFlicker($filterForm, CProfile::get('web.tr_status.filter.state', 0));
/*************** FILTER END ******************/

  	if($_REQUEST['fullscreen']){
		$triggerInfo = new CTriggersInfo($_REQUEST['groupid'], $_REQUEST['hostid']);
		$triggerInfo->HideHeader();
		$triggerInfo->show();
	}

	$m_form = new CForm('get', 'acknow.php');
	$m_form->setName('tr_status');
	$m_form->addVar('backurl', $page['file']);

	$admin_links = (($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN) || ($USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN));
	$show_event_col = ($config['event_ack_enable'] && ($_REQUEST['show_events'] != EVENTS_OPTION_NOEVENT));

	$table = new CTableInfo(_('No triggers defined.'));
	$switcherName = 'trigger_switchers';

	$header_cb = ($show_event_col) ? new CCheckBox('all_events', false, "checkAll('".$m_form->GetName()."','all_events','events');")
		: new CCheckBox('all_triggers', false, "checkAll('".$m_form->GetName()."','all_triggers', 'triggers');");

	if($show_events != EVENTS_OPTION_NOEVENT){
		$whow_hide_all = new CDiv(SPACE, 'filterclosed');
		$whow_hide_all->setAttribute('id', $switcherName);
	}
	else{
		$whow_hide_all = null;
	}

	$table->setHeader(array(
		$whow_hide_all,
		$config['event_ack_enable'] ? $header_cb : null,
		make_sorting_header(_('Severity'), 'priority'),
		_('Status'),
		_('Info'),
		make_sorting_header(_('Last change'), 'lastchange'),
		_('Age'),
		$show_event_col ? _('Duration') : null,
		$config['event_ack_enable'] ? _('Acknowledged') : null,
		is_show_all_nodes() ? _('Node') : null,
		_('Host'),
		make_sorting_header(_('Name'), 'description'),
		_('Comments')
	));


	$sortfield = getPageSortField('description');
	$sortorder = getPageSortOrder();
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored' => true,
		'output' => array('triggerid', $sortfield),
		'skipDependent' => true,
		'limit' => $config['search_limit'] + 1
	);

	// filtering
	if($pageFilter->hostsSelected){
		if($pageFilter->hostid > 0)
			$options['hostids'] = $pageFilter->hostid;
		else if($pageFilter->groupid > 0)
			$options['groupids'] = $pageFilter->groupid;
	}
	else{
		$options['hostids'] = array();
	}


	if(!zbx_empty($_REQUEST['txt_select'])){
		$options['search'] = array('description' => $_REQUEST['txt_select']);
	}
	if($show_triggers == TRIGGERS_OPTION_ONLYTRUE){
		$options['only_true'] = 1;
	}
	if($ack_status == ZBX_ACK_STS_WITH_UNACK){
		$options['withUnacknowledgedEvents'] = 1;
	}
	if($ack_status == ZBX_ACK_STS_WITH_LAST_UNACK){
		$options['withLastEventUnacknowledged'] = 1;
	}
	if($show_severity > -1){
		$options['min_severity'] = $show_severity;
	}
	if($_REQUEST['status_change']){
		$options['lastChangeSince'] = time() - $_REQUEST['status_change_days'] * SEC_PER_DAY;
	}
	if (!get_request('show_maintenance')) {
		$options['maintenance'] = false;
	}

	$triggers = API::Trigger()->get($options);

// sorting && paging
	order_result($triggers, $sortfield, $sortorder);
	$paging = getPagingLine($triggers);

	$options = array(
		'nodeids' => get_current_nodeid(),
		'triggerids' => zbx_objectValues($triggers, 'triggerid'),
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name', 'maintenance_status', 'maintenance_type', 'maintenanceid', 'description'),
		'selectItems' =>  array('hostid', 'name', 'key_', 'value_type'),
		'selectDependencies' => API_OUTPUT_EXTEND,
		'expandDescription' => true
	);
	$triggers = API::Trigger()->get($options);

	$triggers = zbx_toHash($triggers, 'triggerid');

	order_result($triggers, $sortfield, $sortorder);
//---------

	$triggerids = zbx_objectValues($triggers, 'triggerid');

	if ($config['event_ack_enable']) {
		$options = array(
			'countOutput' => true,
			'groupCount' => true,
			'triggerids' => $triggerids,
			'filter' => array(
				'object' => EVENT_OBJECT_TRIGGER,
				'value_changed' => TRIGGER_VALUE_CHANGED_YES,
				'acknowledged' => 0,
				'value' => TRIGGER_VALUE_TRUE
			),
			'nopermissions' => true
		);
		// get all unacknowledged events, if trigger has unacknowledged even => it has events
		$event_counts = API::Event()->get($options);
		foreach ($event_counts as $event_count) {
			$triggers[$event_count['objectid']]['hasEvents'] = true;
			$triggers[$event_count['objectid']]['event_count'] = $event_count['rowscount'];
		}

		// gather ids of triggers which don't have unack. events
		$triggerIdsWithoutUnackEvents = array();
		foreach ($triggers as $tnum => $trigger) {
			if (!isset($trigger['hasEvents'])) {
				$triggerIdsWithoutUnackEvents[] = $trigger['triggerid'];
			}
			if (!isset($trigger['event_count'])) {
				$triggers[$tnum]['event_count'] = 0;
			}
		}
		if (!empty($triggerIdsWithoutUnackEvents)) {
			$options = array(
				'countOutput' => true,
				'groupCount' => true,
				'triggerids' => $triggerIdsWithoutUnackEvents,
				'filter' => array(
					'object' => EVENT_OBJECT_TRIGGER,
					'value_changed' => TRIGGER_VALUE_CHANGED_YES
				),
				'nopermissions' => true
			);
			// for triggers without unack. events we try to select any event
			$allEventCounts = API::Event()->get($options);
			$allEventCounts = zbx_toHash($allEventCounts, 'objectid');
			foreach ($triggers as $tnum => $trigger) {
				if (!isset($trigger['hasEvents'])) {
					$triggers[$tnum]['hasEvents'] = isset($allEventCounts[$trigger['triggerid']]);
				}
			}
		}
	}

	$tr_hostids = array();
	foreach ($triggers as $tnum => $trigger) {
		$triggers[$tnum]['events'] = array();

		//getting all host ids and names
		foreach ($trigger['hosts'] as $tr_hosts) {
			$tr_hostids[$tr_hosts['hostid']] = $tr_hosts['hostid'];
		}
	}


	$scripts_by_hosts = API::Script()->getScriptsByHosts($tr_hostids);

	// fetch all hosts
	$hosts = API::Host()->get(array(
		'hostids' => $tr_hostids,
		'preservekeys' => true,
		'selectScreens' => API_OUTPUT_COUNT,
		'selectInventory' => array('hostid')
	));

	if($show_events != EVENTS_OPTION_NOEVENT){
		$ev_options = array(
			'nodeids' => get_current_nodeid(),
			'triggerids' => zbx_objectValues($triggers, 'triggerid'),
			'filter' => array(
				'value_changed' => TRIGGER_VALUE_CHANGED_YES,
			),
			'output' => API_OUTPUT_EXTEND,
			'select_acknowledges' => API_OUTPUT_COUNT,
			'time_from' => time() - $config['event_expire'] * SEC_PER_DAY,
			'time_till' => time(),
			'nopermissions' => true,
			//'limit' => $config['event_show_max']
		);

		switch($show_events){
			case EVENTS_OPTION_ALL:
			break;
			case EVENTS_OPTION_NOT_ACK:
				$ev_options['acknowledged'] = false;
				$ev_options['value'] = TRIGGER_VALUE_TRUE;
			break;
		}

		$events = API::Event()->get($ev_options);
		$sortFields = array(
			array('field' => 'clock', 'order' => ZBX_SORT_DOWN),
			array('field' => 'eventid', 'order' => ZBX_SORT_DOWN)
		);
		CArrayHelper::sort($events, $sortFields);

		foreach($events as $enum => $event){
			$triggers[$event['objectid']]['events'][] = $event;
		}
	}

	$dep_res = DBselect(
		'SELECT triggerid_down,triggerid_up'.
		' FROM trigger_depends'.
		' WHERE '.dbConditionInt('triggerid_up', $triggerids)
	);
	$triggerids_down = array();
	while ($row = DBfetch($dep_res)) {
		$triggerids_down[$row['triggerid_up']][] = intval($row['triggerid_down']);
	}

	foreach ($triggers as $tnum => $trigger) {
		$items = array();

		$used_hosts = array();
		foreach ($trigger['hosts'] as $th) {
			$used_hosts[$th['hostid']] = $th['name'];
		}
		$used_host_count = count($used_hosts);

		foreach ($trigger['items'] as $inum => $item) {
			$item_name = itemName($item);

			//if we have items from different hosts, we must prefix a host name
			if ($used_host_count > 1) {
				$item_name = $used_hosts[$item['hostid']].':'.$item_name;
			}

			$items[$inum]['itemid'] = $item['itemid'];
			$items[$inum]['value_type'] = $item['value_type']; //ZBX-3059: So it would be possible to show different caption for history for chars and numbers (KB)
			$items[$inum]['action'] = str_in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64)) ? 'showgraph' : 'showvalues';
			$items[$inum]['name'] = htmlspecialchars($item_name);
		}
		$trigger['items'] = $items;

		// trigger js menu
		$menu_trigger_conf = 'null';
		if($admin_links && $trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL){
			$configurationUrl = 'triggers.php?form=update&triggerid='.$trigger['triggerid'].'&hostid='.$pageFilter->hostid.'&switch_node='.id2nodeid($trigger['triggerid']);
			$menu_trigger_conf = "['"._('Configuration of triggers')."',".CJs::encodeJson($configurationUrl).",
				null, {'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
		}
		$menu_trigger_url = 'null';
		if (!zbx_empty($trigger['url'])) {
			// double CHtml::encode is required to prevent XSS attacks
			$menu_trigger_url = "['"._('URL')."',".CJs::encodeJson(CHtml::encode(CHtml::encode(resolveTriggerUrl($trigger)))).",
				null, {'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
		}

		$description = new CSpan($trigger['description'], 'link_menu');
		$description->addAction('onclick',
			"javascript: create_mon_trigger_menu(event, [{'triggerid': '".$trigger['triggerid'].
				"', 'lastchange': '".$trigger['lastchange']."'}, ".$menu_trigger_conf.", ".$menu_trigger_url."],".
			zbx_jsvalue($items, true).");"
		);

		if($_REQUEST['show_details']){
			$font = new CTag('font', 'yes');
			$font->setAttribute('color', '#000');
			$font->setAttribute('size', '-2');
			$font->addItem(explode_exp($trigger['expression'], true, true));
			$description = array($description, BR(), $font);
		}

// DEPENDENCIES {{{
		if(!empty($trigger['dependencies'])){
			$dep_table = new CTableInfo();
			$dep_table->setAttribute('style', 'width: 200px;');
			$dep_table->addRow(bold(_('Depends on').':'));

			foreach($trigger['dependencies'] as $dep){
				$dep_table->addRow(' - '.CTriggerHelper::expandDescriptionById($dep['triggerid']));
			}

			$img = new Cimg('images/general/arrow_down2.png', 'DEP_UP');
			$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
			$img->setHint($dep_table);

			$description = array($img, SPACE, $description);
		}

		$dependency = false;
		$dep_table = new CTableInfo();
		$dep_table->setAttribute('style', 'width: 200px;');
		$dep_table->addRow(bold(_('Dependent').':'));
		if (!empty($triggerids_down[$trigger['triggerid']])) {
			$depTriggers = CTriggerHelper::batchExpandDescriptionById($triggerids_down[$trigger['triggerid']]);

			foreach ($depTriggers as $depTrigger) {
				$dep_table->addRow(SPACE.'-'.SPACE.$depTrigger['description']);
				$dependency = true;
			}
		}

		if($dependency){
			$img = new Cimg('images/general/arrow_up2.png', 'DEP_UP');
			$img->setAttribute('style', 'vertical-align: middle; border: 0px;');
			$img->setHint($dep_table);

			$description = array($img,SPACE,$description);
		}
		unset($img, $dep_table, $dependency);
// }}} DEPENDENCIES

		$tr_desc = new CSpan($description);


// host JS menu {{{
		$hosts_list = array();
		foreach($trigger['hosts'] as $trigger_host){
			$host = $hosts[$trigger_host['hostid']];

			$hosts_span = new CDiv(null, 'floatleft');

			// fetch scripts for the host JS menu
			$menuScripts = array();
			if (isset($scripts_by_hosts[$trigger_host['hostid']])) {
				foreach ($scripts_by_hosts[$trigger_host['hostid']] as $script) {
					$menuScripts[] = array(
						'scriptid' => $script['scriptid'],
						'confirmation' => $script['confirmation'],
						'name' => $script['name']
					);
				}
			}

			$hosts_name = new CSpan($trigger_host['name'], 'link_menu menu-host');
			$hosts_name->setAttribute('data-menu', array(
				'scripts' => $menuScripts,
				'hostid' => $trigger_host['hostid'],
				'hasScreens' => (bool) $host['screens'],
				'hasInventory' => (bool) $host['inventory']
			));

			$hosts_span->addItem($hosts_name);

			// add maintenance icon with hint if host is in maintenance
			if($trigger_host['maintenance_status']){
				$mntIco = new CDiv(null, 'icon-maintenance-inline');

				$maintenances = API::Maintenance()->get(array(
					'maintenanceids' => $trigger_host['maintenanceid'],
					'output' => API_OUTPUT_EXTEND,
					'limit'	=> 1
				));

				if ($maintenance = reset($maintenances)) {
					$hint = $maintenance['name'].' ['.($trigger_host['maintenance_type']
						? _('Maintenance without data collection')
						: _('Maintenance with data collection')).']';

					if (isset($maintenance['description'])) {
						// double quotes mandatory
						$hint .= "\n".$maintenance['description'];
					}

					$mntIco->setHint($hint);
					$mntIco->addClass('pointer');
				}

				$hosts_span ->addItem($mntIco);
			}

			// add comma after hosts, except last
			if (next($trigger['hosts'])) {
				$hosts_span->addItem(','.SPACE);
			}

			$hosts_list[] = $hosts_span;
		}

		$host = new CCol($hosts_list);
		$host->addStyle('white-space: normal;');
// }}} host JS menu

		$statusSpan = new CSpan(trigger_value2str($trigger['value']));
		// add colors and blinking to span depending on configuration and trigger parameters
		addTriggerValueStyle(
			$statusSpan,
			$trigger['value'],
			$trigger['lastchange'],
			$config['event_ack_enable'] ? $trigger['event_count'] == 0 : false
		);

		$lastchange = new CLink(zbx_date2str(_('d M Y H:i:s'), $trigger['lastchange']), 'events.php?triggerid='.$trigger['triggerid']);
		//.'&stime='.date('YmdHis', $trigger['lastchange']

		if($config['event_ack_enable']){
			if ($trigger['hasEvents']) {
				if($trigger['event_count']){
					$to_ack = new CCol(array(new CLink(_('Acknowledge'), 'acknow.php?triggers[]='.$trigger['triggerid'].'&backurl='.$page['file'], 'on'), ' ('.$trigger['event_count'].')'));
				}
				else{
					$to_ack = new CCol(_('Acknowledged'), 'off');
				}
			}
			else {
				$to_ack = new CCol(_('No events'), 'unknown');
			}
		}
		else{
			$to_ack = null;
		}


		if(($show_events != EVENTS_OPTION_NOEVENT) && !empty($trigger['events'])){
			$open_close = new CDiv(SPACE, 'filterclosed');
			$open_close->setAttribute('data-switcherid', $trigger['triggerid']);
		}
		else if($show_events == EVENTS_OPTION_NOEVENT){
			$open_close = null;
		}
		else{
			$open_close = SPACE;
		}


		$severity_col = getSeverityCell($trigger['priority'], null, !$trigger['value']);
		if($show_event_col) $severity_col->setColSpan(2);


// Unknown triggers
		$unknown = SPACE;
		if($trigger['value_flags'] == TRIGGER_VALUE_FLAG_UNKNOWN){
			$unknown = new CDiv(SPACE, 'status_icon iconunknown');
			$unknown->setHint($trigger['error'], '', 'on');
		}
//----

		$table->addRow(array(
			$open_close,
			$config['event_ack_enable'] ?
				($show_event_col ? null : new CCheckBox('triggers['.$trigger['triggerid'].']', 'no', null, $trigger['triggerid'])) : null,
			$severity_col,
			$statusSpan,
			$unknown,
			$lastchange,
			zbx_date2age($trigger['lastchange']),
			$show_event_col ? SPACE : NULL,
			$to_ack,
			get_node_name_by_elid($trigger['triggerid']),
			$host,
			$tr_desc,
			new CLink(zbx_empty($trigger['comments']) ? _('Add') : _('Show'), 'tr_comments.php?triggerid='.$trigger['triggerid'])
		), 'even_row');


		if($show_events != EVENTS_OPTION_NOEVENT){
			$i = 1;

			foreach($trigger['events'] as $enum => $row_event){
				$i++;

				$eventStatusSpan = new CSpan(trigger_value2str($row_event['value']));
				// add colors and blinking to span depending on configuration and trigger parameters
				addTriggerValueStyle(
					$eventStatusSpan,
					$row_event['value'],
					$row_event['clock'],
					$row_event['acknowledged']
				);

				$statusSpan = new CCol($eventStatusSpan);
				$statusSpan->setColSpan(2);

				$ack = getEventAckState($row_event, true);

				if(($row_event['acknowledged'] == 0) && ($row_event['value'] == TRIGGER_VALUE_TRUE)){
					$ack_cb = new CCheckBox('events['.$row_event['eventid'].']', 'no', NULL, $row_event['eventid']);
				}
				else{
					$ack_cb = SPACE;
				}

				$clock = new CLink(zbx_date2str(_('d M Y H:i:s'), $row_event['clock']),
					'tr_events.php?triggerid='.$trigger['triggerid'].'&eventid='.$row_event['eventid']);
				$next_clock = isset($trigger['events'][$enum-1]) ? $trigger['events'][$enum-1]['clock'] : time();

				$empty_col = new CCol(SPACE);
				$empty_col->setColSpan(3);
				$ack_cb_col = new CCol($ack_cb);
				$ack_cb_col->setColSpan(2);
				$row = new CRow(array(
					SPACE,
					$config['event_ack_enable'] ? $ack_cb_col : null,
					$statusSpan,
					$clock,
					zbx_date2age($row_event['clock']),
					zbx_date2age($next_clock, $row_event['clock']),
					($config['event_ack_enable']) ? $ack : NULL,
					is_show_all_nodes() ? SPACE : null,
					$empty_col
				), 'odd_row');
				$row->setAttribute('data-parentid', $trigger['triggerid']);
				$row->addStyle('display: none;');
				$table->addRow($row);

				if($i > $config['event_show_max']) break;
			}
		}
	}

//----- GO ------
	$footer = null;
	if($config['event_ack_enable']){
		$goBox = new CComboBox('go');
		$goBox->addItem('bulkacknowledge', _('Bulk acknowledge'));

// goButton name is necessary!!!
		$goButton = new CSubmit('goButton', _('Go').' (0)');
		$goButton->setAttribute('id', 'goButton');

		$show_event_col ? zbx_add_post_js('chkbxRange.pageGoName = "events";') : zbx_add_post_js('chkbxRange.pageGoName = "triggers";');

		$footer = get_table_header(array($goBox, $goButton));
	}
//----

	$table = array($paging, $table, $paging, $footer);
	$m_form->addItem($table);
	$trigg_wdgt->addItem($m_form);
	$trigg_wdgt->show();

	zbx_add_post_js('jqBlink.blink();');
	zbx_add_post_js('var switcher = new CSwitcher(\''.$switcherName.'\');');

require_once dirname(__FILE__).'/include/page_footer.php';
