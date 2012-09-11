<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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

$page['title'] = _('Notification report');
$page['file'] = 'report4.php';
$page['hist_arg'] = array('media_type','period','year');

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'year' =>		array(T_ZBX_INT, O_OPT, P_SYS|P_NZERO,	null,	null),
	'period' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_NZERO,	IN('"daily","weekly","monthly","yearly"'), null),
	'media_type' =>	array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	null)
);
check_fields($fields);

$year = get_request('year', intval(date('Y')));
$period = get_request('period', 'weekly');
$media_type = get_request('media_type', 0);

$_REQUEST['year'] = $year;
$_REQUEST['period'] = $period;
$_REQUEST['media_type'] = $media_type;

/*
 * Display
 */
$media_types = array();

$db_media_types = DBselect(
	'SELECT mt.*'.
	' FROM media_type mt'.
	' WHERE '.DBin_node('mt.mediatypeid').
	' ORDER BY mt.description'
);
while ($media_type_data = DBfetch($db_media_types)) {
	$media_types[$media_type_data['mediatypeid']] = $media_type_data['description'];
}

// if no media types were defined, we have nothing to show
if (zbx_empty($media_types)) {
	show_table_header(_('Notifications'));
	$table = new CTableInfo(_('No media types defined.'));
	$table->show();
}
else {
	$table = new CTableInfo();
	$table->makeVerticalRotation();

	if (($min_time = DBfetch(DBselect('SELECT MIN(a.clock) AS clock FROM alerts a'))) && $min_time['clock']) {
		$MIN_YEAR = intval(date('Y', $min_time['clock']));
	}

	if (!isset($MIN_YEAR)) {
		$MIN_YEAR = intval(date('Y'));
	}

	$form = new CForm();
	$form->setMethod('get');

	$form->addItem(SPACE._('Media type').SPACE);
	$cmbMedia = new CComboBox('media_type', $media_type, 'submit();');
	$cmbMedia->addItem(0, _('all'));

	foreach ($media_types as $media_type_id => $media_type_description) {
		$cmbMedia->addItem($media_type_id, $media_type_description);

		// we won't need other media types in the future, if only one was selected
		if ($media_type > 0 && $media_type != $media_type_id) {
			unset($media_types[$media_type_id]);
		}
	}
	$form->addItem($cmbMedia);

	$form->addItem(SPACE._('Period').SPACE);
	$cmbPeriod = new CComboBox('period', $period, 'submit();');
	$cmbPeriod->addItem('daily', _('Daily'));
	$cmbPeriod->addItem('weekly', _('Weekly'));
	$cmbPeriod->addItem('monthly', _('Monthly'));
	$cmbPeriod->addItem('yearly', _('Yearly'));
	$form->addItem($cmbPeriod);

	if ($period != 'yearly') {
		$form->addItem(SPACE._('Year').SPACE);
		$cmbYear = new CComboBox('year', $year, 'submit();');
		for ($y = $MIN_YEAR; $y <= date('Y'); $y++) {
			$cmbYear->addItem($y, $y);
		}
		$form->addItem($cmbYear);
	}

	show_table_header(_('Notifications'), $form);

	$header = array();
	$db_users = DBselect('SELECT u.* FROM users u WHERE '.DBin_node('u.userid').' ORDER BY u.alias,u.userid');
	while ($user_data = DBfetch($db_users)) {
		$header[] = new CCol($user_data['alias'], 'vertical_rotation');
		$users[$user_data['userid']] = $user_data['alias'];
	}

	switch ($period) {
		case 'yearly':
			$from = $MIN_YEAR;
			$to = date('Y');
			array_unshift($header, new CCol(_('Year'), 'center'));

			function get_time($y) {
				return mktime(0, 0, 0, 1, 1, $y);
			}
			function format_time($t) {
				return zbx_date2str(REPORT4_ANNUALLY_DATE_FORMAT, $t);
			}
			function format_time2($t) {
				return null;
			}

			break;

		case 'monthly':
			$from = 1;
			$to = 12;
			array_unshift($header, new CCol(_('Month'),'center'));

			function get_time($m) {
				global $year;
				return mktime(0, 0, 0, $m, 1, $year);
			}
			function format_time($t) {
				return zbx_date2str(REPORT4_MONTHLY_DATE_FORMAT, $t);
			}
			function format_time2($t) {
				return null;
			}

			break;

		case 'daily':
			$from = 1;
			$to = DAY_IN_YEAR;
			array_unshift($header, new CCol(_('Day'),'center'));

			function get_time($d) {
				global $year;
				return mktime(0, 0, 0, 1, $d, $year);
			}
			function format_time($t) {
				return zbx_date2str(REPORT4_DAILY_DATE_FORMAT,$t);
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
				static $beg;
				if (!isset($beg)) {
					global $year;
					$time = mktime(0, 0, 0, 1, 1, $year);
					$wd = date('w', $time);
					$wd = ($wd == 0) ? 6 : $wd - 1;
					$beg = $time - $wd * SEC_PER_DAY;
				}
				return strtotime("+$w week", $beg);
			}
			function format_time($t) {
				return zbx_date2str(REPORT4_WEEKLY_DATE_FORMAT,$t);
			}
			function format_time2($t) {
				return format_time($t);
			}

			break;
	}

	$table->setHeader($header, 'vertical_header');
	for ($t = $from; $t <= $to; $t++) {
		if (($start = get_time($t)) > time()) {
			break;
		}

		if (($end = get_time($t + 1)) > time()) {
			$end = time();
		}

		$table_row = array(format_time($start), format_time2($end));

		// getting all alerts in this period of time
		$options = array(
			'output' => array('mediatypeid', 'userid'),
			'time_from' => $start,
			'time_till' => $end
		);

		// if we must get only specific media type, no need to select the other ones
		if ($media_type > 0){
			$options['mediatypeids'] = $media_type;
		}

		// getting data through API
		$alert_info = API::Alert()->get($options);

		// counting alert count for each user and media type
		$summary = array();
		foreach ($users as $userid => $alias) {
			$summary[$userid] = array();
			$summary[$userid]['total'] = 0;
			$summary[$userid]['medias'] = array();
			foreach ($media_types as $media_type_nr => $mt) {
				$summary[$userid]['medias'][$media_type_nr] = 0;
			}
		}

		foreach ($alert_info as $ai) {
			if (!isset($summary[$ai['userid']])) {
				continue;
			}

			$summary[$ai['userid']]['total']++;
			if (isset($summary[$ai['userid']]['medias'][$ai['mediatypeid']])) {
				$summary[$ai['userid']]['medias'][$ai['mediatypeid']]++;
			}
			else {
				$summary[$ai['userid']]['medias'][$ai['mediatypeid']] = 1;
			}
		}

		foreach ($summary as $s) {
			array_push($table_row, array($s['total'], ($media_type == 0) ? SPACE.'('.implode('/', $s['medias']).')' : ''));
		}

		$table->addRow($table_row);
	}
	$table->show();

	if ($media_type == 0) {
		echo SBR;

		$links = array();
		foreach ($media_types as $id => $description) {
			$links[] = new CLink($description, 'media_types.php?form=edit&mediatypeid='.$id);
			$links[] = SPACE.'/'.SPACE;
		}
		array_pop($links);

		$linksDiv = new CDiv(array(SPACE._('all').SPACE.'('.SPACE, $links, SPACE.')'));
		$linksDiv->show();
	}
}

require_once dirname(__FILE__).'/include/page_footer.php';
