<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'year' =>		[T_ZBX_INT, O_OPT, P_SYS|P_NZERO,	null,	null],
	'period' =>		[T_ZBX_STR, O_OPT, P_SYS|P_NZERO,	IN('"daily","weekly","monthly","yearly"'), null],
	'media_type' =>	[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	null]
];
check_fields($fields);

if (getRequest('media_type')) {
	$mediaTypeData = API::MediaType()->get([
		'mediatypeids' => [$_REQUEST['media_type']],
		'countOutput' => true
	]);
	if (!$mediaTypeData) {
		access_deny ();
	}
}

$year = getRequest('year', intval(date('Y')));
$period = getRequest('period', 'weekly');
$media_type = getRequest('media_type', 0);

$_REQUEST['year'] = $year;
$_REQUEST['period'] = $period;
$_REQUEST['media_type'] = $media_type;

$currentYear = date('Y');

// fetch media types
$media_types = [];
$db_media_types = API::MediaType()->get([
	'output' => ['name'],
	'preservekeys' => true
]);

CArrayHelper::sort($db_media_types, ['name']);
foreach ($db_media_types as $mediatypeid => $db_media_type) {
	$media_types[$mediatypeid] = $db_media_type['name'];
}

$widget = (new CWidget())->setTitle(_('Notifications'));

// if no media types were defined, we have nothing to show
if (zbx_empty($media_types)) {
	$table = new CTableInfo();
	$widget->addItem($table)->show();
}
else {
	$table = (new CTableInfo())->makeVerticalRotation();

	// fetch the year of the first alert
	if (($firstAlert = DBfetch(DBselect('SELECT MIN(a.clock) AS clock FROM alerts a'))) && $firstAlert['clock']) {
		$minYear = date('Y', $firstAlert['clock']);
	}
	// if no alerts exist, use the current year
	else {
		$minYear = date('Y');
	}

	$select_media_type = (new CSelect('media_type'))
		->setValue($media_type)
		->setFocusableElementId('media-type')
		->onChange('$(this).closest("form").submit()')
		->addOption(new CSelectOption(0, _('all')))
		->addOptions(CSelect::createOptionsFromArray($media_types));

	$select_period = (new CSelect('period'))
		->setValue($period)
		->setFocusableElementId('period')
		->onChange('$(this).closest("form").submit()')
		->addOptions(CSelect::createOptionsFromArray([
			'daily' => _('Daily'),
			'weekly' => _('Weekly'),
			'monthly' => _('Monthly'),
			'yearly' => _('Yearly')
		]));

	$controls = (new CList())
		->addItem([
			new CLabel(_('Media type'), $select_media_type->getFocusableElementId()),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$select_media_type
		])
		->addItem([
			new CLabel(_('Period'), $select_period->getFocusableElementId()),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$select_period
		]);

	if ($period != 'yearly') {
		$cmb_year = (new CSelect('year'))
			->setValue($year)
			->setFocusableElementId('year')
			->onChange('$(this).closest("form").submit()');

		for ($y = $minYear; $y <= date('Y'); $y++) {
			$cmb_year->addOption(new CSelectOption($y, $y));
		}

		$controls->addItem([
			new CLabel(_('Year'), $cmb_year->getFocusableElementId()),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$cmb_year
		]);
	}

	$widget->setControls((new CForm('get'))
		->cleanItems()
		->setAttribute('aria-label', _('Main filter'))
		->addItem($controls)
	);

	$header = [];
	$users = [];

	$db_users = API::User()->get([
		'output' => ['userid', 'alias', 'name', 'surname'],
		'sortfield' => 'alias'
	]);

	foreach ($db_users as $user_data) {
		$full_name = getUserFullname($user_data);
		$header[] = (new CColHeader($full_name))
			->addClass('vertical_rotation')
			->setTitle($full_name);
		$users[] = $user_data['userid'];
	}

	$intervals = [];

	switch ($period) {
		case 'yearly':
			$minTime = mktime(0, 0, 0, 1, 1, $minYear);

			$dateFormat = _x('Y', DATE_FORMAT_CONTEXT);
			array_unshift($header, _('Year'));

			for ($i = $minYear; $i <= date('Y'); $i++) {
				$intervals[mktime(0, 0, 0, 1, 1, $i)] = mktime(0, 0, 0, 1, 1, $i + 1);
			}

			break;

		case 'monthly':
			$minTime = mktime(0, 0, 0, 1, 1, $year);

			$dateFormat = _x('F', DATE_FORMAT_CONTEXT);
			array_unshift($header, _('Month'));

			$max = ($year == $currentYear) ? date('n') : 12;
			for ($i = 1; $i <= $max; $i++) {
				$intervals[mktime(0, 0, 0, $i, 1, $year)] = mktime(0, 0, 0, $i + 1, 1, $year);
			}

			break;

		case 'daily':
			$minTime = mktime(0, 0, 0, 1, 1, $year);

			$dateFormat = DATE_FORMAT;
			array_unshift($header, _('Day'));

			$max = ($year == $currentYear) ? date('z') + 1 : date('z', mktime(0, 0, 0, 12, 31, $year)) + 1;
			for ($i = 1; $i <= $max; $i++) {
				$intervals[mktime(0, 0, 0, 1, $i, $year)] = mktime(0, 0, 0, 1, $i + 1, $year);
			}

			break;

		case 'weekly':
			$time = mktime(0, 0, 0, 1, 1, $year);
			$wd = date('w', $time);
			$wd = ($wd == 0) ? 6 : $wd - 1;
			$minTime = $time - $wd * SEC_PER_DAY;

			$dateFormat = DATE_TIME_FORMAT;
			array_unshift($header, _('From'), _('Till'));

			$max = ($year == $currentYear) ? date('W') - 1 : 52;
			for ($i = 0; $i <= $max; $i++) {
				$intervals[strtotime('+'.$i.' week', $minTime)] = strtotime('+'.($i + 1).' week', $minTime);
			}

			break;
	}

	// time till
	$maxTime = ($year == $currentYear || $period === 'yearly') ? time() : mktime(0, 0, 0, 1, 1, $year + 1);

	// fetch alerts
	$alerts = [];
	foreach (eventSourceObjects() as $sourceObject) {
		$alerts = array_merge($alerts, API::Alert()->get([
			'output' => ['mediatypeid', 'userid', 'clock'],
			'eventsource' => $sourceObject['source'],
			'eventobject' => $sourceObject['object'],
			'mediatypeids' => (getRequest('media_type')) ? getRequest('media_type') : null,
			'time_from' => $minTime,
			'time_till' => $maxTime
		]));
	}
	// sort alerts in chronological order so we could easily iterate through them later
	CArrayHelper::sort($alerts, ['clock']);

	$table->setHeader($header);

	if ($media_type > 0) {
		$media_types = [$media_type => $media_types[$media_type]];
	}

	foreach ($intervals as $from => $till) {
		// interval start
		$row = [zbx_date2str($dateFormat, $from)];

		// interval end, displayed only for week intervals
		if ($period == 'weekly') {
			$row[] = zbx_date2str($dateFormat, min($till, time()));
		}

		// counting alert count for each user and media type
		$summary = [];
		foreach ($users as $userid) {
			$summary[$userid] = [];
			$summary[$userid]['total'] = 0;
			$summary[$userid]['medias'] = [];
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
			if ($s['total'] == 0) {
				array_push($row, '');
			}
			else {
				array_push($row, [$s['total'], ($media_type == 0) ? SPACE.'('.implode('/', $s['medias']).')' : '']);
			}
		}

		$table->addRow($row);
	}

	$widget->addItem($table);

	if ($media_type == 0) {
		$allowed_ui_media_types = CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_MEDIA_TYPES);
		$links = [];
		foreach ($media_types as $id => $name) {
			$links[] = !$allowed_ui_media_types
				? $name
				: new CLink($name, 'zabbix.php?action=mediatype.edit&mediatypeid='.$id);
			$links[] = SPACE.'/'.SPACE;
		}
		array_pop($links);

		$linksDiv = new CDiv([SPACE._('all').SPACE.'('.SPACE, $links, SPACE.')']);

		$widget
			->addItem(new CTag('br'))
			->addItem($linksDiv);
	}

	$widget->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
