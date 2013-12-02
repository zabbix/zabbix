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


$dnsTestWidget = new CWidget(null, 'incidents');

// header
$dnsTestWidget->addPageHeader(_('Incidents'), SPACE);
$dnsTestWidget->addHeader(_('Incidents'));

$dnsTestWidget->addHeaderRowNumber();

$filterTable = new CTable('', 'filter');

$filterTld = new CTextBox('filter_search',
	isset($this->data['filter_search']) ? $this->data['filter_search'] : null
);
$filterTld->setAttribute('autocomplete', 'off');

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
	array(array(bold(_('TLD')), ':'.SPACE), $filterTld),
	array(
		$filterFrom,
		new CSpan($filterTo, 'time-period'),
		new CLink(
			'Rolling week',
			null,
			'time-period',
			'rollingweek('.$this->data['incident_type'].', \''.$this->data['sid'].'\');'
		)
	)
));

$filter = new CButton('filter', _('Filter'),
	"javascript: create_var('zbx_filter', 'filter_set', '1', true);"
);
$filter->useJQueryStyle('main');

$divButtons = new CDiv($filter);
$divButtons->setAttribute('style', 'padding: 4px 0px;');

$filterTable->addRow(new CCol($divButtons, 'center', 2));

$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addVar('filter_from', zbxDateToTime($this->data['filter_from']));
$filterForm->addVar('filter_to', zbxDateToTime($this->data['filter_to']));
$filterForm->addVar('original_from', zbxDateToTime($this->data['filter_from']));
$filterForm->addVar('original_to', zbxDateToTime($this->data['filter_to']));
$filterForm->addItem($filterTable);
$dnsTestWidget->addFlicker($filterForm, CProfile::get('web.dnstest.incidents.filter.state', 0));

if (isset($this->data['tld'])) {
	$infoBlock = new CTable(null, 'filter info-block');
	$infoBlock->addRow(array(array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])));
	$dnsTestWidget->additem($infoBlock);
}

$incidentPage = new CTabView(array('remember' => true));
$incidentPage->setSelected($this->data['incident_type']);

$headers = array(
	_('Incident ID'),
	_('Status'),
	_('Start time'),
	_('End Time'),
	_('Incident duration time'),
	_('Input into rolling week'),
	_('Service ticket')
);
$noData = _('No incidents found.');

$dnsTab = new CDiv();
$dnssecTab = new CDiv();
$rddsTab = new CDiv();

if (isset($this->data['tld'])) {
	if (isset($this->data['dns']['events'])) {
		$dnsInfoTable = new CTable(null, 'incidents-info');

		$dnsTable = new CTableInfo($noData);
		$dnsTable->setHeader($headers);

		foreach ($this->data['dns']['events'] as $event) {
			if ($event['false_positive']) {
				$incidentStatus = _('False positive');
			}
			else {
				$incidentStatus = $event['status'] ? _('Active') : _('Resolved');
			}
			$row = array(
				new CLink(
					$event['eventid'],
					'dnstest.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['dns']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['dns']['availItemId']
				),
				$incidentStatus,
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '-',
				isset($event['incidentHistory']) ? $event['incidentHistory'] : '-',
				$event['rollingWeekHistory'],
				new CLink('XYZ123', 'http://ticket.com', null, null, true),
			);

			$dnsTable->addRow($row);
		}

		$minutesDown = new CLink(
			$this->data['dns']['incidentTotal'],
			'dnstest.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type=0&slvItemId='.$this->data['dns']['itemid']
		);

		$testsInfo = array(
			_('Minutes down'),
			':',
			SPACE,
			$minutesDown,
			SPACE,
			_n('minute', 'minutes', $this->data['dns']['incidentTotal']),
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['dns']['inIncident'],
				$this->data['dns']['incidentTotal'] - $this->data['dns']['inIncident']
			).')'
		);

		$details = new CSpan(array(
			_s(
				'From %1$s till %2$s',
				date('d.m.Y H:i:s', zbxDateToTime($this->data['filter_from'])),
				date('d.m.Y H:i:s', zbxDateToTime($this->data['filter_to']))
			),
			BR(),
			_s(
				'Incidents: %1$s',
				isset($this->data['dns']) ? count($this->data['dns']['events']) : 0
			),
			BR(),
			$testsInfo
		));

		$rollingWeek = new CSpan(_s('%1$s Rolling week status', $this->data['dns']['slv'].'%'), 'rolling-week-status');
		$dnsInfoTable->addRow(array($details, $rollingWeek));
		$dnsTab->additem($dnsInfoTable);

		$dnsTab->additem($dnsTable);
	}
	else {
		$dnsTab->additem(new CDiv(bold(_('Incorrect TLD configuration.')), 'red center'));
	}

	if (isset($this->data['dnssec']['events'])) {
		$dnssecInfoTable = new CTable(null, 'incidents-info');

		$dnssecTable = new CTableInfo($noData);
		$dnssecTable->setHeader($headers);

		foreach ($this->data['dnssec']['events'] as $event) {
			if ($event['false_positive']) {
				$incidentStatus = _('False positive');
			}
			else {
				$incidentStatus = $event['status'] ? _('Active') : _('Resolved');
			}
			$row = array(
				new CLink(
					$event['eventid'],
					'dnstest.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['dnssec']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['dnssec']['availItemId']
				),
				$incidentStatus,
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '-',
				isset($event['incidentHistory']) ? $event['incidentHistory'] : '-',
				$event['rollingWeekHistory'],
				new CLink('XYZ123', 'http://ticket.com', null, null, true),
			);

			$dnssecTable->addRow($row);
		}


		$minutesDown = new CLink(
			$this->data['dnssec']['incidentTotal'],
			'dnstest.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type=1&slvItemId='.$this->data['dnssec']['itemid']
		);

		$testsInfo = array(
			_('Minutes down'),
			':',
			SPACE,
			$minutesDown,
			SPACE,
			_n('minute', 'minutes', $this->data['dnssec']['incidentTotal']),
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['dnssec']['inIncident'],
				$this->data['dnssec']['incidentTotal'] - $this->data['dnssec']['inIncident']
			).')'
		);

		$details = new CSpan(array(
			_s(
				'From %1$s till %2$s',
				date('d.m.Y H:i:s', zbxDateToTime($this->data['filter_from'])),
				date('d.m.Y H:i:s', zbxDateToTime($this->data['filter_to']))
			),
			BR(),
			_s(
				'Incidents: %1$s',
				isset($this->data['dnssec']) ? count($this->data['dnssec']['events']) : 0
			),
			BR(),
			$testsInfo
		));

		$rollingWeek = new CSpan(_s('%1$s Rolling week status', $this->data['dnssec']['slv'].'%'), 'rolling-week-status');
		$dnssecInfoTable->addRow(array($details, $rollingWeek));
		$dnssecTab->additem($dnssecInfoTable);

		$dnssecTab->additem($dnssecTable);
	}
	else {
		$dnssecTab->additem(new CDiv(bold(_('DNSSEC is disabled.')), 'red center'));
	}

	if (isset($this->data['rdds']['events'])) {
		$rddsInfoTable = new CTable(null, 'incidents-info');

		$rddsTable = new CTableInfo($noData);
		$rddsTable->setHeader($headers);

		foreach ($this->data['rdds']['events'] as $event) {
			if ($event['false_positive']) {
				$incidentStatus = _('False positive');
			}
			else {
				$incidentStatus = $event['status'] ? _('Active') : _('Resolved');
			}
			$row = array(
				new CLink(
					$event['eventid'],
					'dnstest.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['rdds']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['rdds']['availItemId']
				),
				$incidentStatus,
				date('d.m.Y H:i:s', $event['startTime']),
				isset($event['endTime']) ? date('d.m.Y H:i:s', $event['endTime']) : '-',
				isset($event['incidentHistory']) ? $event['incidentHistory'] : '-',
				$event['rollingWeekHistory'],
				new CLink('XYZ123', 'http://ticket.com', null, null, true),
			);

			$rddsTable->addRow($row);
		}

		$minutesDown = new CLink(
			$this->data['rdds']['incidentTotal'],
			'dnstest.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type=2&slvItemId='.$this->data['rdds']['itemid']
		);

		$testsInfo = array(
			_('Minutes down'),
			':',
			SPACE,
			$minutesDown,
			SPACE,
			_n('minute', 'minutes', $this->data['rdds']['incidentTotal']),
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['rdds']['inIncident'],
				$this->data['rdds']['incidentTotal'] - $this->data['rdds']['inIncident']
			).')'
		);

		$details = new CSpan(array(
			_s(
				'From %1$s till %2$s',
				date('d.m.Y H:i:s', zbxDateToTime($this->data['filter_from'])),
				date('d.m.Y H:i:s', zbxDateToTime($this->data['filter_to']))
			),
			BR(),
			_s(
				'Incidents: %1$s',
				isset($this->data['rdds']) ? count($this->data['rdds']['events']) : 0
			),
			BR(),
			$testsInfo
		));

		$rollingWeek = new CSpan(_s('%1$s Rolling week status', $this->data['rdds']['slv'].'%'), 'rolling-week-status');
		$rddsInfoTable->addRow(array($details, $rollingWeek));
		$rddsTab->additem($rddsInfoTable);

		$rddsTab->additem($rddsTable);
	}
	else {
		$rddsTab->additem(new CDiv(bold(_('RDDS is disabled.')), 'red center'));
	}
}
else {
	$dnsTab->additem(new CDiv(_($noData), 'center'));
	$dnssecTab->additem(new CDiv(_($noData), 'center'));
	$rddsTab->additem(new CDiv(_($noData), 'center'));
}

$eppTab = null;

$incidentPage->addTab('dnsTab', _('DNS'), $dnsTab);
$incidentPage->addTab('dnssecTab', _('DNSSEC'), $dnssecTab);
$incidentPage->addTab('rddsTab', _('RDDS'), $rddsTab);
$incidentPage->addTab('eppTab', _('EPP'), $eppTab);

$dnsTestWidget->addItem($incidentPage);

require_once dirname(__FILE__).'/js/dnstest.incidents.list.js.php';

return $dnsTestWidget;
