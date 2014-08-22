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

$page['title'] = _('Configuration of icon mapping');
$page['file'] = 'adm.iconmapping.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'iconmapid' =>		array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	'(isset({form})&&{form}=="update")||isset({delete})'),
	'iconmap' =>		array(T_ZBX_STR, O_OPT, null,			null,	'isset({save})'),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'clone' =>			array(T_ZBX_STR, O_OPT, null,			null,	null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,			null,	null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,			null,	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['iconmapid'])) {
	$iconMap = API::IconMap()->get(array(
		'iconmapids' => getRequest('iconmapid'),
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'preservekeys' => true,
		'selectMappings' => API_OUTPUT_EXTEND,
	));
	if (empty($iconMap)) {
		access_deny();
	}
}

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	$_REQUEST['iconmap']['mappings'] = isset($_REQUEST['iconmap']['mappings'])
		? $_REQUEST['iconmap']['mappings']
		: array();

	$i = 0;
	foreach ($_REQUEST['iconmap']['mappings'] as &$mapping) {
		$mapping['sortorder'] = $i++;
	}
	unset($mapping);

	if (isset($_REQUEST['iconmapid'])) {
		$_REQUEST['iconmap']['iconmapid'] = $_REQUEST['iconmapid'];
		$result = API::IconMap()->update($_REQUEST['iconmap']);
		$msgOk = _('Icon map updated');
		$msgErr = _('Cannot update icon map');
	}
	else {
		$result = API::IconMap()->create($_REQUEST['iconmap']);
		$msgOk = _('Icon map created');
		$msgErr = _('Cannot create icon map');
	}

	show_messages($result, $msgOk, $msgErr);

	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (isset($_REQUEST['delete'])) {
	$result = API::IconMap()->delete(array(getRequest('iconmapid')));

	if ($result) {
		unset($_REQUEST['form']);
	}

	show_messages($result, _('Icon map deleted'), _('Cannot delete icon map'));
}
elseif (isset($_REQUEST['clone'])) {
	unset($_REQUEST['iconmapid']);
	$_REQUEST['form'] = 'clone';
}

/*
 * Display
 */
$generalComboBox = new CComboBox('configDropDown', 'adm.iconmapping.php', 'redirect(this.options[this.selectedIndex].value);');
$generalComboBox->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeping'),
	'adm.images.php' => _('Images'),
	'adm.iconmapping.php' => _('Icon mapping'),
	'adm.regexps.php' => _('Regular expressions'),
	'adm.macros.php' => _('Macros'),
	'adm.valuemapping.php' => _('Value mapping'),
	'adm.workingtime.php' => _('Working time'),
	'adm.triggerseverities.php' => _('Trigger severities'),
	'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
	'adm.other.php' => _('Other')
));
$iconMapForm = new CForm();
$iconMapForm->cleanItems();
$iconMapForm->addItem($generalComboBox);

if (!isset($_REQUEST['form'])) {
	$iconMapForm->addItem(new CSubmit('form', _('Create icon map')));
}

$iconMapWidget = new CWidget();
$iconMapWidget->addPageHeader(_('CONFIGURATION OF ICON MAPPING'), $iconMapForm);

$data = array(
	'form_refresh' => getRequest('form_refresh', 0),
	'iconmapid' => getRequest('iconmapid'),
	'iconList' => array(),
	'inventoryList' => array()
);

$iconList = API::Image()->get(array(
	'output' => array('imageid', 'name'),
	'filter' => array('imagetype' => IMAGE_TYPE_ICON),
	'preservekeys' => true
));
order_result($iconList, 'name');

foreach ($iconList as $icon) {
	$data['iconList'][$icon['imageid']] = $icon['name'];
}

$inventoryFields = getHostInventories();
foreach ($inventoryFields as $field) {
	$data['inventoryList'][$field['nr']] = $field['title'];
}

if (isset($_REQUEST['form'])) {
	if ($data['form_refresh'] || ($_REQUEST['form'] === 'clone')) {
		$data['iconmap'] = getRequest('iconmap');
	}
	elseif (isset($_REQUEST['iconmapid'])) {
		$data['iconmap'] = reset($iconMap);
	}
	else {
		$firstIcon = reset($iconList);

		$data['iconmap'] = array(
			'name' => '',
			'default_iconid' => $firstIcon['imageid'],
			'mappings' => array()
		);
	}

	$iconMapView = new CView('administration.general.iconmap.edit', $data);
}
else {
	$iconMapWidget->addHeader(_('Icon mapping'));

	$data['iconmaps'] = API::IconMap()->get(array(
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'preservekeys' => true,
		'selectMappings' => API_OUTPUT_EXTEND
	));
	order_result($data['iconmaps'], 'name');

	$iconMapView = new CView('administration.general.iconmap.list', $data);
}

$iconMapWidget->addItem($iconMapView->render());
$iconMapWidget->show();

require_once dirname(__FILE__).'/include/page_footer.php';
