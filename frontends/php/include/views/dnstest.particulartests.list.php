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


$dnsTestWidget = new CWidget(null, 'particular-test');

// header
$dnsTestWidget->addPageHeader(_('Details of particular test'), SPACE);
$dnsTestWidget->addHeader(_('Details of particular test'));

$headers = array(
	_('Probe ID'),
	_('Row result')
);
$noData = _('No particular test found.');

$particularTestsInfoTable = new CTable(null, 'filter info-block');

$particularTestsTable = new CTableInfo($noData);
$particularTestsTable->setHeader($headers);

foreach ($this->data['probes'] as $probe) {
	$status = null;
	if (isset($probe['status']) && $probe['status'] === PROBE_DOWN) {
		$link = new CSpan(_('Offline'), 'red');
	}
	else {
		if ($this->data['type'] == 0) {
			if (isset($probe['value'])) {
				if ($probe['value']) {
					$status = new CSpan(_('Up'), 'green');
				}
				else {
					$status = new CSpan(_('Down'), 'red');
				}
				$link = new CLink(
					$status,
					'dnstest.particularproxys.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
						'&time='.$this->data['time'].'&probe='.$probe['host']
				);
			}
			else {
				$link = new CSpan(_('Not monitored'), 'red');
			}
		}
		elseif ($this->data['type'] == 1) {
			if (isset($probe['value']['ok'])) {
				$values = array();
				if ($probe['value']['ok']) {
					$values[] = $probe['value']['ok'].' OK';
				}
				if ($probe['value']['fail']) {
					$values[] = $probe['value']['fail'].' FAILED';
				}
				if ($probe['value']['noResult']) {
					$values[] = $probe['value']['noResult'].' NO RESULT';
				}
				$link = new CLink(
					implode(', ', $values),
					'dnstest.particularproxys.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
						'&time='.$this->data['time'].'&probe='.$probe['host']
				);
			}
			else {
				$link = new CSpan(_('Not monitored'), 'red');
			}
		}
		elseif ($this->data['type'] == 2) {
			if (!isset($probe['value']) || $probe['value'] == null) {
				$link = _('No result');
			}
			elseif ($probe['value'] == 2) {
				$link = new CSpan(_('Up'), 'green');
			}
			elseif ($probe['value'] == 1) {
				$link = _('Only RDDS43');
			}
			else {
				$link = new CSpan(_('Down'), 'red');
			}
		}
	}
	$row = array(
		$probe['name'],
		$link
	);

	$particularTestsTable->addRow($row);
}

$particularTests = array(
	new CSpan(array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])),
	BR(),
	new CSpan(array(bold(_('Service')), ':', SPACE, $this->data['slvItem']['name'])),
	BR(),
	new CSpan(array(bold(_('Test time')), ':', SPACE, date('d.m.Y H:i:s', $this->data['time'])))
);

$rollingWeek = new CSpan(_s('%1$s Rolling week status', $this->data['slv'].'%'), 'rolling-week-status');
$particularTestsInfoTable->addRow(array(array($particularTests, $rollingWeek)));
$dnsTestWidget->additem($particularTestsInfoTable);

$dnsTestWidget->additem($particularTestsTable);

return $dnsTestWidget;
