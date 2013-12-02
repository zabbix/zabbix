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


$dnsTestWidget = new CWidget(null, 'rolling-week-status');

$dnsTestWidget->addPageHeader(_('TLD Rolling week status'));

// header
$dnsTestWidget->addHeader(_('TLD Rolling week status'));
$dnsTestWidget->addHeaderRowNumber();

$filterTable = new CTable('', 'filter');

$filterTld = new CTextBox('filter_search',
	isset($this->data['filter_search']) ? $this->data['filter_search'] : null
);
$filterTld->setAttribute('autocomplete', 'off');

$filterStatus = new CComboBox('filter_status',
	isset($this->data['filter_status']) ? $this->data['filter_status'] : null
);
$filterStatus->addItem(0, _('all'));
$filterStatus->addItem(1, _('fail'));

$filterValue = new CComboBox('filter_slv', isset($this->data['filter_slv']) ? $this->data['filter_slv'] : null);
$slvs = explode(',', $this->data['slv']['value']);
foreach ($slvs as $slv) {
	$filterValue->addItem($slv, $slv.'%');
}

$filterTable->addRow(array(
	array(array(bold(_('TLD')), ':'.SPACE), $filterTld),
	array(array(
		array(
			new CCheckBox('filter_dns', isset($this->data['filter_dns']) ? $this->data['filter_dns'] : null, null, 1),
			SPACE,
			bold(_('DNS')),
		),
		new CSpan(array(new CCheckBox('filter_dnssec',
			isset($this->data['filter_dnssec']) ? $this->data['filter_dnssec'] : null, null, 1),
			SPACE,
			bold(_('DNSSEC'))
		), 'checkbox-block'),
		new CSpan(array(new CCheckBox('filter_rdds',
			isset($this->data['filter_rdds']) ? $this->data['filter_rdds'] : null, null, 1),
			SPACE,
			bold(_('RDDS'))
		), 'checkbox-block'),
		new CSpan(array(new CCheckBox('filter_epp',
			isset($this->data['filter_epp']) ? $this->data['filter_epp'] : null, null, 1),
			SPACE,
			bold(_('EPP'))
		), 'checkbox-block'),
		new CButton('checkAll', _('All/Any'), null, 'link_menu checkbox-block'),
		new CSpan(array(SPACE, bold(_('Exceeding or equal to')), ':'.SPACE, $filterValue), 'select-block'),
	)),
	array(array(bold(_('Current status')), ':'.SPACE), $filterStatus)
));

$filter = new CButton('filter', _('Filter'),
	"javascript: create_var('zbx_filter', 'filter_set', '1', true);"
);
$filter->useJQueryStyle('main');

$reset = new CButton('reset', _('Reset'), "javascript: clearAllForm('zbx_filter');");
$reset->useJQueryStyle();

$divButtons = new CDiv(array($filter, SPACE, $reset));
$divButtons->setAttribute('style', 'padding: 4px 0px;');

$filterTable->addRow(new CCol($divButtons, 'center', 3));

$filterForm = new CForm('get');
$filterForm->setAttribute('name', 'zbx_filter');
$filterForm->setAttribute('id', 'zbx_filter');
$filterForm->addItem($filterTable);
$filterForm->addVar('checkallvalue', 0);
$dnsTestWidget->addFlicker($filterForm, CProfile::get('web.dnstest.rollingweekstatus.filter.state', 0));

$table = new CTableInfo(_('No TLD\'s found.'));
$table->setHeader(array(
	_('TLD'),
	_('DNS (4Hrs)'),
	_('DNSSEC (4Hrs)'),
	_('RDDS (24Hrs)'),
	_('EPP (24Hrs)')
));

if (isset($this->data['tld'])) {
	foreach ($this->data['tld'] as $key => $tld) {
		if (isset($tld['dns'])) {
			$icon = $tld['dns']['trigger'] ? 'iconrollingweekfail' : 'iconrollingweekok';
			$dnsValue = $tld['dns']['lastvalue']
				? new CLink($tld['dns']['lastvalue'].'%', 'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&'.
					'incident_type=0&host='.$tld['host'],
					'first-cell-value')
				: new CSpan('0%', 'first-cell-value');

			$dnsStatus =  new CDiv(SPACE, 'status_icon status_icon_extra '.$icon.' cell-value');
			$dnsGraph = $tld['dns']['lastvalue']
				? new CLink('graph', 'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['dns']['itemid'],
					'cell-value')
				: null;
			$dns = array($dnsValue, $dnsStatus, $dnsGraph);
		}
		else {
			$dns = new CDiv(SPACE, 'status_icon status_icon_extra iconrollingweekfail');
			$dns->setHint('Incorrect TLD configuration.', '', 'on');
		}
		if (isset($tld['dnssec'])) {
			$icon = $tld['dnssec']['trigger'] ? 'iconrollingweekfail' : 'iconrollingweekok';
			$dnssecValue = $tld['dnssec']['lastvalue']
				? new CLink($tld['dnssec']['lastvalue'].'%', 'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&'.
					'incident_type=1&host='.$tld['host'],
					'first-cell-value')
				: new CSpan('0%', 'first-cell-value');

			$dnssecStatus = new CDiv(SPACE, 'status_icon status_icon_extra '.$icon.' cell-value');
			$dnssecGraph = $tld['dnssec']['lastvalue']
				? new CLink('graph', 'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['dnssec']['itemid'],
					'cell-value')
				: null;
			$dnssec =  array($dnssecValue, $dnssecStatus, $dnssecGraph);
		}
		else {
			$dnssec = new CDiv(SPACE, 'status_icon status_icon_extra iconrollingweekfail');
			$dnssec->setHint('DNSSEC is disabled.', '', 'on');
		}
		if (isset($tld['rdds'])) {
			$icon = $tld['rdds']['trigger'] ? 'iconrollingweekfail' : 'iconrollingweekok';
			$rddsValue = $tld['rdds']['lastvalue']
				? new CLink($tld['rdds']['lastvalue'].'%', 'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&'.
					'incident_type=2&host='.$tld['host'],
					'first-cell-value')
				: new CSpan('0%', 'first-cell-value');

			$rddsStatus = new CDiv(SPACE, 'status_icon status_icon_extra '.$icon.' cell-value');
			$rddsGraph = $tld['rdds']['lastvalue']
				? new CLink('graph', 'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['rdds']['itemid'],
					'cell-value')
				: null;
			$rdds =  array($rddsValue, $rddsStatus, $rddsGraph);
		}
		else {
			$rdds = new CDiv(SPACE, 'status_icon status_icon_extra iconrollingweekfail');
			$rdds->setHint('RDDS is disabled.', '', 'on');
		}
		$row = array(
			$tld['name'],
			$dns,
			$dnssec,
			$rdds,
			'-'
		);

		$table->addRow($row);
	}
}

$table = array($this->data['paging'], $table, $this->data['paging']);
$dnsTestWidget->addItem($table);

require_once dirname(__FILE__).'/js/dnstest.rollingweekstatus.list.js.php';

return $dnsTestWidget;
