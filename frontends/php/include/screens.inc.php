<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/events.inc.php';
require_once dirname(__FILE__).'/actions.inc.php';
require_once dirname(__FILE__).'/js.inc.php';

function screen_resources($resource = null) {
	$resources = [
		SCREEN_RESOURCE_CLOCK => _('Clock'),
		SCREEN_RESOURCE_DATA_OVERVIEW => _('Data overview'),
		SCREEN_RESOURCE_GRAPH => _('Graph'),
		SCREEN_RESOURCE_ACTIONS => _('Action log'),
		SCREEN_RESOURCE_EVENTS => _('History of events'),
		SCREEN_RESOURCE_HOSTS_INFO => _('Hosts info'),
		SCREEN_RESOURCE_MAP => _('Map'),
		SCREEN_RESOURCE_PLAIN_TEXT => _('Plain text'),
		SCREEN_RESOURCE_SCREEN => _('Screen'),
		SCREEN_RESOURCE_SERVER_INFO => _('Server info'),
		SCREEN_RESOURCE_SIMPLE_GRAPH => _('Simple graph'),
		SCREEN_RESOURCE_HOSTGROUP_TRIGGERS => _('Host group issues'),
		SCREEN_RESOURCE_HOST_TRIGGERS => _('Host issues'),
		SCREEN_RESOURCE_SYSTEM_STATUS => _('System status'),
		SCREEN_RESOURCE_TRIGGERS_INFO => _('Triggers info'),
		SCREEN_RESOURCE_TRIGGERS_OVERVIEW => _('Triggers overview'),
		SCREEN_RESOURCE_URL => _('URL'),
		SCREEN_RESOURCE_LLD_GRAPH => _('Graph prototype'),
		SCREEN_RESOURCE_LLD_SIMPLE_GRAPH => _('Simple graph prototype')
	];

	if (is_null($resource)) {
		natsort($resources);
		return $resources;
	}
	elseif (isset($resources[$resource])) {
		return $resources[$resource];
	}
	else {
		return _('Unknown');
	}
}

function check_screen_recursion($mother_screenid, $child_screenid) {
	if (bccomp($mother_screenid , $child_screenid) == 0) {
		return true;
	}

	$db_scr_items = DBselect(
		'SELECT si.resourceid'.
		' FROM screens_items si'.
		' WHERE si.screenid='.zbx_dbstr($child_screenid).
		' AND si.resourcetype='.SCREEN_RESOURCE_SCREEN
	);
	while ($scr_item = DBfetch($db_scr_items)) {
		if (check_screen_recursion($mother_screenid, $scr_item['resourceid'])) {
			return true;
		}
	}

	return false;
}

/**
 * Add screen row or column
 *
 * @param array $screen
 * @param string $message
 * @param string $group_type with value 'add_row' or 'add_col'
 */
function addScreenCellGroup($screen, $message, $group_type) {
	xdebug_start_trace();
	$add_num = getRequest($group_type, 0);
	switch ($group_type) {
		case 'add_row':
			$size = 'vsize';
			$axis = 'y';
			break;
		case 'add_col':
			$size = 'hsize';
			$axis = 'x';
			break;
		default:
			return;
	}
	DBstart();
	$result = API::Screen()->update([
		'screenid' => $screen['screenid'],
		$size => $screen[$size]+1,
	]);

	if ($add_num < $screen[$size]) {
		$screen_items = $screen['screenitems'];

		usort($screen_items, function($a, $b) use ($axis) {
			return bccomp($b[$axis], $a[$axis]);
		});

		foreach ($screen_items as $item) {
			if ($item[$axis] >= $add_num) {
				$item[$axis] = $item[$axis]+1;
				$result &= API::ScreenItem()->update($item);
			}
		}
	}

	if ($result) {
		add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'],
			$message
		);
	}
	DBend($result);
	xdebug_stop_trace();
}

/**
 * Remove screen row or column.
 *
 * @param array $screen
 * @param string $message
 * @param string $group_type with value 'add_row' or 'add_col'
 */
function removeScreenCellGroup($screen, $message, $group_type) {
	$rmv_num = getRequest($group_type, 0);
	switch ($group_type) {
		case 'rmv_row':
			$size = 'vsize';
			$axis = 'y';
			break;
		case 'rmv_col':
			$size = 'hsize';
			$axis = 'x';
			break;
		default:
			return;
	}

	if ($screen[$size] == 1) {
		error(_('Screen should contain at least one row and column.'));
		show_error_message(_('Impossible to remove last row and column.'));
	}
	elseif ($rmv_num < $screen[$size]) {
		DBstart();
		$result = true;
		$screen_items = $screen['screenitems'];

		usort($screen_items, function($a, $b) use ($axis) {
			return bccomp($a[$axis], $b[$axis]);
		});

		foreach ($screen_items as $item) {
			if ($item[$axis] > $rmv_num) {
				$item[$axis] = $item[$axis]-1;
				$result &= API::ScreenItem()->update($item);
			}
			elseif ($item[$axis] == $rmv_num) {
				$result &= API::ScreenItem()->delete([$item['screenitemid']]);
			}
		}

		$result &= API::Screen()->update([
			'screenid' => $screen['screenid'],
			$size => $screen[$size]-1,
		]);

		if ($result) {
			add_audit_details(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'],
				$message
			);
		}
		DBend($result);
	}
}

function getSlideshowScreens($slideshowId, $step) {
	$dbSlides = DBfetch(DBselect(
		'SELECT MIN(s.step) AS min_step,MAX(s.step) AS max_step'.
		' FROM slides s'.
		' WHERE s.slideshowid='.zbx_dbstr($slideshowId)
	));

	if (!$dbSlides || $dbSlides['min_step'] === null) {
		return false;
	}

	$step = $step % ($dbSlides['max_step'] + 1);

	$currentStep = (!$step || $step < $dbSlides['min_step'] || $step > $dbSlides['max_step'])
		? $dbSlides['min_step'] : $step;

	return DBfetch(DBselect(
		'SELECT sl.*'.
		' FROM slides sl,slideshows ss'.
		' WHERE ss.slideshowid='.zbx_dbstr($slideshowId).
			' AND sl.slideshowid=ss.slideshowid'.
			' AND sl.step='.zbx_dbstr($currentStep)
	));
}

function slideshow_accessible($slideshowid, $perm) {
	$result = false;

	if (get_slideshow_by_slideshowid($slideshowid, PERM_READ)) {
		$result = true;

		$screenids = [];
		$db_screens = DBselect(
			'SELECT DISTINCT s.screenid'.
			' FROM slides s'.
			' WHERE s.slideshowid='.zbx_dbstr($slideshowid)
		);
		while ($slide_data = DBfetch($db_screens)) {
			$screenids[$slide_data['screenid']] = $slide_data['screenid'];
		}

		$options = [
			'output' => ['screenid'],
			'screenids' => $screenids
		];
		if ($perm == PERM_READ_WRITE) {
			$options['editable'] = true;
		}
		$screens = API::Screen()->get($options);
		$screens = zbx_toHash($screens, 'screenid');

		foreach ($screenids as $screenid) {
			if (!isset($screens[$screenid])) {
				return false;
			}
		}
	}

	return $result;
}

function get_slideshow_by_slideshowid($slideshowid, $permission) {
	$user_data = CWebUser::$data;

	$condition = '';
	if ($user_data['type'] != USER_TYPE_SUPER_ADMIN && $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
		$public_slideshows = '';

		if ($permission == PERM_READ) {
			$public_slideshows = ' OR s.private='.PUBLIC_SHARING;
		}

		$user_groups = getUserGroupsByUserId($user_data['userid']);

		$condition = ' AND (EXISTS ('.
				'SELECT NULL'.
				' FROM slideshow_user su'.
				' WHERE s.slideshowid=su.slideshowid'.
					' AND su.userid='.$user_data['userid'].
					' AND su.permission>='.$permission.
			')'.
			' OR EXISTS ('.
				'SELECT NULL'.
				' FROM slideshow_usrgrp sg'.
				' WHERE s.slideshowid=sg.slideshowid'.
					' AND '.dbConditionInt('sg.usrgrpid', $user_groups).
					' AND sg.permission>='.$permission.
			')'.
			' OR s.userid='.$user_data['userid'].
			$public_slideshows.
		')';
	}

	return DBfetch(DBselect(
		'SELECT s.* FROM slideshows s WHERE s.slideshowid='.zbx_dbstr($slideshowid).$condition
	));
}

function add_slideshow($data) {
	$user_data = CWebUser::$data;

	// Validate slides.
	if (empty($data['slides'])) {
		error(_('Slide show must contain slides.'));

		return false;
	}

	// Validate screens.
	$screenids = zbx_objectValues($data['slides'], 'screenid');

	$screens = API::Screen()->get([
		'output' => ['screenid'],
		'screenids' => $screenids,
		'preservekeys' => true
	]);

	foreach ($screenids as $screenid) {
		if (!array_key_exists($screenid, $screens)) {
			error(_('Incorrect screen provided for slide show.'));

			return false;
		}
	}

	// Validate slide name.
	$db_slideshow = DBfetch(DBselect(
		'SELECT s.slideshowid FROM slideshows s WHERE s.name='.zbx_dbstr($data['name'])
	));

	if ($db_slideshow) {
		error(_s('Slide show "%s" already exists.', $data['name']));

		return false;
	}

	// Validate slide show owner.
	if ($data['userid'] === null) {
		error(_('Slide show owner cannot be empty.'));

		return false;
	}
	elseif ($data['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
			&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
		error(_('Only administrators can set slide show owner.'));

		return false;
	}

	$slideshowid = get_dbid('slideshows', 'slideshowid');
	$result = DBexecute(
		'INSERT INTO slideshows (slideshowid,name,delay,userid,private)'.
		' VALUES ('.zbx_dbstr($slideshowid).','.zbx_dbstr($data['name']).','.zbx_dbstr($data['delay']).','.
			zbx_dbstr($data['userid']).','.zbx_dbstr($data['private']).')'
	);


	// User shares.
	$shared_users = [];

	foreach ($data['users'] as $user) {
		if ($data['private'] == PUBLIC_SHARING && $user['permission'] == PERM_READ) {
			error(_s('Slide show "%1$s" is public and read-only sharing is disallowed.', $data['name']));

			return false;
		}

		$shared_users[] = [
			'slideshowid' => $slideshowid,
			'userid' => $user['userid'],
			'permission' => $user['permission']
		];
	}

	DB::insert('slideshow_user', $shared_users);

	// User group shares.
	$shared_user_groups = [];

	foreach ($data['userGroups'] as $user_group) {
		if ($data['private'] == PUBLIC_SHARING && $user_group['permission'] == PERM_READ) {
			error(_s('Slide show "%1$s" is public and read-only sharing is disallowed.', $data['name']));

			return false;
		}

		$shared_user_groups[] = [
			'slideshowid' => $slideshowid,
			'usrgrpid' => $user_group['usrgrpid'],
			'permission' => $user_group['permission']
		];
	}

	DB::insert('slideshow_usrgrp', $shared_user_groups);

	// create slides
	$i = 0;
	foreach ($data['slides'] as $slide) {
		$slideid = get_dbid('slides', 'slideid');

		// set default delay
		if (empty($slide['delay'])) {
			$slide['delay'] = 0;
		}

		$result = DBexecute(
			'INSERT INTO slides (slideid,slideshowid,screenid,step,delay)'.
			' VALUES ('.zbx_dbstr($slideid).','.zbx_dbstr($slideshowid).','.zbx_dbstr($slide['screenid']).','.($i++).','.zbx_dbstr($slide['delay']).')'
		);

		if (!$result) {
			return false;
		}
	}

	return $slideshowid;
}

function update_slideshow($data) {
	$user_data = CWebUser::$data;

	// Validate slides.
	if (empty($data['slides'])) {
		error(_('Slide show must contain slides.'));

		return false;
	}

	// validate screens.
	$screenids = zbx_objectValues($data['slides'], 'screenid');

	$screens = API::Screen()->get([
		'output' => ['screenid'],
		'screenids' => $screenids,
		'preservekeys' => true
	]);

	foreach ($screenids as $screenid) {
		if (!array_key_exists($screenid, $screens)) {
			error(_('Incorrect screen provided for slide show.'));

			return false;
		}
	}

	// Validate slide name.
	$db_slideshow = DBfetch(DBselect(
		'SELECT s.slideshowid'.
		' FROM slideshows s'.
		' WHERE s.name='.zbx_dbstr($data['name']).
			' AND s.slideshowid<>'.zbx_dbstr($data['slideshowid'])
	));

	if ($db_slideshow) {
		error(_s('Slide show "%1$s" already exists.', $data['name']));

		return false;
	}

	// Validate slide show owner.
	if (array_key_exists('userid', $data)) {
		if ($data['userid'] === null || $data['userid'] === '') {
			error(_('Slide show owner cannot be empty.'));

			return false;
		}
		elseif ($data['userid'] != $user_data['userid'] && $user_data['type'] != USER_TYPE_SUPER_ADMIN
				&& $user_data['type'] != USER_TYPE_ZABBIX_ADMIN) {
			error(_('Only administrators can set slide show owner.'));

			return false;
		}
	}

	$to_update = $data;
	unset($to_update['slideshowid'], $to_update['slides'], $to_update['users'], $to_update['userGroups']);

	DB::update('slideshows', [
		'values' => $to_update,
		'where' => ['slideshowid' => $data['slideshowid']]
	]);

	// Read-only sharing validation.
	foreach ($data['users'] as $user) {
		if ($data['private'] == PUBLIC_SHARING && $user['permission'] == PERM_READ) {
			error(_s('Slide show "%1$s" is public and read-only sharing is disallowed.', $data['name']));

			return false;
		}
	}

	foreach ($data['userGroups'] as $user_group) {
		if ($data['private'] == PUBLIC_SHARING && $user_group['permission'] == PERM_READ) {
			error(_s('Slide show "%1$s" is public and read-only sharing is disallowed.', $data['name']));

			return false;
		}
	}

	$shared_userids_to_delete = [];
	$shared_users_to_update = [];
	$shared_users_to_add = [];
	$shared_user_groupids_to_delete = [];
	$shared_user_groups_to_update = [];
	$shared_user_groups_to_add = [];

	// Slide show user shares.
	$db_slideshow['users'] = DBfetchArray(DBselect(
		'SELECT s.userid,s.permission,s.slideshowuserid'.
		' FROM slideshow_user s'.
		' WHERE s.slideshowid='.zbx_dbstr(getRequest('slideshowid'))
	));

	$userids = [];
	foreach ($db_slideshow['users'] as $user) {
		$userids[] = $user['userid'];
	}

	$allowed_users = API::User()->get([
		'output' => ['userid'],
		'userids' => $userids,
		'preservekeys' => true
	]);

	foreach ($db_slideshow['users'] as $key => $user) {
		if (!array_key_exists($user['userid'], $allowed_users)) {
			unset($db_slideshow['users'][$key]);
		}
	}

	$user_shares_diff = zbx_array_diff($data['users'], $db_slideshow['users'], 'userid');

	foreach ($user_shares_diff['both'] as $update_user_share) {
		$shared_users_to_update[] = [
			'values' => $update_user_share,
			'where' => ['userid' => $update_user_share['userid'], 'slideshowid' => $data['slideshowid']]
		];
	}

	foreach ($user_shares_diff['first'] as $new_shared_user) {
		$new_shared_user['slideshowid'] = $data['slideshowid'];
		$shared_users_to_add[] = $new_shared_user;
	}

	$shared_userids_to_delete = zbx_objectValues($user_shares_diff['second'], 'slideshowuserid');

	// Slide show user group shares.
	$db_slideshow['userGroups'] = DBfetchArray(DBselect(
		'SELECT s.usrgrpid,s.permission,s.slideshowusrgrpid'.
		' FROM slideshow_usrgrp s'.
		' WHERE s.slideshowid='.zbx_dbstr(getRequest('slideshowid'))
	));

	$usrgrpids = [];
	foreach ($db_slideshow['userGroups'] as $user_group) {
		$usrgrpids[] = $user_group['usrgrpid'];
	}

	$allowed_user_groups = API::UserGroup()->get([
		'output' => ['usrgrpid'],
		'usrgrpids' => $usrgrpids,
		'preservekeys' => true
	]);

	foreach ($db_slideshow['userGroups'] as $key => $user_group) {
		if (!array_key_exists($user_group['usrgrpid'], $allowed_user_groups)) {
			unset($db_slideshow['userGroups'][$key]);
		}
	}

	$user_group_shares_diff = zbx_array_diff($data['userGroups'], $db_slideshow['userGroups'], 'usrgrpid');

	foreach ($user_group_shares_diff['both'] as $update_user_share) {
		$shared_user_groups_to_update[] = [
			'values' => $update_user_share,
			'where' => ['usrgrpid' => $update_user_share['usrgrpid'], 'slideshowid' => $data['slideshowid']]
		];
	}

	foreach ($user_group_shares_diff['first'] as $new_shared_user_group) {
		$new_shared_user_group['slideshowid'] = $data['slideshowid'];
		$shared_user_groups_to_add[] = $new_shared_user_group;
	}

	$shared_user_groupids_to_delete = zbx_objectValues($user_group_shares_diff['second'], 'slideshowusrgrpid');

	// User shares.
	DB::insert('slideshow_user', $shared_users_to_add);
	DB::update('slideshow_user', $shared_users_to_update);

	if ($shared_userids_to_delete) {
		DB::delete('slideshow_user', ['slideshowuserid' => $shared_userids_to_delete]);
	}

	// User group shares.
	DB::insert('slideshow_usrgrp', $shared_user_groups_to_add);
	DB::update('slideshow_usrgrp', $shared_user_groups_to_update);

	if ($shared_user_groupids_to_delete) {
		DB::delete('slideshow_usrgrp', ['slideshowusrgrpid' => $shared_user_groupids_to_delete]);
	}

	// get slides
	$db_slides = DBfetchArrayAssoc(DBselect('SELECT s.* FROM slides s WHERE s.slideshowid='.zbx_dbstr($data['slideshowid'])), 'slideid');

	$slidesToDel = zbx_objectValues($db_slides, 'slideid');
	$slidesToDel = zbx_toHash($slidesToDel);
	$step = 0;
	foreach ($data['slides'] as $slide) {
		$slide['delay'] = $slide['delay'] ? $slide['delay'] : 0;
		if (isset($db_slides[$slide['slideid']])) {
			// update slide
			if ($db_slides[$slide['slideid']]['delay'] != $slide['delay'] || $db_slides[$slide['slideid']]['step'] != $step) {
				$result = DBexecute('UPDATE slides SET step='.zbx_dbstr($step).', delay='.zbx_dbstr($slide['delay']).' WHERE slideid='.zbx_dbstr($slide['slideid']));
			}
			// do nothing with slide
			else {
				$result = true;
			}
			unset($slidesToDel[$slide['slideid']]);
		}
		// insert slide
		else {
			$slideid = get_dbid('slides', 'slideid');
			$result = DBexecute(
				'INSERT INTO slides (slideid,slideshowid,screenid,step,delay)'.
				' VALUES ('.zbx_dbstr($slideid).','.zbx_dbstr($data['slideshowid']).','.zbx_dbstr($slide['screenid']).','.zbx_dbstr($step).','.zbx_dbstr($slide['delay']).')'
			);
		}
		$step ++;
		if (!$result) {
			return false;
		}
	}

	// delete unnecessary slides
	if (!empty($slidesToDel)) {
		DBexecute('DELETE FROM slides WHERE slideid IN('.implode(',', $slidesToDel).')');
	}

	return true;
}

function delete_slideshow($slideshowid) {
	$result = false;

	if (get_slideshow_by_slideshowid($slideshowid, PERM_READ_WRITE)) {
		$result = DBexecute('DELETE FROM slideshows where slideshowid='.zbx_dbstr($slideshowid));
		$result &= DBexecute('DELETE FROM slides where slideshowid='.zbx_dbstr($slideshowid));
		$result &= DBexecute('DELETE FROM profiles WHERE idx=\'web.favorite.screenids\' AND source=\'slideshowid\' AND value_id='.zbx_dbstr($slideshowid));
	}

	return (bool) $result;
}

// check whether there are dynamic items in the screen, if so return TRUE, else FALSE
function check_dynamic_items($elid, $config = 0) {
	if ($config == 0) {
		$sql = 'SELECT si.screenitemid'.
				' FROM screens_items si'.
				' WHERE si.screenid='.zbx_dbstr($elid).
					' AND si.dynamic='.SCREEN_DYNAMIC_ITEM;
	}
	else {
		$sql = 'SELECT si.screenitemid'.
				' FROM slides s,screens_items si'.
				' WHERE s.slideshowid='.zbx_dbstr($elid).
					' AND si.screenid=s.screenid'.
					' AND si.dynamic='.SCREEN_DYNAMIC_ITEM;
	}
	if (DBfetch(DBselect($sql, 1))) {
		return true;
	}

	return false;
}

function getResourceNameByType($resourceType) {
	switch ($resourceType) {
		case SCREEN_RESOURCE_DATA_OVERVIEW:
		case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
			return _('Group');
	}

	return null;
}
