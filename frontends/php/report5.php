<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/triggers.inc.php');

$page['title'] = _('Most busy triggers top 100');
$page['file'] = 'report5.php';
$page['hist_arg'] = array('period');
$page['scripts'] = array();

require_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'period' => array(T_ZBX_STR,	O_OPT,	P_SYS | P_NZERO,	IN('"day","week","month","year"'),	NULL)
);
check_fields($fields);
?>
<?php
$rprt_wdgt = new CWidget();

$_REQUEST['period'] = get_request('period', 'day');
$admin_links = ($USER_DETAILS['type'] == USER_TYPE_ZABBIX_ADMIN || $USER_DETAILS['type'] == USER_TYPE_SUPER_ADMIN);

$form = new CForm('get');

$cmbPeriod = new CComboBox('period', $_REQUEST['period'], 'submit()');
$cmbPeriod->addItem('day', _('Day'));
$cmbPeriod->addItem('week', _('Week'));
$cmbPeriod->addItem('month', _('Month'));
$cmbPeriod->addItem('year', _('Year'));

$form->addItem($cmbPeriod);

$rprt_wdgt->addPageHeader(_('MOST BUSY TRIGGERS TOP 100'));

$rprt_wdgt->addHeader(_('REPORT'), $form);
$rprt_wdgt->addItem(BR());

$table = new CTableInfo();
$table->setHeader(array(
	is_show_all_nodes() ? _('Node') : null,
	_('Host'),
	_('Trigger'),
	_('Severity'),
	_('Number of status changes')
));

switch ($_REQUEST['period']) {
	case 'week':
		$time_dif = SEC_PER_DAY;
		break;
	case 'month':
		$time_dif = SEC_PER_MONTH;
		break;
	case 'year':
		$time_dif = SEC_PER_YEAR;
		break;
	case 'day':
	default:
		$time_dif = SEC_PER_DAY;
		break;
}

$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY);
$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array());
$scripts_by_hosts = API::Script()->getScriptsByHosts($available_hosts);

$triggers = array();
$triggerids = array();
$sql = 'SELECT h.name AS hostname,MAX(h.hostid) AS hostid,t.triggerid,t.description,t.expression,'.
		'MAX(t.lastchange) AS lastchange,t.priority,count(distinct e.eventid) AS cnt_event,t.flags'.
		' FROM hosts h,items i,functions f,triggers t,events e'.
		' WHERE h.hostid=i.hostid'.
			' AND i.itemid=f.itemid'.
			' AND f.triggerid=t.triggerid'.
			' AND t.triggerid=e.objectid'.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock>'.(time() - $time_dif).
			' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
			' AND'.DBcondition('t.triggerid', $available_triggers).
			' AND '.DBin_node('t.triggerid').
			' AND'.DBcondition('t.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
		' GROUP BY h.name,t.triggerid,t.description,t.expression,t.priority'.
		' ORDER BY cnt_event desc,h.name,t.description,t.triggerid';
$result = DBselect($sql, 100);
while ($row = DBfetch($result)) {
	$row['items'] = array();
	$triggers[$row['triggerid']] = $row;
	$triggerids[$row['triggerid']] = $row['triggerid'];
}

$result = DBselect(
	'SELECT f.triggerid,i.*'.
	' FROM functions f,items i'.
	' WHERE f.itemid=i.itemid'.
		' AND'.DBcondition('f.triggerid', $triggerids)
);
$item = array();
while ($row = DBfetch($result)) {
	$item['itemid'] = $row['itemid'];
	$item['action'] = str_in_array($row['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64)) ? 'showgraph' : 'showvalues';
	$item['name'] = itemName($row);
	$item['value_type'] = $row['value_type']; // ZBX-3059: So it would be possible to show different caption for history for chars and numbers (KB)

	$triggers[$row['triggerid']]['items'][$row['itemid']] = $item;
}

foreach ($triggers as $row) {
	$description = expand_trigger_description_by_data($row);

	$menus = '';
	$host_nodeid = id2nodeid($row['hostid']);
	foreach ($scripts_by_hosts[$row['hostid']] as $script) {
		$script_nodeid = id2nodeid($script['scriptid']);
		if (bccomp($host_nodeid, $script_nodeid) == 0) {
			$menus .= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$row['hostid']."&scriptid=".$script['scriptid']."','Global script',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
		}
	}

	$menus .= "['"._('URLs')."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
	$menus .= "['"._('Latest data')."',\"javascript: redirect('latest.php?hostid=".$row['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
	$menus = "show_popup_menu(event,[['"._('Scripts')."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180);";

	$host = new CSpan($row['hostname'], 'link_menu');
	$host->setAttribute('onclick', $menus);

	$tr_conf_link = 'null';
	if ($USER_DETAILS['type'] > USER_TYPE_ZABBIX_USER && $row['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$tr_conf_link = "['"._('Configuration of trigger')."',\"javascript: redirect('triggers.php?form=update&triggerid=".$row['triggerid']."&hostid=".$row['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
	}


	$tr_desc = new CSpan($description, 'link_menu');
	$tr_desc->addAction('onclick', "create_mon_trigger_menu(event, ".
			" [{'triggerid': '".$row['triggerid']."', 'lastchange': '".$row['lastchange']."'},".$tr_conf_link."],".
			zbx_jsvalue($row['items'], true).");");

	$table->addRow(array(
		get_node_name_by_elid($row['triggerid']),
		$host,
		$tr_desc,
		getSeverityCell($row['priority']),
		$row['cnt_event'],
	));
}

$rprt_wdgt->addItem($table);
$rprt_wdgt->show();

$jsmenu = new CPUMenu(null, 170);
$jsmenu->InsertJavaScript();

require_once('include/page_footer.php');
?>
