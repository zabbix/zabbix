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
$page['file'] = 'toptriggers.php';
$page['hist_arg'] = array('period');
$page['scripts'] = array('multiselect.js', 'class.calendar.js');

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR					TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'groupids' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'hostids' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,	null),
	'severity'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	null,	null),
	'filter_rst' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'filter_set' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'filterState' =>	array(T_ZBX_INT, O_OPT, P_ACT,	null,	null)
);
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('filterState')) {
	CProfile::update('web.toptriggers.filter.state', getRequest('filterState'), PROFILE_TYPE_INT);
}

$data = array();

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.toptriggers.filter.severity', getRequest('severity', -1), PROFILE_TYPE_INT);
	CProfile::updateArray('web.toptriggers.filter.groupids', getRequest('groupids', array()), PROFILE_TYPE_STR);
	CProfile::updateArray('web.toptriggers.filter.hostids', getRequest('hostids', array()), PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.toptriggers.filter.severity');
	CProfile::deleteIdx('web.toptriggers.filter.groupids');
	CProfile::deleteIdx('web.toptriggers.filter.hostids');
	DBend();
}

$data['filter'] = array(
	'severity' => CProfile::get('web.toptriggers.filter.severity', -1),
	'groupids' => CProfile::getArray('web.toptriggers.filter.groupids'),
	'hostids' => CProfile::getArray('web.toptriggers.filter.hostids')
);

// multiselect host groups
$data['multiSelectHostGroupData'] = array();
if ($data['filter']['groupids'] !== null) {
	$filterGroups = API::HostGroup()->get(array(
		'output' => array('groupid', 'name'),
		'groupids' => $data['filter']['groupids']
	));

	foreach ($filterGroups as $group) {
		$data['multiSelectHostGroupData'][] = array(
			'id' => $group['groupid'],
			'name' => $group['name']
		);
	}
}

// multiselect hosts
$data['multiSelectHostData'] = array();
if ($data['filter']['hostids']) {
	$filterHosts = API::Host()->get(array(
		'output' => array('hostid', 'name'),
		'hostids' => $data['filter']['hostids']
	));

	foreach ($filterHosts as $host) {
		$data['multiSelectHostData'][] = array(
			'id' => $host['hostid'],
			'name' => $host['name']
		);
	}
}

$data['config'] = select_config();

$data['filterSet'] = ($data['filter']['severity'] !== '' || $data['filter']['groupids'] || $data['filter']['hostids']);

// render view
$historyView = new CView('reports.toptriggers', $data);
$historyView->render();
$historyView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
