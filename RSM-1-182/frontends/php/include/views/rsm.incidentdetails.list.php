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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$rsmWidget = new CWidget(null, 'incident-details');

// header
$rsmWidget->addPageHeader(_('Incident details'), SPACE);
$rsmWidget->addHeader(_('Incidents details'));

$filterTable = new CTable('', 'filter');

$clndrFirstIcon = new CImg('images/general/bar/cal.gif', 'calendar', 16, 12, 'pointer');
$clndrSecondIcon = clone $clndrFirstIcon;

$clndrFirstIcon->addAction('onclick', 'javascript: '.
	'var pos = getPosition(this); '.
	'pos.top+=10; '.
	'pos.left+=16; '.
	"CLNDR['incidents_from'].clndr.clndrshow(pos.top,pos.left);"
);

$clndrSecondIcon->addAction('onclick', 'javascript: '.
	'var pos = getPosition(this); '.
	'pos.top+=10; '.
	'pos.left+=16; '.
	"CLNDR['incidents_to'].clndr.clndrshow(pos.top,pos.left);"
);

$filterFrom = array(
	bold(_('From')),
	':'.SPACE,
	new CNumericBox(
		'filter_from_day',
		((zbxDateToTime($data['filter_from']) > 0) ? date('d', zbxDateToTime($data['filter_from'])) : ''),
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_from_month',
		((zbxDateToTime($data['filter_from']) > 0) ? date('m', zbxDateToTime($data['filter_from'])) : ''),
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_from_year',
		((zbxDateToTime($data['filter_from']) > 0) ? date('Y', zbxDateToTime($data['filter_from'])) : ''),
		4
	),
	SPACE,
	new CNumericBox(
		'filter_from_hour',
		((zbxDateToTime($data['filter_from']) > 0) ? date('H', zbxDateToTime($data['filter_from'])) : ''),
		2
	),
	':',
	new CNumericBox(
		'filter_from_minute',
		((zbxDateToTime($data['filter_from']) > 0) ? date('i', zbxDateToTime($data['filter_from'])) : ''),
		2
	),
	SPACE,
	$clndrFirstIcon
);

$filterTo = array(
	bold(_('To')),
	':'.SPACE,
	new CNumericBox(
		'filter_to_day',
		(zbxDateToTime($data['filter_to']) > 0) ? date('d', zbxDateToTime($data['filter_to'])) : '',
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_to_month',
		(zbxDateToTime($data['filter_to']) > 0) ? date('m', zbxDateToTime($data['filter_to'])) : '',
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_to_year',
		(zbxDateToTime($data['filter_to']) > 0) ? date('Y', zbxDateToTime($data['filter_to'])) : '',
		4
	),
	SPACE,
	new CNumericBox(
		'filter_to_hour',
		(zbxDateToTime($data['filter_to']) > 0) ? date('H', zbxDateToTime($data['filter_to'])) : '',
		2
	),
	':',
	new CNumericBox(
		'filter_to_minute',
		(zbxDateToTime($data['filter_to']) > 0) ? date('i', zbxDateToTime($data['filter_to'])) : '',
		2
	),
	SPACE,
	$clndrSecondIcon
);

zbx_add_post_js('create_calendar(null,'.
	'["filter_from_day","filter_from_month","filter_from_year","filter_from_hour","filter_from_minute"],'.
	'"incidents_from",'.
	'"filter_from");'
);

zbx_add_post_js('create_calendar(null,'.
	'["filter_to_day","filter_to_month","filter_to_year","filter_to_hour","filter_to_minute"],'.
	'"incidents_to",'.
	'"filter_to");'
);

zbx_add_post_js('addListener($("filter_icon"),"click",CLNDR[\'incidents_from\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'incidents_from\'].clndr));'.
	'addListener($("filter_icon"),"click",CLNDR[\'incidents_to\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'incidents_to\'].clndr));'
);

$filterTable->addRow(array(
	array(
		$filterFrom,
		new CSpan($filterTo, 'spaces'),
		new CLink(
			'Rolling week',
			null,
			'spaces pointer',
			'javascript: location.href = "rsm.incidentdetails.php?filter_rolling_week=1";'
		),
		new CSpan(
			array(
				new CCheckBox('filter_failing_tests',
					isset($data['filter_failing_tests']) ? $data['filter_failing_tests'] : null, null, 1),
				SPACE,
				bold(_('Only failing tests'))
			),
			'spaces'
		),
		new CSpan(
			array(
				new CCheckBox('filter_show_all',
					isset($data['filter_show_all']) ? $data['filter_show_all'] : null, null, 1),
				SPACE,
				bold(_('Show all'))
			),
			'spaces'
		)
	)
));

$filter = new CButton('filter', _('Filter'),
	"javascript: create_var('zbx_filter', 'filter_set', '1', true);"
);
$filter->useJQueryStyle('main');

$divButtons = new CDiv($filter);
$divButtons->setAttribute('style', 'padding: 4px 0px;');

$filterTable->addRow(new CCol($divButtons, 'center'));

$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('filter_from', zbxDateToTime($data['filter_from']));
$filterForm->addVar('filter_to', zbxDateToTime($data['filter_to']));
$filterForm->addVar('original_from', zbxDateToTime($data['filter_from']));
$filterForm->addVar('original_to', zbxDateToTime($data['filter_to']));
$filterForm->addVar('eventid', $data['eventid']);
$filterForm->addVar('slvItemId', $data['slvItemId']);
$filterForm->addVar('availItemId', $data['availItemId']);
$filterForm->addVar('host', $data['host']);
$filterForm->addItem($filterTable);
$rsmWidget->addFlicker($filterForm, CProfile::get('web.rsm.incidentdetails.filter.state', 0));

$headers = array(
	_('Incident'),
	_('Time'),
	_('Result'),
	_('Historical rolling week value'),
	SPACE
);
$noData = _('No problems found.');

$detailsInfoTable = new CTable(null, 'filter info-block');

$detailsTable = new CTableInfo($noData);
$detailsTable->setHeader($headers);

foreach ($data['tests'] as $test) {
	if (isset($test['startEvent']) && $test['startEvent']) {
		$startEndIncident = _('Start time');
	}
	elseif (isset($test['endEvent']) && $test['endEvent'] != TRIGGER_VALUE_TRUE) {
		if ($test['endEvent'] == TRIGGER_VALUE_FALSE) {
			$startEndIncident = _('Resolved');
		}
		else {
			$startEndIncident = _('Resolved (no data)');
		}
	}
	else {
		$startEndIncident = SPACE;
	}

	$value = $test['value'] ? _('Up') : _('Down');

	$row = array(
		$startEndIncident,
		date('d.m.Y H:i:s', $test['clock']),
		$value,
		isset($test['slv']) ? $test['slv'].'%' : '-',
		new CLink(
			_('details'),
			'rsm.particulartests.php?slvItemId='.$data['slvItemId'].'&host='.$data['tld']['host'].
				'&time='.$test['clock'].'&type='.$data['type']
		)
	);

	$detailsTable->addRow($row);
}

if ($data['incidentType'] == INCIDENT_ACTIVE) {
	$incidentType = _('Active');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
elseif ($data['incidentType'] == INCIDENT_RESOLVED) {
	$incidentType = _('Resolved');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
elseif ($data['incidentType'] == INCIDENT_RESOLVED_NO_DATA) {
	$incidentType = _('Resolved (no data)');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
else {
	$incidentType = _('False positive');
	$changeIncidentType = $data['active'] ? INCIDENT_ACTIVE : INCIDENT_RESOLVED;
	$changeIncidentTypeName = _('Unmark incident as false positive');
}

$details = array(
	new CSpan(array(bold(_('TLD')), ':', SPACE, $data['tld']['name'])),
	BR(),
	new CSpan(array(bold(_('Service')), ':', SPACE, $data['slvItem']['name'])),
	BR(),
	new CSpan(array(bold(_('Incident type')), ':', SPACE, $incidentType))
);

if ($data['type'] == RSM_RDDS) {
	$ok_rdds_services = array();
	if (array_key_exists(RSM_TLD_RDDS43_ENABLED, ($data['tld']['subservices']))
			&& $data['tld']['subservices'][RSM_TLD_RDDS43_ENABLED] == 1) {
		$ok_rdds_services[] = 'RDDS43';
	}
	if (array_key_exists(RSM_TLD_RDDS80_ENABLED, ($data['tld']['subservices']))
			&& $data['tld']['subservices'][RSM_TLD_RDDS80_ENABLED] == 1) {
		$ok_rdds_services[] = 'RDDS80';
	}
	if (array_key_exists(RSM_TLD_RDAP_ENABLED, ($data['tld']['subservices']))
			&& $data['tld']['subservices'][RSM_TLD_RDAP_ENABLED] == 1) {
		$ok_rdds_services[] = 'RDAP';
	}

	if ($ok_rdds_services) {
		$subservices = implode('/', $ok_rdds_services);
	}
	else {
		$subservices = _('None');
	}

	$details[] = BR();
	$details[] = new CSpan(array(bold(_('Testing interface')), ':', SPACE, $subservices));
}

$rollingWeek = array(
	new CSpan(_s('%1$s Rolling week status', $data['slv'].'%'), 'rolling-week-status'),
	BR(),
	new CSpan(date('d.m.Y H:i', $data['slvTestTime']), 'floatright'),
);

$detailsInfoTable->addRow(array($details, $rollingWeek));
$rsmWidget->additem($detailsInfoTable);

$rsmWidget->additem(array($data['paging'], $detailsTable, $data['paging']));

if (CWebUser::getType() == USER_TYPE_ZABBIX_ADMIN || CWebUser::getType() == USER_TYPE_SUPER_ADMIN
		|| CWebUser::getType() == USER_TYPE_TEHNICAL_SERVICE) {
	$filterTable = new CTable('', 'filter');

	$filter = new CButton('mark_incident', $changeIncidentTypeName,
		'javascript: location.href = "rsm.incidents.php?mark_incident='.$changeIncidentType.
		'&eventid='.$data['eventid'].'&host='.$data['tld']['host'].'&type='.$data['type'].'";'
	);
	$filter->useJQueryStyle('main');

	$divButtons = new CDiv($filter);
	$divButtons->setAttribute('style', 'padding: 4px 0px;');

	$filterTable->addRow(new CCol($divButtons, 'left'));

	$rsmWidget->additem($filterTable);
}

return $rsmWidget;
