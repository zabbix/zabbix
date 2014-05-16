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
require_once dirname(__FILE__).'/include/ident.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

if (isset($_REQUEST['go']) && $_REQUEST['go'] == 'export' && isset($_REQUEST['maps'])) {
	$page['file'] = 'zbx_export_maps.xml';
	$page['type'] = detect_page_type(PAGE_TYPE_XML);

	$isExportData = true;
}
else {
	$page['title'] = _('Configuration of network maps');
	$page['file'] = 'sysmaps.php';
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['hist_arg'] = array();

	$isExportData = false;
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'maps' =>					array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'sysmapid' =>				array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'name' =>					array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY, 'isset({save})', _('Name')),
	'width' =>					array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})', _('Width')),
	'height' =>					array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 65535), 'isset({save})', _('Height')),
	'backgroundid' =>			array(T_ZBX_INT, O_OPT, null,	DB_ID,			'isset({save})'),
	'iconmapid' =>				array(T_ZBX_INT, O_OPT, null,	DB_ID,			'isset({save})'),
	'expandproblem' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null),
	'markelements' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null),
	'show_unack' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 2),	null),
	'highlight' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null),
	'label_format' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 1),	null),
	'label_type_host' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({save})'),
	'label_type_hostgroup' =>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({save})'),
	'label_type_trigger' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({save})'),
	'label_type_map' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({save})'),
	'label_type_image' =>		array(T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL, MAP_LABEL_TYPE_CUSTOM), 'isset({save})'),
	'label_string_host' =>		array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'label_string_hostgroup' =>	array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'label_string_trigger' =>	array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'label_string_map' =>		array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'label_string_image' =>		array(T_ZBX_STR, O_OPT, null,	null,			'isset({save})'),
	'label_type' =>				array(T_ZBX_INT, O_OPT, null,	BETWEEN(MAP_LABEL_TYPE_LABEL,MAP_LABEL_TYPE_CUSTOM), 'isset({save})'),
	'label_location' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(0, 3),	'isset({save})'),
	'urls' =>					array(T_ZBX_STR, O_OPT, null,	null,			null),
	'severity_min' =>			array(T_ZBX_INT, O_OPT, null,	IN('0,1,2,3,4,5'), null),
	// actions
	'save' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'delete' =>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'cancel' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'go' =>						array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	// form
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null,	null,			null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP);

/*
 * Permissions
 */
if (isset($_REQUEST['sysmapid'])) {
	$sysmap = API::Map()->get(array(
		'sysmapids' => $_REQUEST['sysmapid'],
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectUrls' => API_OUTPUT_EXTEND
	));
	if (empty($sysmap)) {
		access_deny();
	}
	else {
		$sysmap = reset($sysmap);
	}
}
else {
	$sysmap = array();
}

if ($isExportData) {
	$export = new CConfigurationExport(array('maps' => get_request('maps', array())));
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();

	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		echo $exportData;
	}
	exit();
}

$_REQUEST['go'] = get_request('go', 'none');

/*
 * Actions
 */
if (isset($_REQUEST['save'])) {
	$map = array(
		'name' => $_REQUEST['name'],
		'width' => $_REQUEST['width'],
		'height' => $_REQUEST['height'],
		'backgroundid' => $_REQUEST['backgroundid'],
		'iconmapid' => $_REQUEST['iconmapid'],
		'highlight' => get_request('highlight', 0),
		'markelements' => get_request('markelements', 0),
		'expandproblem' => get_request('expandproblem', 0),
		'label_format' => get_request('label_format', 0),
		'label_type_host' => get_request('label_type_host', 2),
		'label_type_hostgroup' => get_request('label_type_hostgroup', 2),
		'label_type_trigger' => get_request('label_type_trigger', 2),
		'label_type_map' => get_request('label_type_map', 2),
		'label_type_image' => get_request('label_type_image', 2),
		'label_string_host' => get_request('label_string_host', ''),
		'label_string_hostgroup' => get_request('label_string_hostgroup', ''),
		'label_string_trigger' => get_request('label_string_trigger', ''),
		'label_string_map' => get_request('label_string_map', ''),
		'label_string_image' => get_request('label_string_image', ''),
		'label_type' => $_REQUEST['label_type'],
		'label_location' => $_REQUEST['label_location'],
		'show_unack' => get_request('show_unack', 0),
		'severity_min' => get_request('severity_min', TRIGGER_SEVERITY_NOT_CLASSIFIED),
		'urls' => get_request('urls', array())
	);

	foreach ($map['urls'] as $unum => $url) {
		if (zbx_empty($url['name']) && zbx_empty($url['url'])) {
			unset($map['urls'][$unum]);
		}
	}

	if (isset($_REQUEST['sysmapid'])) {
		// TODO check permission by new value.
		$map['sysmapid'] = $_REQUEST['sysmapid'];
		$result = API::Map()->update($map);

		$auditAction = AUDIT_ACTION_UPDATE;
		show_messages($result, _('Network map updated'), _('Cannot update network map'));
	}
	else {
		$result = API::Map()->create($map);

		$auditAction = AUDIT_ACTION_ADD;
		show_messages($result, _('Network map added'), _('Cannot add network map'));
	}

	if ($result) {
		add_audit($auditAction, AUDIT_RESOURCE_MAP, 'Name ['.$_REQUEST['name'].']');
		unset($_REQUEST['form']);
		clearCookies($result);
	}
}
elseif ((isset($_REQUEST['delete']) && isset($_REQUEST['sysmapid'])) || $_REQUEST['go'] == 'delete') {
	$sysmapIds = get_request('maps', array());

	if (isset($_REQUEST['sysmapid'])) {
		$sysmapIds[] = $_REQUEST['sysmapid'];
	}

	DBstart();

	$maps = API::Map()->get(array(
		'sysmapids' => $sysmapIds,
		'output' => array('sysmapid', 'name'),
		'editable' => true
	));

	$result = API::Map()->delete($sysmapIds);

	if ($result) {
		unset($_REQUEST['form']);

		foreach ($maps as $map) {
			add_audit_ext(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MAP, $map['sysmapid'], $map['name'], null, null, null);
		}
	}

	$result = DBend($result);

	show_messages($result, _('Network map deleted'), _('Cannot delete network map'));
	clearCookies($result);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	if (!isset($_REQUEST['sysmapid']) || isset($_REQUEST['form_refresh'])) {
		$data = array(
			'sysmap' => array(
				'sysmapid' => getRequest('sysmapid'),
				'name' => get_request('name', ''),
				'width' => get_request('width', 800),
				'height' => get_request('height', 600),
				'backgroundid' => get_request('backgroundid', 0),
				'iconmapid' => get_request('iconmapid', 0),
				'label_format' => get_request('label_format', 0),
				'label_type_host' => get_request('label_type_host', 2),
				'label_type_hostgroup' => get_request('label_type_hostgroup', 2),
				'label_type_trigger' => get_request('label_type_trigger', 2),
				'label_type_map' => get_request('label_type_map', 2),
				'label_type_image' => get_request('label_type_image', 2),
				'label_string_host' => get_request('label_string_host', ''),
				'label_string_hostgroup' => get_request('label_string_hostgroup', ''),
				'label_string_trigger' => get_request('label_string_trigger', ''),
				'label_string_map' => get_request('label_string_map', ''),
				'label_string_image' => get_request('label_string_image', ''),
				'label_type' => get_request('label_type', 0),
				'label_location' => get_request('label_location', 0),
				'highlight' => get_request('highlight', 0),
				'markelements' => get_request('markelements', 0),
				'expandproblem' => get_request('expandproblem', 0),
				'show_unack' => get_request('show_unack', 0),
				'severity_min' => get_request('severity_min', TRIGGER_SEVERITY_NOT_CLASSIFIED),
				'urls' => get_request('urls', array())
			)
		);
	}
	else {
		$data = array('sysmap' => $sysmap);
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
	$data['images'] = API::Image()->get(array(
		'output' => array('imageid', 'name'),
		'filter' => array('imagetype' => IMAGE_TYPE_BACKGROUND)
	));
	order_result($data['images'], 'name');

	foreach ($data['images'] as $num => $image) {
		$data['images'][$num]['name'] = get_node_name_by_elid($image['imageid'], null, NAME_DELIMITER).$image['name'];
	}

	// icon maps
	$data['iconMaps'] = API::IconMap()->get(array(
		'output' => array('iconmapid', 'name'),
		'preservekeys' => true
	));
	order_result($data['iconMaps'], 'name');

	// render view
	$mapView = new CView('configuration.sysmap.edit', $data);
	$mapView->render();
	$mapView->show();
}
else {
	$data = array();

	// get maps
	$sortField = getPageSortField('name');
	$sortOrder = getPageSortOrder();

	$data['maps'] = API::Map()->get(array(
		'editable' => true,
		'output' => array('sysmapid', 'name', 'width', 'height'),
		'sortfield' => $sortField,
		'sortorder' => $sortOrder,
		'limit' => $config['search_limit'] + 1
	));
	order_result($data['maps'], $sortField, $sortOrder);

	// paging
	$data['paging'] = getPagingLine($data['maps'], array('sysmapid'));

	// nodes
	if ($data['displayNodes'] = is_array(get_current_nodeid())) {
		foreach ($data['maps'] as &$map) {
			$map['nodename'] = get_node_name_by_elid($map['sysmapid'], true);
		}
		unset($map);
	}

	// render view
	$mapView = new CView('configuration.sysmap.list', $data);
	$mapView->render();
	$mapView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
