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
require_once dirname(__FILE__).'/include/maps.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of network maps');
$page['file'] = 'sysmap.php';
$page['hist_arg'] = array('sysmapid');
$page['scripts'] = array('class.cmap.js', 'class.cviewswitcher.js');
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
	'favid' =>		array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favcnt' =>		array(T_ZBX_INT, O_OPT, null,	null,		null)
);
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	$json = new CJSON();
	if ($_REQUEST['favobj'] == 'sysmap' && $_REQUEST['action'] == 'save') {
		$sysmapid = get_request('sysmapid', 0);

		@ob_start();

		try {
			DBstart();

			$sysmap = API::Map()->get(array(
				'sysmapids' => $sysmapid,
				'editable' => true,
				'output' => API_OUTPUT_SHORTEN
			));
			$sysmap = reset($sysmap);
			if ($sysmap === false) {
				throw new Exception(_('Access denied!')."\n\r");
			}

			$sysmapUpdate = $json->decode($_REQUEST['sysmap'], true);
			$sysmapUpdate['sysmapid'] = $sysmapid;

			$result = API::Map()->update($sysmapUpdate);

			if ($result !== false) {
				echo 'if (Confirm("'._('Map is saved! Return?').'")) { location.href = "sysmaps.php"; }';
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
		exit();

	}
}

if (PAGE_TYPE_HTML != $page['type']) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Display
 */
// include JS + templates
include('include/views/js/configuration.sysmaps.js.php');

show_table_header(_('CONFIGURATION OF NETWORK MAPS'));

if (isset($_REQUEST['sysmapid'])) {
	$maps = API::Map()->get(array(
		'sysmapids' => $_REQUEST['sysmapid'],
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectSelements' => API_OUTPUT_EXTEND,
		'selectLinks' => API_OUTPUT_EXTEND,
		'preservekeys' => true
	));

	if (empty($maps)) {
		access_deny();
	}
	else {
		$sysmap = reset($maps);
	}
}

echo SBR;

// elements
$el_add = new CIcon(_('Add element'), 'iconplus');
$el_add->setAttribute('id', 'selementAdd');
$el_rmv = new CIcon(_('Remove element'), 'iconminus');
$el_rmv->setAttribute('id', 'selementRemove');

// connectors
$cn_add = new CIcon(_('Add link'), 'iconplus');
$cn_add->setAttribute('id', 'linkAdd');
$cn_rmv = new CIcon(_('Remove link'), 'iconminus');
$cn_rmv->setAttribute('id', 'linkRemove');

$expandMacros = new CSpan(($sysmap['expand_macros'] == SYSMAP_EXPAND_MACROS_ON) ? _('On') : _('Off'), 'whitelink');
$expandMacros->setAttribute('id', 'expand_macros');

$gridShow = new CSpan(($sysmap['grid_show'] == SYSMAP_GRID_SHOW_ON) ? _('Shown') : _('Hidden'), 'whitelink');
$gridShow->setAttribute('id', 'gridshow');

$gridAutoAlign = new CSpan(($sysmap['grid_align'] == SYSMAP_GRID_ALIGN_ON) ? _('On') : _('Off'), 'whitelink');
$gridAutoAlign->setAttribute('id', 'gridautoalign');

$possibleGridSizes = array(
	20 => '20x20',
	40 => '40x40',
	50 => '50x50',
	75 => '75x75',
	100 => '100x100'
);
$gridSize = new CComboBox('gridsize', $sysmap['grid_size']);
$gridSize->addItems($possibleGridSizes);

$gridAlignAll = new CSubmit('gridalignall', _('Align icons'));
$gridAlignAll->setAttribute('id', 'gridalignall');

$gridForm = new CDiv(array($gridSize, $gridAlignAll));
$gridForm->setAttribute('id', 'gridalignblock');

$saveButton = new CSubmit('save', _('Save'));
$saveButton->setAttribute('id', 'sysmap_save');

$menuRow = array(
	_s('Map "%s"', $sysmap['name']),
	SPACE.SPACE,
	_('Icon'), SPACE, $el_add, SPACE, $el_rmv,
	SPACE.SPACE,
	_('Link'), SPACE, $cn_add, SPACE, $cn_rmv,
	SPACE.SPACE,
	_('Expand macros').' [ ', $expandMacros, ' ]',
	SPACE.SPACE,
	_('Grid').SPACE.'[', $gridShow, '|', $gridAutoAlign, ']',
	SPACE,
	$gridForm,
	SPACE.'|'.SPACE,
	$saveButton
);

$elcn_tab = new CTable(null, 'textwhite');
$elcn_tab->addRow($menuRow);

show_table_header($elcn_tab);

$sysmap_img = new CImg('images/general/tree/zero.gif', 'Sysmap');
$sysmap_img->setAttribute('id', 'sysmap_img', $sysmap['width'], $sysmap['height']);

$table = new CTable();
$table->addRow($sysmap_img);
$table->Show();

$container = new CDiv();
$container->setAttribute('id', 'sysmap_cnt');
$container->Show();

insert_show_color_picker_javascript();

add_elementNames($sysmap['selements']);

foreach ($sysmap['links'] as &$link) {
	foreach ($link['linktriggers'] as $lnum => $linktrigger) {
		$dbTrigger = API::Trigger()->get(array(
			'triggerids' => $linktrigger['triggerid'],
			'output' => array('description', 'expression'),
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'expandDescription' => true
		));
		$dbTrigger = reset($dbTrigger);
		$host = reset($dbTrigger['hosts']);

		$link['linktriggers'][$lnum]['desc_exp'] = $host['name'].':'.$dbTrigger['description'];
	}
	order_result($link['linktriggers'], 'desc_exp');
}
unset($link);

if ($sysmap['iconmapid']) {
	$iconMaps = API::IconMap()->get(array(
		'iconmapids' => $sysmap['iconmapid'],
		'output' => array('default_iconid'),
		'preservekeys' => true
	));
	$iconMap = reset($iconMaps);
	$defaultAutoIconId = $iconMap['default_iconid'];
}
else {
	$defaultAutoIconId = null;
}

$iconList = array();
$result = DBselect('SELECT i.imageid,i.name FROM images i WHERE i.imagetype='.IMAGE_TYPE_ICON.' AND '.DBin_node('i.imageid'));
while ($row = DBfetch($result)) {
	$iconList[] = array('imageid' => $row['imageid'], 'name' => $row['name']);
}
order_result($iconList, 'name');

zbx_add_post_js('ZABBIX.apps.map.run("sysmap_cnt", '.CJs::encodeJson(array(
	'sysmap' => $sysmap,
	'iconList' => $iconList,
	'defaultAutoIconId' => $defaultAutoIconId
), true).');');

require_once dirname(__FILE__).'/include/page_footer.php';
