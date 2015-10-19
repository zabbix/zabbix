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

$page['title'] = _('Configuration of value mapping');
$page['file'] = 'adm.valuemapping.php';

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'valuemapid' =>		[T_ZBX_INT, O_NO,	P_SYS,			DB_ID,		'(isset({form}) && {form} == "update") || isset({delete})'],
	'name' =>			[T_ZBX_STR, O_OPT,	null,			NOT_EMPTY,	'isset({add}) || isset({update})'],
	'mappings' =>		[T_ZBX_STR, O_OPT,	null,			null,		null],
	// actions
	'add' =>			[T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null],
	'update' =>			[T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null],
	'delete' =>			[T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null],
	'form' =>			[T_ZBX_STR, O_OPT,	P_SYS,			null,		null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT,	null,			null,		null]
];
check_fields($fields);

/*
 * Permissions
 */
if (hasRequest('valuemapid')) {
	$valuemaps = API::ValueMap()->get([
		'output' => ['valuemapid'],
		'valuemapids' => [getRequest('valuemapid')]
	]);

	if (!$valuemaps) {
		access_deny();
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$valuemap = [
		'name' => getRequest('name'),
		'mappings' => getRequest('mappings')
	];

	if (hasRequest('update')) {
		$valuemap['valuemapid'] = getRequest('valuemapid');

		$result = (bool) API::ValueMap()->update($valuemap);

		show_messages($result, _('Value map updated'), _('Cannot update value map'));
	}
	else {
		$result = (bool) API::ValueMap()->create($valuemap);

		show_messages($result, _('Value map added'), _('Cannot add value map'));
	}

	if ($result) {
		unset($_REQUEST['form']);
	}
}
elseif (hasRequest('delete') && hasRequest('valuemapid')) {
	$result = (bool) API::ValueMap()->delete([getRequest('valuemapid')]);

	show_messages($result, _('Value map deleted'), _('Cannot delete value map'));

	if ($result) {
		unset($_REQUEST['form']);
	}
}

/*
 * Display
 */
if (hasRequest('form')) {
	$data = [
		'form' => getRequest('form', ''),
		'valuemapid' => getRequest('valuemapid', 0),
		'valuemap_count' => 0
	];

	if ($data['valuemapid'] != 0 && !hasRequest('form_refresh')) {
		$valuemaps = API::ValueMap()->get([
			'output' => ['valuemapid', 'name'],
			'selectMappings' => ['value', 'newvalue'],
			'valuemapids' => [$data['valuemapid']]
		]);
		$valuemap = reset($valuemaps);

		$data = zbx_array_merge($data, $valuemap);
		order_result($data['mappings'], 'value');
	}
	else {
		$data['name'] = getRequest('name', '');
		$data['mappings'] = getRequest('mappings', []);
	}

	if ($data['valuemapid'] != 0) {
		$data['valuemap_count'] += API::Item()->get([
			'countOutput' => true,
			'webitems' => true,
			'filter' => ['valuemapid' => $data['valuemapid']]
		]);
		$data['valuemap_count'] += API::ItemPrototype()->get([
			'countOutput' => true,
			'filter' => ['valuemapid' => $data['valuemapid']]
		]);
	}

	if (!$data['mappings']) {
		$data['mappings'][] = ['value' => '', 'newvalue' => ''];
	}

	$view = new CView('administration.general.valuemapping.edit', $data);
}
else {
	$data['valuemaps'] = API::ValueMap()->get([
		'output' => ['valuemapid', 'name'],
		'selectMappings' => ['value', 'newvalue']
	]);
	order_result($data['valuemaps'], 'name');

	foreach ($data['valuemaps'] as &$valuemap) {
		order_result($valuemap['mappings'], 'value');
	}
	unset($valuemap);

	$view = new CView('administration.general.valuemapping.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
