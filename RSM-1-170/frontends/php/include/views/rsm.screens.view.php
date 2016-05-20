<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$widget = new CWidget(null, 'sla-reports');

$widget->addPageHeader(_('SLA report'));

// Header block.
$widget->addHeader(_('SLA report'));
$widget->addHeaderRowNumber();

$filter_table = new CTable('', 'filter');

$filter_tld = new CTextBox('filter_search',
	isset($data['filter_search']) ? $data['filter_search'] : null
);
$filter_tld->setAttribute('autocomplete', 'off');

$filter_month = new CComboBox(
	'filter_month',
	isset($data['filter_month']) ? $data['filter_month'] : null
);

for ($i = 1; $i <= 12; $i++) {
	$filter_month->addItem($i, getMonthCaption($i));
}

$filterYear = new CComboBox(
	'filter_year',
	isset($data['filter_year']) ? $data['filter_year'] : null
);

for ($i = SLA_MONITORING_START_YEAR; $i <= date('Y', time()); $i++) {
	$filterYear->addItem($i, $i);
}

$filter_table->addRow(array(array($filter_month, SPACE, $filterYear)));

$filter = new CButton('filter', _('Filter'), "submit();");
$filter->useJQueryStyle('main');

$reset = new CButton('reset', _('Reset'), "javascript: clearAllForm('zbx_filter');");
$reset->useJQueryStyle();

$div_buttons = new CDiv(array($filter, SPACE, $reset));
$div_buttons->setAttribute('style', 'padding: 4px 0px;');

$filter_table->addRow(new CCol($div_buttons, 'center'));

$filter_form = new CForm('get');
$filter_form->setAttribute('name', 'zbx_filter');
$filter_form->setAttribute('id', 'zbx_filter');
$filter_form->addItem($filter_table);
$filter_form->addVar('checkallvalue', 0);
$filter_form->addVar('filter_set', 1);
$filter_form->addVar('tld', $data['tld']);
$filter_form->addVar('type', $data['type']);
$filter_form->addVar('item_key', $data['item_key']);
$widget->addFlicker($filter_form, CProfile::get('web.rsm.screens.filter.state', 0));
$widget->addItem($data['screen']);

return $widget;
