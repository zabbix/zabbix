<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

$widget = (new CWidget())->setTitle(_('Service availability report').': '.$data['service']['name']);

$controls = (new CList())
	->addItem([
		new CLabel(_('Period'), 'period'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CComboBox('period', $data['period'], 'submit()', [
			'daily' => _('Daily'),
			'weekly' => _('Weekly'),
			'monthly' => _('Monthly'),
			'yearly' => _('Yearly')
		])
	]);

if ($data['period'] != 'yearly') {
	$years = [];
	for ($y = (date('Y') - $data['YEAR_LEFT_SHIFT']); $y <= date('Y'); $y++) {
		$years[$y] = $y;
	}
	$controls->addItem([
		new CLabel(_('Year'), 'year'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		new CComboBox('year', $data['year'], 'submit();', $years)
	]);
}

$widget->setControls(new CList([
	(new CForm())
		->cleanItems()
		->setMethod('get')
		->addVar('action', 'report.services')
		->addVar('serviceid', $data['service']['serviceid'])
		->addVar('fullscreen', $data['fullscreen'])
		->setAttribute('aria-label', _('Main filter'))
		->addItem($controls),
	(new CTag('nav', true, get_icon('fullscreen', ['fullscreen' => $data['fullscreen']])))
		->setAttribute('aria-label', _('Content controls'))
]));

$header = [
	'yearly' => [_('Year'), null, _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')],
	'monthly' => [_('Month'), null, _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')],
	'weekly' => [_('From'), _('Till'), _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')],
	'daily' => [_('Day'), null, _('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')]
];

// create table
$table = (new CTableInfo())->setHeader($header[$data['period']]);

order_result($data['sla']['sla'], 'from', ZBX_SORT_DOWN);

foreach ($data['sla']['sla'] as $sla) {
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

	$ok = ($sla['okTime'] != 0)
		? (new CSpan(
			sprintf('%dd %dh %dm',
				$sla['okTime'] / SEC_PER_DAY,
				($sla['okTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
				($sla['okTime'] % SEC_PER_HOUR) / SEC_PER_MIN
			)
		))->addClass(ZBX_STYLE_GREEN)
		: '';

	$problems = ($sla['problemTime'] != 0)
		? (new CSpan(
			sprintf('%dd %dh %dm',
				$sla['problemTime'] / SEC_PER_DAY,
				($sla['problemTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
				($sla['problemTime'] % SEC_PER_HOUR) /SEC_PER_MIN
			)
		))->addClass(ZBX_STYLE_RED)
		: '';

	$downtime = ($sla['downtimeTime'] != 0)
		? (new CSpan(
			sprintf('%dd %dh %dm',
				$sla['downtimeTime'] / SEC_PER_DAY,
				($sla['downtimeTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
				($sla['downtimeTime'] % SEC_PER_HOUR) / SEC_PER_MIN
			)
		))->addClass(ZBX_STYLE_GREY)
		: '';

	$percentage = $data['service']['showsla']
		? (new CSpan(sprintf('%2.4f', $sla['sla'])))
			->addClass($sla['sla'] >= $data['service']['goodsla'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
		: '';

	$goodsla = $data['service']['showsla'] ? $data['service']['goodsla'] : '';

	$table->addRow([$from, $to, $ok, $problems, $downtime, $percentage, $goodsla]);
}

$widget
	->addItem($table)
	->show();
