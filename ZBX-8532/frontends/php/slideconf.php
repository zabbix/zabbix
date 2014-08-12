<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
validate_sort_and_sortorder('name', ZBX_SORT_UP, array('cnt', 'name', 'delay'));

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

	$dbSlideshow = get_slideshow_by_slideshowid(getRequest('slideshowid'));

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

$_REQUEST['go'] = getRequest('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['slideshowid'])) {
	unset($_REQUEST['slideshowid']);
	$_REQUEST['form'] = 'clone';
}
elseif (isset($_REQUEST['save'])) {
	DBstart();

	if (isset($_REQUEST['slideshowid'])) {
		$result = update_slideshow($_REQUEST['slideshowid'], $_REQUEST['name'], $_REQUEST['delay'], getRequest('slides', array()));

		$messageSuccess = _('Slide show updated');
		$messageFailed = _('Cannot update slide show');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$result = add_slideshow($_REQUEST['name'], $_REQUEST['delay'], getRequest('slides', array()));

		$messageSuccess = _('Slide show added');
		$messageFailed = _('Cannot add slide show');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_SLIDESHOW, ' Name "'.$_REQUEST['name'].'" ');
		unset($_REQUEST['form'], $_REQUEST['slideshowid']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['slideshowid'])) {
	DBstart();

	$result = delete_slideshow($_REQUEST['slideshowid']);

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SLIDESHOW, ' Name "'.$dbSlideshow['name'].'" ');
	}
	unset($_REQUEST['slideshowid'], $_REQUEST['form']);

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Slide show deleted'), _('Cannot delete slide show'));
}
elseif ($_REQUEST['go'] == 'delete') {
	$result = true;

	$shows = getRequest('shows', array());
	DBstart();

	foreach ($shows as $showid) {
		$result &= delete_slideshow($showid);
		if (!$result) {
			break;
		}
	}

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form']);
		uncheckTableRows();
	}
	show_messages($result, _('Slide show deleted'), _('Cannot delete slide show'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => getRequest('form'),
		'form_refresh' => getRequest('form_refresh', 0),
		'slideshowid' => getRequest('slideshowid'),
		'name' => getRequest('name', ''),
		'delay' => getRequest('delay', ZBX_ITEM_DELAY_DEFAULT),
		'slides' => getRequest('slides', array())
	);

	if (isset($data['slideshowid']) && !isset($_REQUEST['form_refresh'])) {
		$data['name'] = $dbSlideshow['name'];
		$data['delay'] = $dbSlideshow['delay'];

		// get slides
		$data['slides'] = DBfetchArray(DBselect(
				'SELECT s.slideid, s.screenid, s.delay'.
				' FROM slides s'.
				' WHERE s.slideshowid='.zbx_dbstr($data['slideshowid']).
				' ORDER BY s.step'
		));
	}

	// get slides without delay
	$data['slides_without_delay'] = $data['slides'];
	foreach ($data['slides_without_delay'] as &$slide) {
		unset($slide['delay']);
	}
	unset($slide);

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
			' GROUP BY s.slideshowid,s.name,s.delay'
	));

	foreach ($data['slides'] as $key => $slide) {
		if (!slideshow_accessible($slide['slideshowid'], PERM_READ_WRITE)) {
			unset($data['slides'][$key]);
		}
	}

	order_result($data['slides'], getPageSortField('name'), getPageSortOrder());

	$data['paging'] = getPagingLine($data['slides']);

	// render view
	$slideshowView = new CView('configuration.slideconf.list', $data);
	$slideshowView->render();
	$slideshowView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
