<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

$widget = (new CWidget())->setTitle(_('IT services availability report').':'.SPACE.$data['service']['name']);

$controls = new CList();

$form = (new CForm())
	->setMethod('get')
	->addVar('action', 'report.services')
	->addVar('serviceid', $data['service']['serviceid']);

$controls->addItem([
	SPACE._('Period').SPACE,
	new CComboBox('period', $data['period'], 'submit()', [
		'daily' => _('Daily'),
		'weekly' => _('Weekly'),
		'monthly' => _('Monthly'),
		'yearly' => _('Yearly')
	])
]);

if ($data['period'] != 'yearly') {
	$cmbYear = new CComboBox('year', $data['year'], 'submit();');

	for ($y = (date('Y') - $data['YEAR_LEFT_SHIFT']); $y <= date('Y'); $y++) {
		$cmbYear->addItem($y, $y);
	}
	$controls->addItem([SPACE._('Year').SPACE, $cmbYear]);
}

$form->addItem($controls);
$widget->setControls($form);

// create table
$table = new CTableInfo();
switch ($data['period']) {
	case 'yearly':
		$header = [_('Year'), _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')];
		break;
	case 'monthly':
		$header = [_('Month'), _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')];
		break;
	case 'daily':
		$header = [_('Day'), _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')];
		break;
	case 'weekly':
		$header = [_('From'), _('Till'), _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')];
		break;
}
$table->setHeader($header);

foreach ($data['sla']['sla'] as $sla) {
	$ok = (new CSpan(
		sprintf('%dd %dh %dm',
			$sla['okTime'] / SEC_PER_DAY,
			($sla['okTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
			($sla['okTime'] % SEC_PER_HOUR) / SEC_PER_MIN
		)))->addClass(ZBX_STYLE_GREEN);

	$problems = (new CSpan(
		sprintf('%dd %dh %dm',
			$sla['problemTime'] / SEC_PER_DAY,
			($sla['problemTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
			($sla['problemTime'] % SEC_PER_HOUR) /SEC_PER_MIN
		)))->addClass(ZBX_STYLE_RED);

	$downtime = sprintf('%dd %dh %dm',
		$sla['downtimeTime'] / SEC_PER_DAY,
		($sla['downtimeTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
		($sla['downtimeTime'] % SEC_PER_HOUR) / SEC_PER_MIN
	);

	$percentage = (new CSpan(sprintf('%2.4f', $sla['sla'])))
		->addClass($sla['sla'] >= $data['service']['goodsla'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);

	switch ($data['period']) {
		case 'yearly':
			$from = zbx_date2str(_x('Y', DATE_FORMAT_CONTEXT), $sla['from']);
			$to = null;
			break;
		case 'monthly':
			$from =  zbx_date2str(_x('F', DATE_FORMAT_CONTEXT), $sla['from']);
			$to = null;
			break;
		case 'daily':
			$from = zbx_date2str(DATE_FORMAT, $sla['from']);
			$to = null;
			break;
		case 'weekly':
			$from = zbx_date2str(DATE_TIME_FORMAT, $sla['from']);
			$to = zbx_date2str(DATE_TIME_FORMAT, $sla['to']);
			break;
	}

	$table->addRow([
		$from,
		$to,
		$sla['okTime'] == 0 ? '' : $ok,
		$sla['problemTime'] == 0 ? '' : $problems,
		$sla['downtimeTime'] ==0 ? '' : (new CSpan($downtime))->addClass(ZBX_STYLE_GREY),
		($data['service']['showsla']) ? $percentage : '',
		($data['service']['showsla']) ? new CSpan($data['service']['goodsla']) : ''
	]);
}

$widget->addItem($table)->show();
