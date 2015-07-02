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
require_once dirname(__FILE__).'/include/screens.inc.php';

$page['title'] = _('Configuration of slide shows');
$page['file'] = 'slideconf.php';
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'shows' =>			array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID,	null),
	'slideshowid' =>	array(T_ZBX_INT, O_NO,	P_SYS,		DB_ID,	'(isset({form})&&({form}=="update"))'),
	'name' => array(T_ZBX_STR, O_OPT, null, NOT_EMPTY, 'isset({save})', _('Name')),
	'delay' => array(T_ZBX_INT, O_OPT, null, BETWEEN(1, SEC_PER_DAY), 'isset({save})',_('Default delay (in seconds)')),
	'slides' =>			array(null,		 O_OPT, null,		null,	null),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,		null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,		null,	null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

if (!empty($_REQUEST['slides'])) {
	natksort($_REQUEST['slides']);
}

/*
 * Permissions
 */
if (isset($_REQUEST['slideshowid'])) {
	if (!slideshow_accessible($_REQUEST['slideshowid'], PERM_READ_WRITE)) {
		access_deny();
	}

	$dbSlideshow = get_slideshow_by_slideshowid(get_request('slideshowid'));

	if (!$dbSlideshow) {
		access_deny();
	}
}
if (isset($_REQUEST['go'])) {
	if (!isset($_REQUEST['shows']) || !is_array($_REQUEST['shows'])) {
		access_deny();
	}
	else {
		$dbSlideshowCount = DBfetch(DBselect(
			'SELECT COUNT(*) AS cnt FROM slideshows s WHERE '.dbConditionInt('s.slideshowid', $_REQUEST['shows'])
		));

		if ($dbSlideshowCount['cnt'] != count($_REQUEST['shows'])) {
			access_deny();
		}
	}
}

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['slideshowid'])) {
	unset($_REQUEST['slideshowid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	if (isset($_REQUEST['slideshowid'])) {
		DBstart();
		$result = update_slideshow($_REQUEST['slideshowid'], $_REQUEST['name'], $_REQUEST['delay'], get_request('slides', array()));
		$result = DBend($result);

		$audit_action = AUDIT_ACTION_UPDATE;
		show_messages($result, _('Slide show updated'), _('Cannot update slide show'));
	}
	else {
		DBstart();
		$slideshowid = add_slideshow($_REQUEST['name'], $_REQUEST['delay'], get_request('slides', array()));
		$result = DBend($slideshowid);

		$audit_action = AUDIT_ACTION_ADD;
		show_messages($result, _('Slide show added'), _('Cannot add slide show'));
	}

	if ($result) {
		add_audit($audit_action, AUDIT_RESOURCE_SLIDESHOW, ' Name "'.$_REQUEST['name'].'" ');
		unset($_REQUEST['form'], $_REQUEST['slideshowid']);
		clearCookies($result);
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['slideshowid'])) {
	DBstart();
	delete_slideshow($_REQUEST['slideshowid']);
	$result = DBend();

	show_messages($result, _('Slide show deleted'), _('Cannot delete slide show'));
	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SLIDESHOW, ' Name "'.$dbSlideshow['name'].'" ');
	}

	unset($_REQUEST['slideshowid'], $_REQUEST['form']);
	clearCookies($result);
}
elseif ($_REQUEST['go'] == 'delete') {
	$goResult = true;

	$shows = get_request('shows', array());
	DBstart();

	foreach ($shows as $showid) {
		$goResult &= delete_slideshow($showid);
		if (!$goResult) {
			break;
		}
	}

	$goResult = DBend($goResult);

	if ($goResult) {
		unset($_REQUEST['form']);
	}

	show_messages($goResult, _('Slide show deleted'), _('Cannot delete slide show'));
	clearCookies($goResult);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => get_request('form', null),
		'form_refresh' => get_request('form_refresh', null),
		'slideshowid' => get_request('slideshowid', null),
		'name' => get_request('name', ''),
		'delay' => get_request('delay', ZBX_ITEM_DELAY_DEFAULT),
		'slides' => get_request('slides', array())
	);

	if (isset($data['slideshowid']) && !isset($_REQUEST['form_refresh'])) {
		$data['name'] = $dbSlideshow['name'];
		$data['delay'] = $dbSlideshow['delay'];

		// get slides
		$db_slides = DBselect('SELECT s.* FROM slides s WHERE s.slideshowid='.zbx_dbstr($data['slideshowid']).' ORDER BY s.step');
		while ($slide = DBfetch($db_slides)) {
			$data['slides'][$slide['step']] = array(
				'slideid' => $slide['slideid'],
				'screenid' => $slide['screenid'],
				'delay' => $slide['delay']
			);
		}
	}

	// get slides without delay
	$data['slides_without_delay'] = $data['slides'];
	for ($i = 0, $size = count($data['slides_without_delay']); $i < $size; $i++) {
		unset($data['slides_without_delay'][$i]['delay']);
	}

	// render view
	$slideshowView = new CView('configuration.slideconf.edit', $data);
	$slideshowView->render();
	$slideshowView->show();
}
else {
	$config = select_config();
	$limit = $config['search_limit'] + 1;

	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();

	$data['slides'] = DBfetchArray(DBselect(
			'SELECT s.slideshowid,s.name,s.delay,COUNT(sl.slideshowid) AS cnt'.
			' FROM slideshows s'.
				' LEFT JOIN slides sl ON sl.slideshowid=s.slideshowid'.
			whereDbNode('s.slideshowid').
			' GROUP BY s.slideshowid,s.name,s.delay'.
			' ORDER BY '.(($sortfield === 'cnt') ? 'cnt' : 's.'.$sortfield)
	));

	foreach ($data['slides'] as $key => $slide) {
		if (!slideshow_accessible($slide['slideshowid'], PERM_READ_WRITE)) {
			unset($data['slides'][$key]);
		}
	}

	order_result($data['slides'], $sortfield, $sortorder);

	if ($sortorder == ZBX_SORT_UP) {
		$data['slides'] = array_slice($data['slides'], 0, $limit);
	}
	else {
		$data['slides'] = array_slice($data['slides'], -$limit, $limit);
	}

	order_result($data['slides'], $sortfield, $sortorder);

	$data['paging'] = getPagingLine($data['slides'], array('slideshowid'));

	// nodes
	if ($data['displayNodes'] = is_array(get_current_nodeid())) {
		foreach ($data['slides'] as &$slide) {
			$slide['nodename'] = get_node_name_by_elid($slide['slideshowid'], true);
		}
		unset($slide);
	}

	// render view
	$slideshowView = new CView('configuration.slideconf.list', $data);
	$slideshowView->render();
	$slideshowView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
