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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';

$page['title'] = _('IT services availability report');
$page['file'] = 'report3.php';

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'serviceid' =>	[T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,										null],
	'period' =>		[T_ZBX_STR, O_OPT,		null,	IN('"daily","weekly","monthly","yearly"'),	null],
	'year' =>		[T_ZBX_INT, O_OPT,		null,	null,										null]
];
check_fields($fields);

$period = getRequest('period', 'weekly');
$year = getRequest('year', date('Y'));

define('YEAR_LEFT_SHIFT', 5);

$service = API::Service()->get([
	'output' => ['serviceid', 'name', 'showsla', 'goodsla'],
	'serviceids' => $_REQUEST['serviceid']
]);
$service = reset($service);
if (!$service) {
	access_deny();
}

$widget = (new CWidget())->setTitle(_('IT services availability report').':'.SPACE.$service['name']);

$controls = new CList();

$form = (new CForm())
	->setMethod('get')
	->addVar('serviceid', $_REQUEST['serviceid']);

$controls->addItem([
	SPACE._('Period').SPACE,
	new CComboBox('period', $period, 'submit()', [
		'daily' => _('Daily'),
		'weekly' => _('Weekly'),
		'monthly' => _('Monthly'),
		'yearly' => _('Yearly')
	])
]);

if ($period != 'yearly') {
	$cmbYear = new CComboBox('year', $year, 'submit();');

	for ($y = (date('Y') - YEAR_LEFT_SHIFT); $y <= date('Y'); $y++) {
		$cmbYear->addItem($y, $y);
	}
	$controls->addItem([SPACE._('Year').SPACE, $cmbYear]);
}

$form->addItem($controls);
$widget->setControls($form);

$table = new CTableInfo();

$header = [_('Ok'), _('Problems'), _('Downtime'), _('SLA'), _('Acceptable SLA')];

switch ($period) {
	case 'yearly':
		$from = date('Y') - YEAR_LEFT_SHIFT;
		$to = date('Y');
		array_unshift($header, _('Year'));

		function get_time($y) {
			return mktime(0, 0, 0, 1, 1, $y);
		}

		function format_time($t) {
			return zbx_date2str(_x('Y', DATE_FORMAT_CONTEXT), $t);
		}

		function format_time2($t) {
			return null;
		}
		break;
	case 'monthly':
		$from = 1;
		$to = 12;
		array_unshift($header, _('Month'));

		function get_time($m) {
			global $year;
			return mktime(0, 0, 0, $m, 1, $year);
		}

		function format_time($t) {
			return zbx_date2str(_x('F', DATE_FORMAT_CONTEXT), $t);
		}

		function format_time2($t) {
			return null;
		}
		break;
	case 'daily':
		$from = 1;
		$to = DAY_IN_YEAR;
		array_unshift($header, _('Day'));

		function get_time($d) {
			global $year;
			return mktime(0, 0, 0, 1, $d, $year);
		}

		function format_time($t) {
			return zbx_date2str(DATE_FORMAT, $t);
		}

		function format_time2($t) {
			return null;
		}
		break;
	case 'weekly':
	default:
		$from = 0;
		$to = 52;
		array_unshift($header, _('From'), _('Till'));

		function get_time($w) {
			static $beg;
			if (!isset($beg)) {
				global $year;
				$time = mktime(0,0,0,1, 1, $year);
				$wd = date('w', $time);
				$wd = $wd == 0 ? 6 : $wd - 1;
				$beg =  $time - $wd * SEC_PER_DAY;
			}

			return strtotime("+$w week", $beg);
		}

		function format_time($t) {
			return zbx_date2str(DATE_TIME_FORMAT, $t);
		}

		function format_time2($t) {
			return format_time($t);
		}
		break;
}

$table->setHeader($header);

$intervals = [];
for ($t = $from; $t <= $to; $t++) {
	if (($start = get_time($t)) > time()) {
		break;
	}

	if (($end = get_time($t + 1)) > time()) {
		$end = time();
	}

	$intervals[] = [
		'from' => $start,
		'to' => $end
	];
}

$sla = API::Service()->getSla([
	'serviceids' => $service['serviceid'],
	'intervals' => $intervals
]);
$sla = reset($sla);

foreach ($sla['sla'] as $intervalSla) {
	$ok = (new CSpan(
		sprintf('%dd %dh %dm',
			$intervalSla['okTime'] / SEC_PER_DAY,
			($intervalSla['okTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
			($intervalSla['okTime'] % SEC_PER_HOUR) / SEC_PER_MIN
		)))->addClass(ZBX_STYLE_GREEN);

	$problems = (new CSpan(
		sprintf('%dd %dh %dm',
			$intervalSla['problemTime'] / SEC_PER_DAY,
			($intervalSla['problemTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
			($intervalSla['problemTime'] % SEC_PER_HOUR) /SEC_PER_MIN
		)))->addClass(ZBX_STYLE_RED);

	$downtime = sprintf('%dd %dh %dm',
		$intervalSla['downtimeTime'] / SEC_PER_DAY,
		($intervalSla['downtimeTime'] % SEC_PER_DAY) / SEC_PER_HOUR,
		($intervalSla['downtimeTime'] % SEC_PER_HOUR) / SEC_PER_MIN
	);

	$percentage = (new CSpan(sprintf('%2.4f', $intervalSla['sla'])))
		->addClass($intervalSla['sla'] >= $service['goodsla'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);

	$table->addRow([
		format_time($intervalSla['from']),
		format_time2($intervalSla['to']),
		$intervalSla['okTime'] == 0 ? '' : $ok,
		$intervalSla['problemTime'] == 0 ? '' : $problems,
		$intervalSla['downtimeTime'] ==0 ? '' : (new CSpan($downtime))->addClass(ZBX_STYLE_GREY),
		($service['showsla']) ? $percentage : '',
		($service['showsla']) ? new CSpan($service['goodsla']) : ''
	]);
}
$widget->addItem($table);
$widget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
