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
require_once dirname(__FILE__).'/include/maps.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of network maps');
$page['file'] = 'sysmap.php';
$page['hist_arg'] = array('sysmapid');
$page['scripts'] = array('class.cmap.js', 'class.cviewswitcher.js', 'multiselect.js');
$page['type'] = detect_page_type();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'sysmapid' =>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null),
	'selementid' =>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
	'sysmap' =>		array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({save})'),
	'selements' =>	array(T_ZBX_STR, O_OPT, P_SYS,	DB_ID,		null),
	'links' =>		array(T_ZBX_STR, O_OPT, P_SYS,	DB_ID,		null),
	// actions
	'action' =>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY,	null),
	'save' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'delete' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null),
	'cancel' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form' =>		array(T_ZBX_STR, O_OPT, P_SYS,	null,		null),
	'form_refresh' => array(T_ZBX_INT, O_OPT, null,	null,		null),
	// ajax
	'favobj' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favid' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null)
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	$json = new CJSON();

	if ($_REQUEST['favobj'] == 'sysmap' && $_REQUEST['action'] == 'save') {
		$sysmapid = getRequest('sysmapid', 0);

		@ob_start();

		try {
			DBstart();

			$sysmap = API::Map()->get(array(
				'sysmapids' => $sysmapid,
				'editable' => true,
				'output' => array('sysmapid')
			));
			$sysmap = reset($sysmap);

			if ($sysmap === false) {
				throw new Exception(_('Access denied!')."\n\r");
			}

			$sysmapUpdate = $json->decode($_REQUEST['sysmap'], true);
			$sysmapUpdate['sysmapid'] = $sysmapid;

			$result = API::Map()->update($sysmapUpdate);

			if ($result !== false) {
				echo 'if (confirm('.CJs::encodeJson(_('Map is saved! Return?')).')) { location.href = "sysmaps.php"; }';
			}
			else {
				throw new Exception(_('Map save operation failed.')."\n\r");
			}

			DBend(true);
		}
		catch (Exception $e) {
			DBend(false);
			$msg = array($e->getMessage());

			foreach (clear_messages() as $errMsg) {
				$msg[] = $errMsg['type'].': '.$errMsg['message'];
			}

			ob_clean();

			echo 'alert('.zbx_jsvalue(implode("\n\r", $msg)).');';
		}

		@ob_flush();
		exit;

	}
}

if (PAGE_TYPE_HTML != $page['type']) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Permissions
 */
if (isset($_REQUEST['sysmapid'])) {
	$sysmap = API::Map()->get(array(
		'sysmapids' => $_REQUEST['sysmapid'],
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectSelements' => API_OUTPUT_EXTEND,
		'selectLinks' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));
	if (empty($sysmap)) {
		access_deny();
	}
	else {
		$sysmap = reset($sysmap);
	}
}

/*
 * Display
 */
$data = array(
	'sysmap' => $sysmap,
	'iconList' => array(),
	'defaultAutoIconId' => null,
	'defaultIconId' => null,
	'defaultIconName' => null
);

// get selements
add_elementNames($data['sysmap']['selements']);

$data['sysmap']['selements'] = zbx_toHash($data['sysmap']['selements'], 'selementid');
$data['sysmap']['links'] = zbx_toHash($data['sysmap']['links'], 'linkid');

// get links
foreach ($data['sysmap']['links'] as &$link) {
	foreach ($link['linktriggers'] as $lnum => $linkTrigger) {
		$dbTrigger = API::Trigger()->get(array(
			'triggerids' => $linkTrigger['triggerid'],
			'output' => array('description', 'expression'),
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'expandDescription' => true
		));
		$dbTrigger = reset($dbTrigger);
		$host = reset($dbTrigger['hosts']);

		$link['linktriggers'][$lnum]['desc_exp'] = $host['name'].NAME_DELIMITER.$dbTrigger['description'];
	}
	order_result($link['linktriggers'], 'desc_exp');
}
unset($link);

// get iconmapping
if ($data['sysmap']['iconmapid']) {
	$iconMap = API::IconMap()->get(array(
		'iconmapids' => $data['sysmap']['iconmapid'],
		'output' => array('default_iconid'),
		'preservekeys' => true
	));
	$iconMap = reset($iconMap);
	$data['defaultAutoIconId'] = $iconMap['default_iconid'];
}

// get icon list
$icons = DBselect(
	'SELECT i.imageid,i.name FROM images i WHERE i.imagetype='.IMAGE_TYPE_ICON
);

while ($icon = DBfetch($icons)) {
	$data['iconList'][] = array(
		'imageid' => $icon['imageid'],
		'name' => $icon['name']
	);

	if ($icon['name'] == MAP_DEFAULT_ICON || !isset($data['defaultIconId'])) {
		$data['defaultIconId'] = $icon['imageid'];
		$data['defaultIconName'] = $icon['name'];
	}
}
if ($data['iconList']) {
	CArrayHelper::sort($data['iconList'], array('name'));
	$data['iconList'] = array_values($data['iconList']);
}

// render view
$sysmapView = new CView('configuration.sysmap.constructor', $data);
$sysmapView->render();
$sysmapView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
