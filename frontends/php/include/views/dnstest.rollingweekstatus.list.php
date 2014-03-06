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
$filterStatus->addItem(2, _('disabled'));

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

$filter = new CButton('filter', _('Filter'), "submit();");
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
$filterForm->addVar('filter_set', 1);
$dnsTestWidget->addFlicker($filterForm, CProfile::get('web.dnstest.rollingweekstatus.filter.state', 0));

$table = new CTableInfo(_('No TLD\'s found.'));
$table->setHeader(array(
	make_sorting_header(_('TLD'), 'name'),
	make_sorting_header(_('DNS (4Hrs)') , 'dns'),
	make_sorting_header(_('DNSSEC (4Hrs)'), 'dnssec'),
	make_sorting_header(_('RDDS (24Hrs)'), 'rdds'),
	make_sorting_header(_('EPP (24Hrs)'), 'epp')
));

if (isset($this->data['tld'])) {
	$from = date('YmdHis', time() - SEC_PER_WEEK);
	$till = date('YmdHis', time());
	foreach ($this->data['tld'] as $key => $tld) {

		// DNS
		if (isset($tld['dns'])) {
			if ($tld['dns']['trigger']) {
				if ($tld['dns']['incident'] && isset($tld['dns']['availItemId'])
						&& isset($tld['dns']['itemid'])) {
					$dnsStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'dnstest.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld['dns']['incident'].
							'&slvItemId='.$tld['dns']['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld['dns']['availItemId'].'&filter_set=1'
					);
				}
				else {
					$dnsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$dnsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnsValue = ($tld['dns']['lastvalue'] > 0)
				? new CLink(
					$tld['dns']['lastvalue'].'%',
					'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&type='.DNSTEST_DNS.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$dnsGraph = ($tld['dns']['lastvalue'] > 0)
				? new CLink(
					'graph',
					'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['dns']['itemid'],
					'cell-value'
				)
				: null;
			$dns = array(new CSpan($dnsValue, 'right'), $dnsStatus, $dnsGraph);
		}
		else {
			$dns = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$dns->setHint('Incorrect TLD configuration.', '', 'on');
		}

		// DNSSEC
		if (isset($tld['dnssec'])) {
			if ($tld['dnssec']['trigger']) {
				if ($tld['dnssec']['incident'] && isset($tld['dnssec']['availItemId'])
						&& isset($tld['dnssec']['itemid'])) {
					$dnssecStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'dnstest.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld['dnssec']['incident'].
							'&slvItemId='.$tld['dnssec']['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld['dnssec']['availItemId'].'&filter_set=1'
					);
				}
				else {
					$dnssecStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$dnssecStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnssecValue = ($tld['dnssec']['lastvalue'] > 0)
				? new CLink(
					$tld['dnssec']['lastvalue'].'%',
					'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&type='.DNSTEST_DNSSEC.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$dnssecGraph = ($tld['dnssec']['lastvalue'] > 0)
				? new CLink(
					'graph',
					'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['dnssec']['itemid'],
					'cell-value'
				)
				: null;
			$dnssec =  array(new CSpan($dnssecValue, 'right'), $dnssecStatus, $dnssecGraph);
		}
		else {
			$dnssec = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$dnssec->setHint('DNSSEC is disabled.', '', 'on');
		}

		// RDDS
		if (isset($tld['rdds'])) {
			if ($tld['rdds']['trigger']) {
				if ($tld['rdds']['incident'] && isset($tld['rdds']['availItemId'])
						&& isset($tld['rdds']['itemid'])) {
					$rddsStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'dnstest.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld['rdds']['incident'].
							'&slvItemId='.$tld['rdds']['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld['rdds']['availItemId'].'&filter_set=1'
					);
				}
				else {
					$rddsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$rddsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$rddsValue = ($tld['rdds']['lastvalue'] > 0)
				? new CLink(
					$tld['rdds']['lastvalue'].'%',
					'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&type='.DNSTEST_RDDS.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$rddsGraph = ($tld['rdds']['lastvalue'] > 0)
				? new CLink(
					'graph',
					'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['rdds']['itemid'],
					'cell-value'
				)
				: null;
			$rdds =  array(new CSpan($rddsValue, 'right'), $rddsStatus, $rddsGraph);
		}
		else {
			$rdds = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$rdds->setHint('RDDS is disabled.', '', 'on');
		}

		// EPP
		if (isset($tld['epp'])) {
			if ($tld['epp']['trigger']) {
				if ($tld['epp']['incident'] && isset($tld['epp']['availItemId'])
						&& isset($tld['epp']['itemid'])) {
					$eppStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'dnstest.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld['epp']['incident'].
							'&slvItemId='.$tld['epp']['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld['epp']['availItemId'].'&filter_set=1'
					);
				}
				else {
					$eppStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$eppStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$eppValue = ($tld['epp']['lastvalue'] > 0)
				? new CLink(
					$tld['epp']['lastvalue'].'%',
					'dnstest.incidents.php?filter_set=1&filter_rolling_week=1&type='.DNSTEST_EPP.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$eppGraph = ($tld['epp']['lastvalue'] > 0)
				? new CLink(
					'graph',
					'history.php?action=showgraph&period='.SEC_PER_WEEK.'&itemid='.$tld['epp']['itemid'],
					'cell-value'
				)
				: null;
			$epp =  array(new CSpan($eppValue, 'right'), $eppStatus, $eppGraph);
		}
		else {
			$epp = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$epp->setHint('EPP is disabled.', '', 'on');
		}
		$row = array(
			$tld['name'],
			$dns,
			$dnssec,
			$rdds,
			$epp
		);

		$table->addRow($row);
	}
}

$table = array($this->data['paging'], $table, $this->data['paging']);
$dnsTestWidget->addItem($table);

require_once dirname(__FILE__).'/js/dnstest.rollingweekstatus.list.js.php';

return $dnsTestWidget;
