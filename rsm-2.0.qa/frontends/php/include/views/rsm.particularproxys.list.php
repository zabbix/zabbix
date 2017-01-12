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


$rsmWidget = new CWidget(null, 'particular-proxy');

// header
$rsmWidget->addPageHeader(_('Test result from particular proxy'), SPACE);
$rsmWidget->addHeader(_('Test result from particular proxy'));

$headers = array(
	_('NS name'),
	_('IP'),
	_('Ms')
);
$noData = _('No particular proxy found.');

$particularProxysInfoTable = new CTable(null, 'filter info-block');

$particularProxysTable = new CTableInfo($noData);
$particularProxysTable->setHeader($headers);

// list generation
$currentNs = null;
foreach ($this->data['proxys'] as $proxy) {
	// remove probe name from list
	if ($proxy['ns'] === $currentNs) {
		$proxy['ns'] = SPACE;
	}
	else {
		$currentNs = $proxy['ns'];
	}

	if ($proxy['ms']) {
		if (!$this->data['minMs']) {
			$ms = $proxy['ms'];
		}
		elseif ($proxy['ms'] < $this->data['minMs']) {
			$ms = new CSpan($proxy['ms'], 'green');
		}
		else {
			$ms = new CSpan($proxy['ms'], 'red');
		}
	}
	else {
		$ms = '-';
	}
	$row = array(
		$proxy['ns'],
		$proxy['ip'],
		$ms
	);
	$particularProxysTable->addRow($row);
}

$particularProxys = array(
	new CSpan(array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])),
	BR(),
	new CSpan(array(bold(_('Service')), ':', SPACE, $this->data['slvItem']['name'])),
	BR(),
	new CSpan(array(bold(_('Test time')), ':', SPACE, date('d.m.Y H:i:s', $this->data['time']))),
	BR(),
	new CSpan(array(bold(_('Probe')), ':', SPACE, $this->data['probe']['name'])),
);

if ($this->data['type'] == RSM_DNS) {
	if ($this->data['testResult'] == true) {
		$testResult = new CSpan(_('Up'), 'green');
	}
	elseif ($this->data['testResult'] == false) {
		$testResult = new CSpan(_('Down'), 'red');
	}
	else {
		$testResult = new CSpan(_('No result'), 'gray');
	}
	array_push($particularProxys, array(BR(),
		new CSpan(array(
			bold(_('Test result')),
			':',
			SPACE,
			$testResult
		))
	));
}

$particularProxysInfoTable->addRow(array($particularProxys));
$particularProxysInfoTable->addRow(array(array(
	new CSpan(array(bold(_('Total number of NS')), ':', SPACE, $this->data['totalNs']), 'first-row-element'),
	new CSpan(array(bold(_('Number of NS with positive result')), ':', SPACE, $this->data['positiveNs']),
		'second-row-element'
	),
	new CSpan(array(bold(_('Number of NS with negative result')), ':', SPACE,
		$this->data['totalNs'] - $this->data['positiveNs']
	))
)));

$rsmWidget->additem($particularProxysInfoTable);

$rsmWidget->additem($particularProxysTable);

return $rsmWidget;
