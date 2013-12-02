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


$dnsTestWidget = new CWidget(null, 'tests');

// header
$dnsTestWidget->addPageHeader(_('Tests'), SPACE);
$dnsTestWidget->addHeader(_('Tests'));

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
			'javascript: location.href = "dnstest.tests.php?filter_set=1&filter_rolling_week=1&hostid='.
				$this->data['tld']['host'].'&type='.$this->data['type'].'&sid='.$this->data['sid'].
				'&slvItemId='.$this->data['slvItemId'].'";'
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
$filterForm->addVar('type', $this->data['type']);
$filterForm->addVar('slvItemId', $this->data['slvItemId']);
$filterForm->addVar('host', $this->data['host']);
$filterForm->addItem($filterTable);
$dnsTestWidget->addFlicker($filterForm, CProfile::get('web.dnstest.tests.filter.state', 0));

$headers = array(
	_('Time'),
	_('Result'),
	_('Details'),
	_('Effects rolling week')
);
$noData = _('No tests found.');

$testsInfoTable = new CTable(null, 'filter info-block');

$testsTable = new CTableInfo($noData);
$testsTable->setHeader($headers);

foreach ($this->data['tests'] as $test) {
	if (isset($test['details'])) {
		$details = explode(',', $test['details']);
		$detailsDescription = new CLink(
			_s('
				%1$s out of %2$s probes reported availability of service',
				$details[0].'%',
				$details[1]
			),
			'dnstest.particulartests.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
				'&time='.$test['clock'].'&type='.$this->data['type']
			);
	}
	else {
		$detailsDescription = '-';
	}

	if (!$test['incident']) {
		$rollingWeekEffects = _('No');
	}
	elseif ($test['incident'] == 1) {
		$rollingWeekEffects = _('Yes');
	}
	else {
		$rollingWeekEffects = _('Yes / False positive');
	}
	$row = array(
		date('d.m.Y H:i:s', $test['clock']),
		$test['value'] ? _('Up') : _('Down'),
		$detailsDescription,
		$rollingWeekEffects
	);

	$testsTable->addRow($row);
}

if ($this->data['type'] == 0) {
	$serviceName = _('DNS service availability');
}
elseif ($this->data['type'] == 1) {
	$serviceName = _('DNSSEC service availability');
}
else {
	$serviceName = _('RDDS service availability');
}

$testsInfoTable->addRow(array(array(
	array(
		new CSpan(array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])),
		BR(),
		new CSpan(array(bold(_('Service')), ':', SPACE, $serviceName))
	),
	new CSpan(_s('%1$s Rolling week status', $this->data['slv'].'%'), 'rolling-week-status')
)));

$testsInfoTable->addRow(array(array(
	new CDiv(array(
		array(
			new CSpan(array(bold(_('Number of tests downtime')), ':', SPACE, $this->data['downTests']), 'first-row-element'),
			new CSpan(array(bold(_('Number of mimutes downtime')), ':', SPACE, $this->data['downTimeMinutes']))
		),
		BR(),
		array(
			new CSpan(array(bold(_('Number of state changes')), ':', SPACE, $this->data['statusChanges']), 'first-row-element'),
			new CSpan(array(bold(_('Total time within selected period')), ':', SPACE, convertUnitsS($this->data['downPeriod'])))
		),
		BR(),
		BR(),
		new CSpan(array(bold(_s('%1$s of downtime: Number of minutes of downtime/Total time within selected period', round($this->data['downTimeMinutes'] / ($this->data['downPeriod'] / 60) * 100).'%'))))
	), 'info-block')
)));


$dnsTestWidget->additem(array($testsInfoTable));

$dnsTestWidget->additem($testsTable);

return $dnsTestWidget;
