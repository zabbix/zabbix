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
require_once('include/config.inc.php');
require_once('include/screens.inc.php');
require_once('include/forms.inc.php');
require_once('include/maps.inc.php');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['title'] = _('Configuration of slide shows');
$page['file'] = 'slideconf.php';
$page['hist_arg'] = array();

require_once('include/page_header.php');
?>
<?php
//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'shows' =>			array(T_ZBX_INT, O_OPT,	P_SYS,		DB_ID,	null),
	'slideshowid' =>	array(T_ZBX_INT, O_NO,	P_SYS,		DB_ID,	'(isset({form})&&({form}=="update"))'),
	'name' =>			array(T_ZBX_STR, O_OPT, null,		NOT_EMPTY, 'isset({save})'),
	'delay' =>			array(T_ZBX_INT, O_OPT, null,		BETWEEN(1, SEC_PER_DAY), 'isset({save})'),
	'slides' =>			array(null,		 O_OPT, null,		null,	null),
	'work_slide' =>		array(null,		 O_OPT, null,		null,	null),
	'add_slide' =>		array(T_ZBX_STR, O_OPT, P_ACT,		null,	null),
	'cancel_slide' =>	array(T_ZBX_STR, O_OPT, P_ACT,		null,	null),
	'edit_slide' =>		array(T_ZBX_STR, O_OPT, P_ACT,		null,	null),
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

$_REQUEST['go'] = get_request('go', 'none');
if (!empty($_REQUEST['slides'])) {
	natksort($_REQUEST['slides']);
}

// validate permitions
if (isset($_REQUEST['slideshowid'])) {
	if (!slideshow_accessible($_REQUEST['slideshowid'], PERM_READ_WRITE)) {
		access_deny();
	}
}

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
	}
}
elseif (isset($_REQUEST['cancel_slide'])) {
	unset($_REQUEST['add_slide'], $_REQUEST['work_slide']);
}
elseif (isset($_REQUEST['add_slide'])) {
	// add new slide item to slides
	if (!empty($_REQUEST['work_slide']['screenid'])) {
		$_REQUEST['slides'][] = $_REQUEST['work_slide'];
		unset($_REQUEST['add_slide'], $_REQUEST['work_slide']);
	}
	// init new slide item
	else {
		$_REQUEST['work_slide']['screenid'] = 0;
		$_REQUEST['work_slide']['delay'] = 0;
		$_REQUEST['work_slide']['slideid'] = rand(1, 9999999);
	}
}
elseif (!empty($_REQUEST['edit_slide'])) {
	// update slide item
	if (!empty($_REQUEST['work_slide']['screenid'])) {
		for ($i = 0, $size = count($_REQUEST['slides']); $i < $size; $i++) {
			if ($_REQUEST['slides'][$i]['slideid'] == $_REQUEST['work_slide']['slideid']) {
				$_REQUEST['slides'][$i] = $_REQUEST['work_slide'];
			}
		}
		unset($_REQUEST['edit_slide'], $_REQUEST['work_slide']);
	}
	// init slide item
	else {
		foreach ($_REQUEST['slides'] as $slide) {
			if ($slide['slideid'] == $_REQUEST['edit_slide']) {
				$_REQUEST['work_slide'] = $slide;
			}
		}
	}
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['slideshowid'])) {
	if ($slideshow = get_slideshow_by_slideshowid($_REQUEST['slideshowid'])) {
		DBstart();
		delete_slideshow($_REQUEST['slideshowid']);
		$result = DBend();

		show_messages($result, _('Slide show deleted'), _('Cannot delete slide show'));
		add_audit_if($result, AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SLIDESHOW, ' Name "'.$slideshow['name'].'" ');
	}
	unset($_REQUEST['slideshowid'], $_REQUEST['form']);
}
elseif ($_REQUEST['go'] == 'delete') {
	$go_result = true;
	$shows = get_request('shows', array());

	DBstart();
	foreach ($shows as $showid) {
		$go_result &= delete_slideshow($showid);
		if (!$go_result) {
			break;
		}
	}
	$go_result = DBend($go_result);
	if ($go_result) {
		unset($_REQUEST['form']);
	}
	show_messages($go_result, _('Slide show deleted'), _('Cannot delete slide show'));
}
if ($_REQUEST['go'] != 'none' && !empty($go_result)) {
	$url = new CUrl();
	$path = $url->getPath();
	insert_js('cookie.eraseArray(\''.$path.'\')');
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
		'slides' => get_request('slides', array()),
		'add_slide' => get_request('add_slide', null),
		'work_slide' => get_request('work_slide', array()),
		'screen' => null
	);

	if (!empty($data['slideshowid']) && !isset($_REQUEST['form_refresh'])) {
		$slideshow = DBfetch(DBselect('SELECT s.* FROM slideshows s WHERE s.slideshowid='.$data['slideshowid']));
		$data['name'] = $slideshow['name'];
		$data['delay'] = $slideshow['delay'];

		// get slides
		$db_slides = DBselect('SELECT s.* FROM slides s WHERE s.slideshowid='.$data['slideshowid'].' ORDER BY s.step');
		while ($slide = DBfetch($db_slides)) {
			$data['slides'][$slide['step']] = array(
				'slideid' => $slide['slideid'],
				'screenid' => $slide['screenid'],
				'delay' => $slide['delay']
			);
		}
	}

	// get work slide screen name
	$data['work_slide_screen'] = '';
	if (!empty($data['work_slide']['screenid'])) {
		$screen = get_screen_by_screenid($data['work_slide']['screenid']);
		$data['work_slide_screen'] = $screen['name'];
	}

	// render view
	$slideshowView = new CView('configuration.slideconf.edit', $data);
	$slideshowView->render();
	$slideshowView->show();
}
else {
	$data['slides'] = DBfetchArray(DBselect(
		'SELECT s.slideshowid,s.name,s.delay,COUNT(sl.slideshowid) AS cnt'.
		' FROM slideshows s'.
			' LEFT JOIN slides sl ON sl.slideshowid=s.slideshowid'.
		' WHERE '.DBin_node('s.slideshowid').
		' GROUP BY s.slideshowid,s.name,s.delay'
	));
	order_result($data['slides'], getPageSortField('name'), getPageSortOrder());

	$data['paging'] = getPagingLine($data['slides']);

	// render view
	$slideshowView = new CView('configuration.slideconf.list', $data);
	$slideshowView->render();
	$slideshowView->show();
}

require_once('include/page_footer.php');
?>
