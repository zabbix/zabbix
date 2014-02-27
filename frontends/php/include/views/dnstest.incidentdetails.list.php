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


$dnsTestWidget = new CWidget(null, 'incident-details');

// header
$dnsTestWidget->addPageHeader(_('Incident details'), SPACE);
$dnsTestWidget->addHeader(_('Incidents details'));

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
		((zbxDateToTime($this->data['filter_from']) > 0) ? date('d', zbxDateToTime($this->data['filter_from'])) : ''),
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_from_month',
		((zbxDateToTime($this->data['filter_from']) > 0) ? date('m', zbxDateToTime($this->data['filter_from'])) : ''),
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_from_year',
		((zbxDateToTime($this->data['filter_from']) > 0) ? date('Y', zbxDateToTime($this->data['filter_from'])) : ''),
		4
	),
	SPACE,
	new CNumericBox(
		'filter_from_hour',
		((zbxDateToTime($this->data['filter_from']) > 0) ? date('H', zbxDateToTime($this->data['filter_from'])) : ''),
		2
	),
	':',
	new CNumericBox(
		'filter_from_minute',
		((zbxDateToTime($this->data['filter_from']) > 0) ? date('i', zbxDateToTime($this->data['filter_from'])) : ''),
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
		(zbxDateToTime($this->data['filter_to']) > 0) ? date('d', zbxDateToTime($this->data['filter_to'])) : '',
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_to_month',
		(zbxDateToTime($this->data['filter_to']) > 0) ? date('m', zbxDateToTime($this->data['filter_to'])) : '',
		2
	),
	SPACE,
	'/',
	SPACE,
	new CNumericBox(
		'filter_to_year',
		(zbxDateToTime($this->data['filter_to']) > 0) ? date('Y', zbxDateToTime($this->data['filter_to'])) : '',
		4
	),
	SPACE,
	new CNumericBox(
		'filter_to_hour',
		(zbxDateToTime($this->data['filter_to']) > 0) ? date('H', zbxDateToTime($this->data['filter_to'])) : '',
		2
	),
	':',
	new CNumericBox(
		'filter_to_minute',
		(zbxDateToTime($this->data['filter_to']) > 0) ? date('i', zbxDateToTime($this->data['filter_to'])) : '',
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
			'spaces',
			'javascript: location.href = "dnstest.incidentdetails.php?filter_set=1&filter_rolling_week=1&host='.
				$this->data['tld']['host'].'&eventid='.$this->data['eventid'].'&slvItemId='.$this->data['slvItemId'].
				'&availItemId='.$this->data['availItemId'].'&sid='.$this->data['sid'].'";'
		),
		new CSpan(
			array(
				new CCheckBox('filter_failing_tests',
					isset($this->data['filter_failing_tests']) ? $this->data['filter_failing_tests'] : null, null, 1),
				SPACE,
				bold(_('Only failing tests'))
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
$filterForm->addVar('filter_from', zbxDateToTime($this->data['filter_from']));
$filterForm->addVar('filter_to', zbxDateToTime($this->data['filter_to']));
$filterForm->addVar('original_from', zbxDateToTime($this->data['filter_from']));
$filterForm->addVar('original_to', zbxDateToTime($this->data['filter_to']));
$filterForm->addVar('eventid', $this->data['eventid']);
$filterForm->addVar('slvItemId', $this->data['slvItemId']);
$filterForm->addVar('availItemId', $this->data['availItemId']);
$filterForm->addVar('host', $this->data['host']);
$filterForm->addItem($filterTable);
$dnsTestWidget->addFlicker($filterForm, CProfile::get('web.dnstest.incidentdetails.filter.state', 0));

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

foreach ($this->data['tests'] as $test) {
	if (isset($test['startEvent']) && $test['startEvent']) {
		$startEndIncident = _('Start time');
	}
	elseif (isset($test['endEvent']) && $test['endEvent']) {
		$startEndIncident = _('Resolved');
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
			'dnstest.particulartests.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
				'&time='.$test['clock'].'&type='.$this->data['type']
		)
	);

	$detailsTable->addRow($row);
}

if ($this->data['incidentType'] == INCIDENT_ACTIVE) {
	$incidentType = _('Active');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
elseif ($this->data['incidentType'] == INCIDENT_RESOLVED) {
	$incidentType = _('Resolved');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
else {
	$incidentType = _('False positive');
	$changeIncidentType = $this->data['active'] ? INCIDENT_ACTIVE : INCIDENT_RESOLVED;
	$changeIncidentTypeName = _('Unmark incident as false positive');
}

$details = array(
	new CSpan(array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])),
	BR(),
	new CSpan(array(bold(_('Service')), ':', SPACE, $this->data['slvItem']['name'])),
	BR(),
	new CSpan(array(bold(_('Incident type')), ':', SPACE, $incidentType))
);

$rollingWeek = new CSpan(_s('%1$s Rolling week status', $this->data['slv'].'%'), 'rolling-week-status');
$detailsInfoTable->addRow(array(array($details, $rollingWeek)));
$dnsTestWidget->additem($detailsInfoTable);

$dnsTestWidget->additem(array($data['paging'], $detailsTable, $data['paging']));

if (CWebUser::getType() >= USER_TYPE_ZABBIX_ADMIN) {
	$filterTable = new CTable('', 'filter');

	$filter = new CButton('mark_incident', $changeIncidentTypeName,
		'javascript: location.href = "dnstest.incidents.php?mark_incident='.$changeIncidentType.
		'&eventid='.$this->data['eventid'].'&host='.$this->data['tld']['host'].'&type='.$this->data['type'].'";'
	);
	$filter->useJQueryStyle('main');

	$divButtons = new CDiv($filter);
	$divButtons->setAttribute('style', 'padding: 4px 0px;');

	$filterTable->addRow(new CCol($divButtons, 'left'));

	$dnsTestWidget->additem($filterTable);
}

return $dnsTestWidget;
