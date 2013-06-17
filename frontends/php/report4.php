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

$currentYear = date('Y');

// fetch media types
$media_types = array();
$db_media_types = DBselect(
	'SELECT mt.*'.
	' FROM media_type mt'.
		whereDbNode('mt.mediatypeid').
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

	// fetch the year of the first alert
	if (($firstAlert = DBfetch(DBselect('SELECT MIN(a.clock) AS clock FROM alerts a'))) && $firstAlert['clock']) {
		$minYear = date('Y', $firstAlert['clock']);
	}
	// if no alerts exist, use the current year
	else {
		$minYear = date('Y');
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
		for ($y = $minYear; $y <= date('Y'); $y++) {
			$cmbYear->addItem($y, $y);
		}
		$form->addItem($cmbYear);
	}

	show_table_header(_('Notifications'), $form);

	$header = array();
	$db_users = DBselect(
			'SELECT u.*'.
			' FROM users u'.
			whereDbNode('u.userid').
			' ORDER BY u.alias,u.userid'
	);
	while ($user_data = DBfetch($db_users)) {
		$header[] = new CCol($user_data['alias'], 'vertical_rotation');
		$users[$user_data['userid']] = $user_data['alias'];
	}

	$intervals = array();
	switch ($period) {
		case 'yearly':
			$minTime = mktime(0, 0, 0, 1, 1, $minYear);

			$dateFormat = REPORT4_ANNUALLY_DATE_FORMAT;
			array_unshift($header, new CCol(_('Year'), 'center'));

			for ($i = $minYear; $i <= date('Y'); $i++) {
				$intervals[mktime(0, 0, 0, 1, 1, $i)] = mktime(0, 0, 0, 1, 1, $i + 1);
			}

			break;

		case 'monthly':
			$minTime = mktime(0, 0, 0, 1, 1, $year);

			$dateFormat = REPORT4_MONTHLY_DATE_FORMAT;
			array_unshift($header, new CCol(_('Month'),'center'));

			$max = ($year == $currentYear) ? date('n') : 12;
			for ($i = 1; $i <= $max; $i++) {
				$intervals[mktime(0, 0, 0, $i, 1, $year)] = mktime(0, 0, 0, $i + 1, 1, $year);
			}

			break;

		case 'daily':
			$minTime = mktime(0, 0, 0, 1, 1, $year);

			$dateFormat = REPORT4_DAILY_DATE_FORMAT;
			array_unshift($header, new CCol(_('Day'),'center'));

			$max = ($year == $currentYear) ? date('z') : DAY_IN_YEAR;
			for ($i = 1; $i <= $max; $i++) {
				$intervals[mktime(0, 0, 0, 1, $i, $year)] = mktime(0, 0, 0, 1, $i + 1, $year);
			}

			break;

		case 'weekly':
			$time = mktime(0, 0, 0, 1, 1, $year);
			$wd = date('w', $time);
			$wd = ($wd == 0) ? 6 : $wd - 1;
			$minTime = $time - $wd * SEC_PER_DAY;

			$dateFormat = REPORT4_WEEKLY_DATE_FORMAT;
			array_unshift($header, new CCol(_('From'), 'center'), new CCol(_('Till'), 'center'));

			$max = ($year == $currentYear) ? date('W') - 1 : 52;
			for ($i = 0; $i <= $max; $i++) {
				$intervals[strtotime('+'.$i.' week', $minTime)] = strtotime('+'.($i + 1).' week', $minTime);
			}

			break;
	}

	// time till
	$maxTime = ($year == $currentYear) ? time() : mktime(0, 0, 0, 1, 1, $year + 1);

	// fetch alerts
	$alerts = array();
	foreach (eventSourceObjects() as $sourceObject) {
		$alerts = array_merge($alerts, API::Alert()->get(array(
			'output' => array('mediatypeid', 'userid', 'clock'),
			'eventsource' => $sourceObject['source'],
			'eventobject' => $sourceObject['object'],
			'mediatypeids' => (get_request('media_type')) ? get_request('media_type') : null,
			'time_from' => $minTime,
			'time_till' => $maxTime
		)));
	}
	// sort alerts in chronological order so we could easily iterate through them later
	CArrayHelper::sort($alerts, array('clock'));

	$table->setHeader($header, 'vertical_header');
	foreach ($intervals as $from => $till) {
		// interval start
		$row = array(zbx_date2str($dateFormat, $from));

		// interval end, displayed only for week intervals
		if ($period == 'weekly') {
			$row[] = zbx_date2str($dateFormat, min($till, time()));
		}

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

		// loop through alerts until we reach an alert from the next interval
		while ($alert = current($alerts)) {
			if ($alert['clock'] >= $till) {
				break;
			}

			if (isset($summary[$alert['userid']])) {
				$summary[$alert['userid']]['total']++;
				if (isset($summary[$alert['userid']]['medias'][$alert['mediatypeid']])) {
					$summary[$alert['userid']]['medias'][$alert['mediatypeid']]++;
				}
				else {
					$summary[$alert['userid']]['medias'][$alert['mediatypeid']] = 1;
				}
			}

			next($alerts);
		}

		foreach ($summary as $s) {
			array_push($row, array($s['total'], ($media_type == 0) ? SPACE.'('.implode('/', $s['medias']).')' : ''));
		}

		$table->addRow($row);
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
