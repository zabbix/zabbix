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

require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['title'] = _('Most busy triggers top 100');
$page['file'] = 'report5.php';
$page['hist_arg'] = array('period');
$page['scripts'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'period' => array(T_ZBX_STR,	O_OPT,	P_SYS | P_NZERO,	IN('"day","week","month","year"'),	NULL)
);
check_fields($fields);

$rprt_wdgt = new CWidget();

$_REQUEST['period'] = get_request('period', 'day');
$admin_links = (CWebUser::$data['type'] == USER_TYPE_ZABBIX_ADMIN || CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN);

$form = new CForm('get');

$cmbPeriod = new CComboBox('period', $_REQUEST['period'], 'submit()');
$cmbPeriod->addItem('day', _('Day'));
$cmbPeriod->addItem('week', _('Week'));
$cmbPeriod->addItem('month', _('Month'));
$cmbPeriod->addItem('year', _('Year'));

$form->addItem($cmbPeriod);

$rprt_wdgt->addPageHeader(_('MOST BUSY TRIGGERS TOP 100'));

$rprt_wdgt->addHeader(_('Report'), $form);
$rprt_wdgt->addItem(BR());

$table = new CTableInfo(_('No triggers found.'));
$table->setHeader(array(
	is_show_all_nodes() ? _('Node') : null,
	_('Host'),
	_('Trigger'),
	_('Severity'),
	_('Number of status changes')
));

switch ($_REQUEST['period']) {
	case 'week':
		$time_dif = SEC_PER_WEEK;
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

$available_hosts = API::Host()->get(array(
	'output' => API_OUTPUT_SHORTEN,
	'preservekeys' => true
));
$available_hosts = array_keys($available_hosts);
$available_triggers = get_accessible_triggers(PERM_READ, array());
$scripts_by_hosts = API::Script()->getScriptsByHosts($available_hosts);

$triggersEventCount = array();
// get 100 triggerids with max even count
$sql = 'SELECT e.objectid,count(distinct e.eventid) AS cnt_event'.
		' FROM triggers t,events e'.
		' WHERE t.triggerid=e.objectid'.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock>'.(time() - $time_dif).
			' AND e.value_changed='.TRIGGER_VALUE_CHANGED_YES.
			' AND'.DBcondition('t.triggerid', $available_triggers).
			' AND'.DBcondition('t.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
		' GROUP BY e.objectid'.
		' ORDER BY cnt_event desc';
$result = DBselect($sql, 100);
while ($row = DBfetch($result)) {
	$triggersEventCount[$row['objectid']] = $row['cnt_event'];
}

$triggers = API::Trigger()->get(array(
	'triggerids' => array_keys($triggersEventCount),
	'output' => array('triggerid', 'description', 'expression', 'priority', 'flags', 'lastchange'),
	'selectItems' => API_OUTPUT_EXTEND,
	'expandDescription' => true,
	'expandData' => true,
	'preservekeys' => true,
	'nopermissions' => true,
));
foreach ($triggers as $tid => $trigger) {
	$trigger['cnt_event'] = $triggersEventCount[$tid];

	$items = $trigger['items'];
	$trigger['items'] = array();
	foreach ($items as $item) {
		$trigger['items'][$item['itemid']] = array(
			'itemid' => $item['itemid'],
			'action' => str_in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64)) ? 'showgraph' : 'showvalues',
			'name' => itemName($item),
			'value_type' => $item['value_type']
		);
	}
	$triggers[$tid] = $trigger;
}

CArrayHelper::sort($triggers, array(array('field' => 'cnt_event', 'order' => ZBX_SORT_DOWN), 'host', 'description', 'priority'));
foreach ($triggers as $trigger) {
	$menus = '';
	$host_nodeid = id2nodeid($trigger['hostid']);
	foreach ($scripts_by_hosts[$trigger['hostid']] as $script) {
		$script_nodeid = id2nodeid($script['scriptid']);
		if (bccomp($host_nodeid, $script_nodeid) == 0) {
			$menus .= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$trigger['hostid']."&scriptid=".$script['scriptid']."','Global script',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
		}
	}

	$menus .= "['"._('URLs')."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
	$menus .= "['"._('Latest data')."',\"javascript: redirect('latest.php?hostid=".$trigger['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
	$menus = "show_popup_menu(event,[['"._('Scripts')."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180);";

	$hostSpan = new CSpan($trigger['hostname'], 'link_menu');
	$hostSpan->setAttribute('onclick', $menus);

	$tr_conf_link = 'null';
	if (CWebUser::$data['type'] > USER_TYPE_ZABBIX_USER && $trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$tr_conf_link = "['"._('Configuration of trigger')."',\"javascript: redirect('triggers.php?form=update&triggerid=".$trigger['triggerid']."&hostid=".$trigger['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}]";
	}


	$tr_desc = new CSpan($trigger['description'], 'link_menu');
	$tr_desc->addAction('onclick', "create_mon_trigger_menu(event, ".
			" [{'triggerid': '".$trigger['triggerid']."', 'lastchange': '".$trigger['lastchange']."'},".$tr_conf_link."],".
			zbx_jsvalue($trigger['items'], true).");");

	$table->addRow(array(
		get_node_name_by_elid($trigger['triggerid']),
		$hostSpan,
		$tr_desc,
		getSeverityCell($trigger['priority']),
		$trigger['cnt_event'],
	));
}

$rprt_wdgt->addItem($table);
$rprt_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
