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

require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Configuration of icon mapping');
$page['file'] = 'adm.iconmapping.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'iconmapid' =>		[T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	'(isset({form}) && {form} == "update") || isset({delete})'],
	'iconmap' =>		[T_ZBX_STR, O_OPT, null,			null,	'isset({add}) || isset({update})'],
	'add' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'delete' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null],
	'clone' =>			[T_ZBX_STR, O_OPT, null,			null,	null],
	'form' =>			[T_ZBX_STR, O_OPT, P_SYS,			null,	null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,			null,	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (hasRequest('iconmapid')) {
	$iconMap = API::IconMap()->get([
		'output' => ['iconmapid', 'name', 'default_iconid'],
		'selectMappings' => ['inventory_link', 'expression', 'iconid', 'sortorder'],
		'iconmapids' => getRequest('iconmapid'),
		'editable' => true
	]);
	if (!$iconMap) {
		access_deny();
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$_REQUEST['iconmap']['mappings'] = isset($_REQUEST['iconmap']['mappings'])
		? $_REQUEST['iconmap']['mappings']
		: [];

	if (hasRequest('update')) {
		$_REQUEST['iconmap']['iconmapid'] = $_REQUEST['iconmapid'];
		$result = (bool) API::IconMap()->update($_REQUEST['iconmap']);
		show_messages($result, _('Icon map updated'), _('Cannot update icon map'));
	}
	else {
		$result = (bool) API::IconMap()->create($_REQUEST['iconmap']);
		show_messages($result, _('Icon map created'), _('Cannot create icon map'));
	}

	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (hasRequest('delete')) {
	$result = (bool) API::IconMap()->delete([getRequest('iconmapid')]);

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
$data = [
	'iconmapid' => getRequest('iconmapid'),
	'iconList' => [],
	'inventoryList' => []
];

$iconList = API::Image()->get([
	'output' => ['imageid', 'name'],
	'filter' => ['imagetype' => IMAGE_TYPE_ICON],
	'preservekeys' => true
]);
order_result($iconList, 'name');

foreach ($iconList as $icon) {
	$data['iconList'][$icon['imageid']] = $icon['name'];
}

$inventoryFields = getHostInventories();
foreach ($inventoryFields as $field) {
	$data['inventoryList'][$field['nr']] = $field['title'];
}

if (isset($_REQUEST['form'])) {
	if (hasRequest('form_refresh') || ($_REQUEST['form'] === 'clone')) {
		$data['iconmap'] = getRequest('iconmap');
	}
	elseif (isset($_REQUEST['iconmapid'])) {
		$data['iconmap'] = reset($iconMap);
	}
	else {
		$firstIcon = reset($iconList);

		$data['iconmap'] = [
			'name' => '',
			'default_iconid' => $firstIcon['imageid'],
			'mappings' => []
		];
	}

	$view = new CView('administration.general.iconmap.edit', $data);
}
else {
	$data['iconmaps'] = API::IconMap()->get([
		'output' => API_OUTPUT_EXTEND,
		'editable' => true,
		'preservekeys' => true,
		'selectMappings' => API_OUTPUT_EXTEND
	]);
	order_result($data['iconmaps'], 'name');

	$view = new CView('administration.general.iconmap.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
