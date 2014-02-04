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

if ($this->data['type'] == DNSTEST_DNS || $this->data['type'] == DNSTEST_DNSSEC) {
	$headers = array(
		_('Probe ID'),
		_('Row result')
	);
}
elseif ($this->data['type'] == DNSTEST_RDDS) {
	$headers = array(
		_('Probe ID'),
		_('Row result'),
		_('RDDS43'),
		_('RDDS43 IP'),
		_('RDDS43 RTT'),
		_('RDDS43 UPD'),
		_('RDDS80'),
		_('RDDS80 IP'),
		_('RDDS80 RTT'),
	);
}
else {
	$headers = array(
		_('Probe ID'),
		_('Row result'),
		_('IP'),
		_('Login'),
		_('Update'),
		_('Info')
	);
}

$noData = _('No particular test found.');

$particularTestsInfoTable = new CTable(null, 'filter info-block');

$particularTestsTable = new CTableInfo($noData);
$particularTestsTable->setHeader($headers);

foreach ($this->data['probes'] as $probe) {
	$status = null;
	if (isset($probe['status']) && $probe['status'] === PROBE_DOWN) {
		if ($this->data['type'] == DNSTEST_DNS || $this->data['type'] == DNSTEST_DNSSEC) {
			$link = new CSpan(_('Offline'), 'red');
		}
		elseif ($this->data['type'] == DNSTEST_RDDS) {
			$rdds = new CSpan(_('Offline'), 'red');
			$rdds43 = new CSpan(_('Offline'), 'red');
			$rdds80 = new CSpan(_('Offline'), 'red');
		}
		else {
			$epp = new CSpan(_('Offline'), 'red');
		}
	}
	else {
		if ($this->data['type'] == DNSTEST_DNS) {
			// DNS
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
						'&time='.$this->data['time'].'&probe='.$probe['host'].'&type='.$this->data['type']
				);
			}
			else {
				$link = new CSpan(_('Not monitored'), 'red');
			}
		}
		elseif ($this->data['type'] == DNSTEST_DNSSEC) {
			// DNSSEC
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
						'&time='.$this->data['time'].'&probe='.$probe['host'].'&type='.$this->data['type']
				);
			}
			else {
				$link = new CSpan(_('Not monitored'), 'red');
			}
		}
		elseif ($this->data['type'] == DNSTEST_RDDS) {
			// RDDS
			if (!isset($probe['value']) || $probe['value'] === null) {
				$rdds43 = _('No result');
				$rdds80 = _('No result');
				$rdds = _('No result');
			}
			elseif ($probe['value'] == 0) {
				$rdds43 = new CSpan(_('Down'), 'red');
				$rdds80 = new CSpan(_('Down'), 'red');
				$rdds = new CSpan(_('Down'), 'red');
			}
			elseif ($probe['value'] == 1) {
				$rdds43 = new CSpan(_('Up'), 'green');
				$rdds80 = new CSpan(_('Up'), 'green');
				$rdds = new CSpan(_('Up'), 'green');
			}
			elseif ($probe['value'] == 2) {
				$rdds43 = new CSpan(_('Up'), 'green');
				$rdds80 = new CSpan(_('Down'), 'red');
				$rdds = new CSpan(_('Down'), 'red');
			}
			elseif ($probe['value'] == 3) {
				$rdds43 = new CSpan(_('Down'), 'green');
				$rdds80 = new CSpan(_('Up'), 'red');
				$rdds = new CSpan(_('Down'), 'red');
			}
		}
		else {
			// EPP
			if (!isset($probe['value']) || $probe['value'] === null) {
				$epp = _('No result');
			}
			elseif ($probe['value'] == 0) {
				$epp = new CSpan(_('Down'), 'red');
			}
			elseif ($probe['value'] == 1) {
				$epp = new CSpan(_('Up'), 'green');
			}
		}
	}

	if ($this->data['type'] == DNSTEST_DNS || $this->data['type'] == DNSTEST_DNSSEC) {
		$row = array(
			$probe['name'],
			$link
		);
	}
	elseif ($this->data['type'] == DNSTEST_RDDS) {
		$row = array(
			$probe['name'],
			$rdds,
			$rdds43,
			(isset($probe['rdds43']['ip']) && $probe['rdds43']['ip']) ? $probe['rdds43']['ip'] : '-',
			(isset($probe['rdds43']['rtt']) && $probe['rdds43']['rtt']) ? $probe['rdds43']['rtt'] : '-',
			(isset($probe['rdds43']['upd']) && $probe['rdds43']['upd']) ? $probe['rdds43']['upd'] : '-',
			$rdds80,
			(isset($probe['rdds80']['ip']) && $probe['rdds80']['ip']) ? $probe['rdds80']['ip'] : '-',
			(isset($probe['rdds80']['rtt']) && $probe['rdds80']['rtt']) ? $probe['rdds80']['rtt'] : '-'
		);
	}
	else {
		$row = array(
			$probe['name'],
			$epp,
			(isset($probe['ip']) && $probe['ip']) ? $probe['ip'] : '-',
			(isset($probe['login']) && $probe['login']) ? $probe['login'] : '-',
			(isset($probe['update']) && $probe['update']) ? $probe['update'] : '-',
			(isset($probe['info']) && $probe['info']) ? $probe['info'] : '-'
		);
	}

	$particularTestsTable->addRow($row);
}
if ($this->data['type'] == DNSTEST_DNS) {
	$additionInfo = array(
		BR(),
		new CSpan(bold(_s(
			'%1$s out of %2$s probes reported availability of service',
			round($this->data['availProbes'] / $this->data['totalProbes'] * 100, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT).'%',
			$this->data['totalProbes']
		)))
	);
}
elseif ($this->data['type'] == DNSTEST_DNSSEC) {
	$additionInfo = array(
		BR(),
		new CSpan(bold(_s(
			'%1$s out of %2$s tests reported availability of service',
			round($this->data['availTests'] / $this->data['totalTests'] * 100, ZBX_UNITS_ROUNDOFF_UPPER_LIMIT).'%',
			$this->data['totalTests']
		)))
	);
}

$particularTests = array(
	new CSpan(array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])),
	BR(),
	new CSpan(array(bold(_('Service')), ':', SPACE, $this->data['slvItem']['name'])),
	BR(),
	new CSpan(array(bold(_('Test time')), ':', SPACE, date('d.m.Y H:i:s', $this->data['time'])))
);

if ($this->data['type'] == DNSTEST_DNS || $this->data['type'] == DNSTEST_DNSSEC) {
	$particularTests = array_merge($particularTests, $additionInfo);
}

$rollingWeek = new CSpan(_s('%1$s Rolling week status', $this->data['slv'].'%'), 'rolling-week-status');
$particularTestsInfoTable->addRow(array(array($particularTests, $rollingWeek)));
$dnsTestWidget->additem($particularTestsInfoTable);

$dnsTestWidget->additem($particularTestsTable);

return $dnsTestWidget;
