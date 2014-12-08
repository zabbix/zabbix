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


$rsmWidget = new CWidget(null, 'rolling-week-status');

$rsmWidget->addPageHeader(_('TLD Rolling week status'));

// header
$rsmWidget->addHeader(_('TLD Rolling week status'));
$rsmWidget->addHeaderRowNumber();

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
$slvs = explode(',', $this->data['slv']);
$filterValue->addItem('', _('any'));
$filterValue->addItem(SLA_MONITORING_SLV_FILTER_NON_ZERO, _('non-zero'));

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
		new CButton('checkAllServices', _('All/Any'), null, 'link_menu checkbox-block'),
		new CSpan(array(SPACE, bold(_('Exceeding or equal to')), ':'.SPACE, $filterValue), 'select-block'),
	)),
	array(array(bold(_('Current status')), ':'.SPACE), $filterStatus)
));

// set disabled for no permission elements
// ccTLD's group
$filterCctldGroup = new CCheckBox('filter_cctld_group',
	isset($this->data['filter_cctld_group']) ? $this->data['filter_cctld_group'] : null, null, 1
);
if (!$this->data['allowedGroups'][RSM_CC_TLD_GROUP]) {
	$filterCctldGroup->setAttribute('disabled', true);
}

// gTLD's group
$filterGtldGroup = new CCheckBox('filter_gtld_group',
	isset($this->data['filter_gtld_group']) ? $this->data['filter_gtld_group'] : null, null, 1
);
if (!$this->data['allowedGroups'][RSM_G_TLD_GROUP]) {
	$filterGtldGroup->setAttribute('disabled', true);
}

// other TLD's group
$filterOtherGroup = new CCheckBox('filter_othertld_group',
	isset($this->data['filter_othertld_group']) ? $this->data['filter_othertld_group'] : null, null, 1
);
if (!$this->data['allowedGroups'][RSM_OTHER_TLD_GROUP]) {
	$filterOtherGroup->setAttribute('disabled', true);
}

// test TLD's group
$filterTestGroup = new CCheckBox('filter_test_group',
	isset($this->data['filter_test_group']) ? $this->data['filter_test_group'] : null, null, 1
);
if (!$this->data['allowedGroups'][RSM_TEST_GROUP]) {
	$filterTestGroup->setAttribute('disabled', true);
}

$filterTable->addRow(array(
	'',
	array(array(
		array(
			$filterCctldGroup,
			SPACE,
			bold(_(RSM_CC_TLD_GROUP)),
		),
		new CSpan(array(
			$filterGtldGroup,
			SPACE,
			bold(_(RSM_G_TLD_GROUP))
		), 'checkbox-block'),
		new CSpan(array(
			$filterOtherGroup,
			SPACE,
			bold(_(RSM_OTHER_TLD_GROUP))
		), 'checkbox-block'),
		new CSpan(array(
			$filterTestGroup,
			SPACE,
			bold(_(RSM_TEST_GROUP))
		), 'checkbox-block'),
		new CButton('checkAllGroups', _('All/Any'), null, 'link_menu checkbox-block')
	)),
	''
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
$filterForm->addVar('checkAllServicesValue', 0);
$filterForm->addVar('checkAllGroupsValue', 0);
$filterForm->addVar('filter_set', 1);
$rsmWidget->addFlicker($filterForm, CProfile::get('web.rsm.rollingweekstatus.filter.state', 0));

$table = new CTableInfo(_('No TLD\'s found.'));
$table->setHeader(array(
	make_sorting_header(_('TLD'), 'name'),
	make_sorting_header(_('Type'), 'type'),
	make_sorting_header(_('DNS (4Hrs)') , 'dns'),
	make_sorting_header(_('DNSSEC (4Hrs)'), 'dnssec'),
	make_sorting_header(_('RDDS (24Hrs)'), 'rdds'),
	make_sorting_header(_('EPP (24Hrs)'), 'epp')
));

if (isset($this->data['tld'])) {
	$serverTime = time() - RSM_ROLLWEEK_SHIFT_BACK;
	$from = date('YmdHis', $serverTime - $this->data['rollWeekSeconds']);
	$till = date('YmdHis', $serverTime);
	foreach ($this->data['tld'] as $key => $tld) {
		// DNS
		if (isset($tld[RSM_DNS])) {
			if ($tld[RSM_DNS]['trigger']) {
				if ($tld[RSM_DNS]['incident'] && isset($tld[RSM_DNS]['availItemId'])
						&& isset($tld[RSM_DNS]['itemid'])) {
					$dnsStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_DNS]['incident'].
							'&slvItemId='.$tld[RSM_DNS]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_DNS]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$dnsStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$dnsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnsValue = ($tld[RSM_DNS]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_DNS]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_DNS.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$dnsGraph = ($tld[RSM_DNS]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
						$tld[RSM_DNS]['itemid'], 'cell-value')
				: null;
			$dns = array(new CSpan($dnsValue, 'right'), $dnsStatus, $dnsGraph);
		}
		else {
			$dns = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$dns->setHint('Incorrect TLD configuration.', '', 'on');
		}

		// DNSSEC
		if (isset($tld[RSM_DNSSEC])) {
			if ($tld[RSM_DNSSEC]['trigger']) {
				if ($tld[RSM_DNSSEC]['incident'] && isset($tld[RSM_DNSSEC]['availItemId'])
						&& isset($tld[RSM_DNSSEC]['itemid'])) {
					$dnssecStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_DNSSEC]['incident'].
							'&slvItemId='.$tld[RSM_DNSSEC]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_DNSSEC]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$dnssecStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$dnssecStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnssecValue = ($tld[RSM_DNSSEC]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_DNSSEC]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_DNSSEC.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$dnssecGraph = ($tld[RSM_DNSSEC]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
						$tld[RSM_DNSSEC]['itemid'], 'cell-value'
				)
				: null;
			$dnssec =  array(new CSpan($dnssecValue, 'right'), $dnssecStatus, $dnssecGraph);
		}
		else {
			$dnssec = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$dnssec->setHint('DNSSEC is disabled.', '', 'on');
		}

		// RDDS
		if (isset($tld[RSM_RDDS])) {
			if ($tld[RSM_RDDS]['trigger']) {
				if ($tld[RSM_RDDS]['incident'] && isset($tld[RSM_RDDS]['availItemId'])
						&& isset($tld[RSM_RDDS]['itemid'])) {
					$rddsStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_RDDS]['incident'].
							'&slvItemId='.$tld[RSM_RDDS]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_RDDS]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$rddsStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$rddsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$rddsValue = ($tld[RSM_RDDS]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_RDDS]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_RDDS.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$rddsGraph = ($tld[RSM_RDDS]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
						$tld[RSM_RDDS]['itemid'], 'cell-value')
				: null;
			$rdds =  array(new CSpan($rddsValue, 'right'), $rddsStatus, $rddsGraph);
		}
		else {
			$rdds = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$rdds->setHint('RDDS is disabled.', '', 'on');
		}

		// EPP
		if (isset($tld[RSM_EPP])) {
			if ($tld[RSM_EPP]['trigger']) {
				if ($tld[RSM_EPP]['incident'] && isset($tld[RSM_EPP]['availItemId'])
						&& isset($tld[RSM_EPP]['itemid'])) {
					$eppStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_EPP]['incident'].
							'&slvItemId='.$tld[RSM_EPP]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_EPP]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$eppStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$eppStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$eppValue = ($tld[RSM_EPP]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_EPP]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_EPP.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$eppGraph = ($tld[RSM_EPP]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
					$tld[RSM_EPP]['itemid'], 'cell-value')
				: null;
			$epp =  array(new CSpan($eppValue, 'right'), $eppStatus, $eppGraph);
		}
		else {
			$epp = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$epp->setHint('EPP is disabled.', '', 'on');
		}
		$row = array(
			$tld['name'],
			$tld['type'],
			$dns,
			$dnssec,
			$rdds,
			$epp
		);

		$table->addRow($row);
	}
}

$table = array($this->data['paging'], $table, $this->data['paging']);
$rsmWidget->addItem($table);

require_once dirname(__FILE__).'/js/rsm.rollingweekstatus.list.js.php';

return $rsmWidget;
