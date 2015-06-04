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
require_once dirname(__FILE__).'/include/maps.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

if (hasRequest('action') && getRequest('action') == 'map.export' && hasRequest('maps')) {
	$page['file'] = 'zbx_export_maps.xml';
	$page['type'] = detect_page_type(PAGE_TYPE_XML);

	$isExportData = true;
}
else {
	$page['title'] = _('Configuration of network maps');
	$page['file'] = 'sysmaps.php';
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	$isExportData = false;
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'maps' =>					[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'sysmapid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null],
	'name' =>					[T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({add}) || isset({update})', _('Name')],
	'width' =>					[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({add}) || isset({update})', _('Width')],
	'height' =>					[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({add}) || isset({update})', _('Height')],
	'backgroundid' =>			[T_ZBX_INT, O_OPT, null,	DB_ID,			'isset({add}) || isset({update})'],
	'iconmapid' =>				[T_ZBX_INT, O_OPT, null,	DB_ID,			'isset({add}) || isset({update})'],
	'expandproblem' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null],
	'markelements' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null],
	'show_unack' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 2),	null],
	'highlight' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null],
	'label_format' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null],
	'label_type_host' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({add}) || isset({update})'],
	'label_type_hostgroup' =>	[T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({add}) || isset({update})'],
	'label_type_trigger' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({add}) || isset({update})'],
	'label_type_map' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({add}) || isset({update})'],
	'label_type_image' =>		[T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({add}) || isset({update})'],
	'label_string_host' =>		[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'label_string_hostgroup' =>	[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'label_string_trigger' =>	[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'label_string_map' =>		[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'label_string_image' =>		[T_ZBX_STR, O_OPT, null,	null,			'isset({add}) || isset({update})'],
	'label_type' =>				[T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL,MAP_LABEL_TYPE_CUSTOM), 'isset({add}) || isset({update})'],
	'label_location' =>			[T_ZBX_INT, O_OPT, null,	BETWEEN(0, 3),	'isset({add}) || isset({update})'],
	'urls' =>					[T_ZBX_STR, O_OPT, null,	null,			null],
	'severity_min' =>			[T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), null],
	// actions
	'action' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"map.export","map.massdelete"'),		null],
	'add' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'update' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'delete' =>					[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null],
	'cancel' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,			null],
	// form
	'form' =>					[T_ZBX_STR, O_OPT, P_SYS,	null,			null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,			null],
	// sort and sortorder
	'sort' =>					[T_ZBX_STR, O_OPT, P_SYS, IN('"height","name","width"'),				null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

/*
 * Permissions
 */
if (hasRequest('sysmapid')) {
	$sysmap = API::Map()->get([
		'sysmapids' => getRequest('sysmapid'),
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectUrls' => API_OUTPUT_EXTEND
	]);
	if (empty($sysmap)) {
		access_deny();
	}
	else {
		$sysmap = reset($sysmap);
	}
}
else {
	$sysmap = [];
}

if ($isExportData) {
	$export = new CConfigurationExport(['maps' => getRequest('maps', [])]);
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();

	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		echo $exportData;
	}

	exit;
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$map = [
		'name' => getRequest('name'),
		'width' => getRequest('width'),
		'height' => getRequest('height'),
		'backgroundid' => getRequest('backgroundid'),
		'iconmapid' => getRequest('iconmapid'),
		'highlight' => getRequest('highlight', 0),
		'markelements' => getRequest('markelements', 0),
		'expandproblem' => getRequest('expandproblem', 0),
		'label_format' => getRequest('label_format', 0),
		'label_type_host' => getRequest('label_type_host', 2),
		'label_type_hostgroup' => getRequest('label_type_hostgroup', 2),
		'label_type_trigger' => getRequest('label_type_trigger', 2),
		'label_type_map' => getRequest('label_type_map', 2),
		'label_type_image' => getRequest('label_type_image', 2),
		'label_string_host' => getRequest('label_string_host', ''),
		'label_string_hostgroup' => getRequest('label_string_hostgroup', ''),
		'label_string_trigger' => getRequest('label_string_trigger', ''),
		'label_string_map' => getRequest('label_string_map', ''),
		'label_string_image' => getRequest('label_string_image', ''),
		'label_type' => getRequest('label_type'),
		'label_location' => getRequest('label_location'),
		'show_unack' => getRequest('show_unack', 0),
		'severity_min' => getRequest('severity_min', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		'urls' => getRequest('urls', [])
	];

	foreach ($map['urls'] as $unum => $url) {
		if (zbx_empty($url['name']) && zbx_empty($url['url'])) {
			unset($map['urls'][$unum]);
		}
	}

	DBstart();

	if (hasRequest('update')) {
		// TODO check permission by new value.
		$map['sysmapid'] = getRequest('sysmapid');
		$result = API::Map()->update($map);

		$messageSuccess = _('Network map updated');
		$messageFailed = _('Cannot update network map');
		$auditAction = AUDIT_ACTION_UPDATE;
	}
	else {
		$result = API::Map()->create($map);

		$messageSuccess = _('Network map added');
		$messageFailed = _('Cannot add network map');
		$auditAction = AUDIT_ACTION_ADD;
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_MAP, 'Name ['.$map['name'].']');
		unset($_REQUEST['form']);
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif ((hasRequest('delete') && hasRequest('sysmapid')) || (hasRequest('action') && getRequest('action') == 'map.massdelete')) {
	$sysmapIds = getRequest('maps', []);

	if (hasRequest('sysmapid')) {
		$sysmapIds[] = getRequest('sysmapid');
	}

	DBstart();

	$maps = API::Map()->get([
		'sysmapids' => $sysmapIds,
		'output' => ['sysmapid', 'name'],
		'editable' => true
	]);
	$result = API::Map()->delete($sysmapIds);

	if ($result) {
		unset($_REQUEST['form']);

		foreach ($maps as $map) {
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MAP, $map['sysmapid'], $map['name'], null, null, null);
		}
	}

	$result = DBend($result);

	if ($result) {
		uncheckTableRows();
	}
	show_messages($result, _('Network map deleted'), _('Cannot delete network map'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	if (!isset($_REQUEST['sysmapid']) || isset($_REQUEST['form_refresh'])) {
		$data = [
			'sysmap' => [
				'sysmapid' => getRequest('sysmapid'),
				'name' => getRequest('name', ''),
				'width' => getRequest('width', 800),
				'height' => getRequest('height', 600),
				'backgroundid' => getRequest('backgroundid', 0),
				'iconmapid' => getRequest('iconmapid', 0),
				'label_format' => getRequest('label_format', 0),
				'label_type_host' => getRequest('label_type_host', 2),
				'label_type_hostgroup' => getRequest('label_type_hostgroup', 2),
				'label_type_trigger' => getRequest('label_type_trigger', 2),
				'label_type_map' => getRequest('label_type_map', 2),
				'label_type_image' => getRequest('label_type_image', 2),
				'label_string_host' => getRequest('label_string_host', ''),
				'label_string_hostgroup' => getRequest('label_string_hostgroup', ''),
				'label_string_trigger' => getRequest('label_string_trigger', ''),
				'label_string_map' => getRequest('label_string_map', ''),
				'label_string_image' => getRequest('label_string_image', ''),
				'label_type' => getRequest('label_type', 0),
				'label_location' => getRequest('label_location', 0),
				'highlight' => getRequest('highlight', 0),
				'markelements' => getRequest('markelements', 0),
				'expandproblem' => getRequest('expandproblem', 0),
				'show_unack' => getRequest('show_unack', 0),
				'severity_min' => getRequest('severity_min', TRIGGER_SEVERITY_NOT_CLASSIFIED),
				'urls' => getRequest('urls', [])
			]
		];
	}
	else {
		$data = ['sysmap' => $sysmap];
	}

	// config
	$data['config'] = select_config();

	// advanced labels
	$data['labelTypes'] = sysmapElementLabel();
	$data['labelTypesLimited'] = $data['labelTypes'];
	unset($data['labelTypesLimited'][MAP_LABEL_TYPE_IP]);
	$data['labelTypesImage'] = $data['labelTypesLimited'];
	unset($data['labelTypesImage'][MAP_LABEL_TYPE_STATUS]);

	// images
	$data['images'] = API::Image()->get([
		'output' => ['imageid', 'name'],
		'filter' => ['imagetype' => IMAGE_TYPE_BACKGROUND]
	]);
	order_result($data['images'], 'name');

	// icon maps
	$data['iconMaps'] = API::IconMap()->get([
		'output' => ['iconmapid', 'name'],
		'preservekeys' => true
	]);
	order_result($data['iconMaps'], 'name');

	// render view
	$mapView = new CView('configuration.sysmap.edit', $data);
	$mapView->render();
	$mapView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder
	];

	// get maps
	$data['maps'] = API::Map()->get([
		'editable' => true,
		'output' => ['sysmapid', 'name', 'width', 'height'],
		'sortfield' => $sortField,
		'limit' => $config['search_limit'] + 1
	]);
	order_result($data['maps'], $sortField, $sortOrder);

	// paging
	$data['paging'] = getPagingLine($data['maps'], $sortOrder);

	// render view
	$mapView = new CView('configuration.sysmap.list', $data);
	$mapView->render();
	$mapView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
