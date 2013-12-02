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


$dnsTestWidget = new CWidget(null, 'monthly-reports');

$dnsTestWidget->addPageHeader(_('Monthly report'));

// header
$dnsTestWidget->addHeader(_('Monthly report'));
$dnsTestWidget->addHeaderRowNumber();

$filterTable = new CTable('', 'filter');

$filterTld = new CTextBox('filter_search',
	isset($this->data['filter_search']) ? $this->data['filter_search'] : null
);
$filterTld->setAttribute('autocomplete', 'off');

$filterMonth = new CComboBox(
	'filter_month',
	isset($this->data['filter_month']) ? $this->data['filter_month'] : null
);

for ($i = 1; $i <= 12; $i++) {
	$filterMonth->addItem($i, getMonthCaption($i));
}

$filterYear = new CComboBox(
	'filter_year',
	isset($this->data['filter_year']) ? $this->data['filter_year'] : null
);

for ($i = 2013; $i <= date('Y', time()); $i++) {
	$filterYear->addItem($i, $i);
}


$filterTable->addRow(array(
	array(array(bold(_('TLD')), ':'.SPACE), $filterTld),
	array(array($filterMonth, SPACE, $filterYear)),
	array(new CLink(
		_('Download all TLD reports'),
		'dnstest.monthlyreports.php?filter_set=1&filter_search='.$this->data['filter_search'].'&filter_year='.
			$this->data['filter_year'].'&filter_month='.$this->data['filter_month'].'&export=1'
	))
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
$dnsTestWidget->addFlicker($filterForm, CProfile::get('web.dnstest.monthlyreports.filter.state', 0));

if (isset($this->data['tld'])) {
	$infoBlock = new CTable(null, 'filter info-block');
	$infoBlock->addRow(array(
		array(bold(_('TLD')), ':', SPACE, $this->data['tld']['name'])
	));
	$dnsTestWidget->additem($infoBlock);
}

$table = new CTableInfo(_('No TLD\'s found.'));
$table->setHeader(array(
	_('Service'),
	_('Parameter'),
	_('SLV'),
	_('Benchmark'),
	SPACE
));

foreach ($this->data['services'] as $name => $services) {
	if (count($services) > 1) {
		$table->addRow(array(
			$name,
			new CCol(SPACE, null, 4)
		));

		foreach ($services as $service) {
			$table->addRow(array(
				SPACE,
				$service['ns'],
				isset($service['slv']) ? $service['slv'] : '-',
				isset($service['slv']) ? $service['slv'] : '-',
				new CLink('graph', 'history.php?action=showgraph&period=2592000&itemid=')
			));
		}
	}
	else {
		$serviceValues = reset($services);
		$table->addRow(array(
			$name,
			SPACE,
			isset($serviceValues['slv']) ? $serviceValues['slv'] : '-',
			isset($serviceValues['slv']) ? $serviceValues['slv'] : '-',
			new CLink('graph', 'history.php?action=showgraph&period=2592000&itemid=')
		));
	}
}

$dnsTestWidget->addItem($table);

require_once dirname(__FILE__).'/js/dnstest.monthlyreports.list.js.php';

return $dnsTestWidget;
