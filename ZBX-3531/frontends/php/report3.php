<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/services.inc.php';

$page['title'] = _('IT services availability report');
$page['file'] = 'report3.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';
?>
<?php
//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'serviceid' =>	array(T_ZBX_INT, O_MAND,	P_SYS,	DB_ID,										null),
	'period' =>		array(T_ZBX_STR, O_OPT,		null,	IN('"daily","weekly","monthly","yearly"'),	null),
	'year' =>		array(T_ZBX_INT, O_OPT,		null,	null,										null)
);

check_fields($fields);

$period = get_request('period', 'weekly');
$year = get_request('year', date('Y'));

define('YEAR_LEFT_SHIFT', 5);
?>
<?php
$result = DBselect('SELECT s.* FROM services s WHERE s.serviceid='.$_REQUEST['serviceid'], 1);
if (!$service = DBfetch($result)) {
	fatal_error(_('No IT services defined.'));
}

if ($service['triggerid']) {
	$options = array(
		'triggerids' => $service['triggerid'],
		'output' => API_OUTPUT_SHORTEN,
		'nodeids' => get_current_nodeid(true)
	);

	$db_data = API::Trigger()->get($options);
	if (empty($db_data)) {
		access_deny();
	}
}

if (!DBfetch(DBselect('SELECT s.serviceid FROM services s WHERE s.serviceid='.$_REQUEST['serviceid']))) {
	fatal_error(_('No IT services defined.'));
}

$form = new CForm();
$form->setMethod('get');
$form->addVar('serviceid', $_REQUEST['serviceid']);

$cmbPeriod = new CComboBox('period', $period, 'submit();');
$cmbPeriod->addItem('daily', _('Daily'));
$cmbPeriod->addItem('weekly', _('Weekly'));
$cmbPeriod->addItem('monthly', _('Monthly'));
$cmbPeriod->addItem('yearly', _('Yearly'));
$form->addItem(array(SPACE._('Period').SPACE, $cmbPeriod));

if ($period != 'yearly') {
	$cmbYear = new CComboBox('year', $year, 'submit();');

	for ($y = (date('Y') - YEAR_LEFT_SHIFT); $y <= date('Y'); $y++) {
		$cmbYear->addItem($y, $y);
	}
	$form->addItem(array(SPACE._('Year').SPACE, $cmbYear));
}

show_table_header(array(
	_('IT SERVICES AVAILABILITY REPORT'),
	SPACE.'"',
	new CLink($service['name'], 'srv_status.php?showgraph=1&serviceid='.$service['serviceid']),
	'"'
	), $form
);

$table = new CTableInfo();

$header = array(_('Ok'), _('Problems'), _('Downtime'), _('Percentage'), _('SLA'));

switch ($period) {
	case 'yearly':
		$from = date('Y') - YEAR_LEFT_SHIFT;
		$to = date('Y');
		array_unshift($header, new CCol(_('Year'), 'center'));

		function get_time($y) {
			return mktime(0, 0, 0, 1, 1, $y);
		}

		function format_time($t) {
			return zbx_date2str(_('Y'), $t);
		}

		function format_time2($t) {
			return null;
		}
		break;
	case 'monthly':
		$from = 1;
		$to = 12;
		array_unshift($header, new CCol(_('Month'), 'center'));

		function get_time($m) {
			global $year;
			return mktime(0, 0, 0, $m, 1, $year);
		}

		function format_time($t) {
			return zbx_date2str(_('M Y'), $t);
		}

		function format_time2($t) {
			return null;
		}
		break;
	case 'daily':
		$from = 1;
		$to = DAY_IN_YEAR;
		array_unshift($header, new CCol(_('Day'), 'center'));

		function get_time($d) {
			global $year;
			return mktime(0, 0, 0, 1, $d, $year);
		}

		function format_time($t) {
			return zbx_date2str(_('d M Y'), $t);
		}

		function format_time2($t) {
			return null;
		}
		break;
	case 'weekly':
	default:
		$from = 0;
		$to = 52;
		array_unshift($header, new CCol(_('From'), 'center'), new CCol(_('Till'), 'center'));

		function get_time($w) {
			global $year;

			$time = mktime(12, 0, 0, 1, 1, $year);
			$time += $w * SEC_PER_WEEK;

			$wd = date('w', $time);
			$time -= $wd * SEC_PER_DAY;

			$y = date('Y', $time);
			$m = date('m', $time);
			$d = date('d', $time);
			return mktime(0, 0, 0, $m, $d, $y);
		}

		function format_time($t) {
			return zbx_date2str(_('d M Y H:i'), $t);
		}

		function format_time2($t) {
			return format_time($t);
		}
		break;
}

$table->setHeader($header);

for ($t = $from; $t <= $to; $t++) {
	if (($start = get_time($t)) > time()) {
		break;
	}

	if (($end = get_time($t + 1)) > time()) {
		$end = time();
	}

	$stat = calculateServiceAvailability($service['serviceid'], $start, $end);

	$ok = new CSpan(
		sprintf('%dd %dh %dm',
			$stat['ok_time'] / SEC_PER_DAY,
			($stat['ok_time'] % SEC_PER_DAY) / SEC_PER_HOUR,
			($stat['ok_time'] % SEC_PER_HOUR) / SEC_PER_MIN
		), 'off'
	);

	$problems = new CSpan(
		sprintf('%dd %dh %dm',
			$stat['problem_time'] / SEC_PER_DAY,
			($stat['problem_time'] % SEC_PER_DAY) / SEC_PER_HOUR,
			($stat['problem_time'] % SEC_PER_HOUR) /SEC_PER_MIN
		), 'on'
	);

	$downtime = sprintf('%dd %dh %dm',
		$stat['downtime_time'] / SEC_PER_DAY,
		($stat['downtime_time'] % SEC_PER_DAY) / SEC_PER_HOUR,
		($stat['downtime_time'] % SEC_PER_HOUR) / SEC_PER_MIN
	);

	$percentage = new CSpan(sprintf('%2.2f%%', $stat['ok']), 'off');

	$table->addRow(array(
		format_time($start),
		format_time2($end),
		$ok,
		$problems,
		$downtime,
		$percentage,
		$service['showsla'] == 1 ? new CSpan($service['goodsla'], $stat['ok'] >= $service['goodsla'] ? 'off' : 'on') : '-'
	));
}
$table->show();

require_once dirname(__FILE__).'/include/page_footer.php';
?>
