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
require_once dirname(__FILE__).'/include/triggers.inc.php';

$page['title'] = _('Most busy triggers top 100');
$page['file'] = 'report5.php';
$page['hist_arg'] = array('period');

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

$triggersEventCount = array();
// get 100 triggerids with max event count
$sql = 'SELECT e.objectid,count(distinct e.eventid) AS cnt_event'.
		' FROM triggers t,events e'.
		' WHERE t.triggerid=e.objectid'.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock>'.(time() - $time_dif);

// add permission filter
if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
	$userid = CWebUser::$data['userid'];
	$userGroups = getUserGroupsByUserId($userid);
	$sql .= ' AND EXISTS ('.
			'SELECT NULL'.
			' FROM functions f,items i,hosts_groups hgg'.
			' JOIN rights r'.
				' ON r.id=hgg.groupid'.
					' AND '.dbConditionInt('r.groupid', $userGroups).
			' WHERE t.triggerid=f.triggerid'.
				' AND f.itemid=i.itemid'.
				' AND i.hostid=hgg.hostid'.
			' GROUP BY f.triggerid'.
			' HAVING MIN(r.permission)>'.PERM_DENY.')';
}
$sql .= ' AND '.dbConditionInt('t.flags', array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)).
		' GROUP BY e.objectid'.
		' ORDER BY cnt_event desc';
$result = DBselect($sql, 100);
while ($row = DBfetch($result)) {
	$triggersEventCount[$row['objectid']] = $row['cnt_event'];
}

$triggers = API::Trigger()->get(array(
	'triggerids' => array_keys($triggersEventCount),
	'output' => array('triggerid', 'description', 'expression', 'priority', 'flags', 'url', 'lastchange'),
	'selectItems' => array('hostid', 'name', 'value_type', 'key_'),
	'selectHosts' => array('hostid'),
	'expandDescription' => true,
	'expandData' => true,
	'preservekeys' => true,
	'nopermissions' => true
));

$hostIds = array();

foreach ($triggers as $triggerId => $trigger) {
	$hostIds[$trigger['hostid']] = $trigger['hostid'];

	$triggerItems = array();

	$trigger['items'] = CMacrosResolverHelper::resolveItemNames($trigger['items']);

	foreach ($trigger['items'] as $item) {
		$triggerItems[] = array(
			'name' => $item['name_expanded'],
			'params' => array(
				'itemid' => $item['itemid'],
				'action' => in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
					? 'showgraph' : 'showvalues'
			)
		);
	}

	$triggers[$triggerId]['items'] = $triggerItems;
	$triggers[$triggerId]['cnt_event'] = $triggersEventCount[$triggerId];
}

CArrayHelper::sort($triggers, array(
	array('field' => 'cnt_event', 'order' => ZBX_SORT_DOWN),
	'host', 'description', 'priority'
));

$hosts = API::Host()->get(array(
	'output' => array('hostid'),
	'hostids' => $hostIds,
	'selectScreens' => API_OUTPUT_COUNT,
	'preservekeys' => true
));

$scripts = API::Script()->getScriptsByHosts($hostIds);

foreach ($triggers as $trigger) {
	$hostId = $trigger['hostid'];

	$hostName = new CSpan($trigger['hostname'], 'link_menu');
	$hostName->setMenuPopup(getMenuPopupHost($hosts[$hostId], $scripts[$hostId]));

	$triggerDescription = new CSpan($trigger['description'], 'link_menu');
	$triggerDescription->setMenuPopup(getMenuPopupTrigger($trigger, $trigger['items']));

	$table->addRow(array(
		get_node_name_by_elid($trigger['triggerid']),
		$hostName,
		$triggerDescription,
		getSeverityCell($trigger['priority']),
		$trigger['cnt_event']
	));
}

$rprt_wdgt->addItem($table);
$rprt_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
