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


require_once dirname(__FILE__).'/events.inc.php';
require_once dirname(__FILE__).'/actions.inc.php';
require_once dirname(__FILE__).'/js.inc.php';

function screen_resources($resource = null) {
	$resources = array(
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
		SCREEN_RESOURCE_URL => _('URL')
	);

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

function get_screen_by_screenid($screenid) {
	$dbScreen = DBfetch(DBselect('SELECT s.* FROM screens s WHERE s.screenid='.zbx_dbstr($screenid)));

	return empty($dbScreen) ? false : $dbScreen;
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

	$sql = 'SELECT s.slideshowid'.
			' FROM slideshows s'.
			' WHERE s.slideshowid='.zbx_dbstr($slideshowid);

	if (DBselect($sql)) {
		$result = true;

		$screenids = array();
		$db_screens = DBselect(
			'SELECT DISTINCT s.screenid'.
			' FROM slides s'.
			' WHERE s.slideshowid='.zbx_dbstr($slideshowid)
		);
		while ($slide_data = DBfetch($db_screens)) {
			$screenids[$slide_data['screenid']] = $slide_data['screenid'];
		}

		$options = array(
			'output' => array('screenid'),
			'screenids' => $screenids
		);
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

function get_slideshow_by_slideshowid($slideshowid) {
	return DBfetch(DBselect('SELECT s.* FROM slideshows s WHERE s.slideshowid='.zbx_dbstr($slideshowid)));
}

function add_slideshow($name, $delay, $slides) {
	// validate slides
	if (empty($slides)) {
		error(_('Slide show must contain slides.'));
		return false;
	}

	// validate screens
	$screenids = zbx_objectValues($slides, 'screenid');
	$screens = API::Screen()->get(array(
		'screenids' => $screenids,
		'output' => array('screenid')
	));
	$screens = ZBX_toHash($screens, 'screenid');
	foreach ($screenids as $screenid) {
		if (!isset($screens[$screenid])) {
			error(_('Incorrect screen provided for slide show.'));
			return false;
		}
	}

	// validate slide name
	$db_slideshow = DBfetch(DBselect(
		'SELECT s.slideshowid FROM slideshows s WHERE s.name='.zbx_dbstr($name)
	));

	if ($db_slideshow) {
		error(_s('Slide show "%s" already exists.', $name));

		return false;
	}

	$slideshowid = get_dbid('slideshows', 'slideshowid');
	$result = DBexecute(
		'INSERT INTO slideshows (slideshowid,name,delay)'.
		' VALUES ('.zbx_dbstr($slideshowid).','.zbx_dbstr($name).','.zbx_dbstr($delay).')'
	);

	// create slides
	$i = 0;
	foreach ($slides as $slide) {
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

function update_slideshow($slideshowid, $name, $delay, $slides) {
	// validate slides
	if (empty($slides)) {
		error(_('Slide show must contain slides.'));
		return false;
	}

	// validate screens
	$screenids = zbx_objectValues($slides, 'screenid');
	$screens = API::Screen()->get(array(
		'screenids' => $screenids,
		'output' => array('screenid')
	));
	$screens = ZBX_toHash($screens, 'screenid');
	foreach ($screenids as $screenid) {
		if (!isset($screens[$screenid])) {
			error(_('Incorrect screen provided for slide show.'));
			return false;
		}
	}

	// validate slide name
	$dbSlideshow = DBfetch(DBselect(
		'SELECT s.slideshowid'.
		' FROM slideshows s'.
		' WHERE s.name='.zbx_dbstr($name).
			' AND s.slideshowid<>'.zbx_dbstr($slideshowid)
	));
	if ($dbSlideshow) {
		error(_s('Slide show "%1$s" already exists.', $name));
		return false;
	}

	$dbSlideshow = DBfetchArray(DBselect('SELECT * FROM slideshows WHERE slideshowid='.zbx_dbstr($slideshowid)));
	$dbSlideshow = $dbSlideshow[0];
	$changed = false;
	$slideshow = array('name' => $name, 'delay' => $delay);

	foreach ($slideshow as $key => $val) {
		if ((string) $val !== (string) $dbSlideshow[$key]) {
			$changed = true;
			break;
		}
	}

	if ($changed) {
		$result = DBexecute(
			'UPDATE slideshows'.
			' SET name='.zbx_dbstr($name).',delay='.zbx_dbstr($delay).
			' WHERE slideshowid='.zbx_dbstr($slideshowid)
		);

		if (!$result) {
			return false;
		}
	}

	// get slides
	$db_slides = DBfetchArrayAssoc(DBselect('SELECT s.* FROM slides s WHERE s.slideshowid='.zbx_dbstr($slideshowid)), 'slideid');

	$slidesToDel = zbx_objectValues($db_slides, 'slideid');
	$slidesToDel = zbx_toHash($slidesToDel);
	$step = 0;
	foreach ($slides as $slide) {
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
				' VALUES ('.zbx_dbstr($slideid).','.zbx_dbstr($slideshowid).','.zbx_dbstr($slide['screenid']).','.zbx_dbstr($step).','.zbx_dbstr($slide['delay']).')'
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
	$result = DBexecute('DELETE FROM slideshows where slideshowid='.zbx_dbstr($slideshowid));
	$result &= DBexecute('DELETE FROM slides where slideshowid='.zbx_dbstr($slideshowid));
	$result &= DBexecute('DELETE FROM profiles WHERE idx=\'web.favorite.screenids\' AND source=\'slideshowid\' AND value_id='.zbx_dbstr($slideshowid));

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
