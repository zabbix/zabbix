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
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

if (hasRequest('action') && getRequest('action') == 'screen.export' && hasRequest('screens')) {
	$isExportData = true;

	$page['type'] = detect_page_type(PAGE_TYPE_XML);
	$page['file'] = 'zbx_export_screens.xml';
}
else {
	$isExportData = false;

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	$page['title'] = _('Configuration of screens');
	$page['file'] = 'screenconf.php';
	$page['hist_arg'] = array('templateid');
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'screens' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'screenid' =>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,			'isset({form}) && {form} == "update"'),
	'templateid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({add}) || isset({update})', _('Name')),
	'hsize' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({add}) || isset({update})', _('Columns')),
	'vsize' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({add}) || isset({update})', _('Rows')),
	// actions
	'action' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, IN('"screen.export","screen.massdelete"'),		null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'add' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'update' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,			null),
	// import
	'rules' =>			array(T_ZBX_STR, O_OPT, null,	DB_ID,			null),
	'import' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	// sort and sortorder
	'sort' =>					array(T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),								null),
	'sortorder' =>				array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

CProfile::update('web.screenconf.config', getRequest('config', 0), PROFILE_TYPE_INT);

/*
 * Permissions
 */
if (isset($_REQUEST['screenid'])) {
	$options = array(
		'screenids' => $_REQUEST['screenid'],
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'selectScreenItems' => API_OUTPUT_EXTEND
	);
	if (isset($_REQUEST['templateid'])) {
		$screens = API::TemplateScreen()->get($options);
	}
	else {
		$screens = API::Screen()->get($options);
	}

	if (empty($screens)) {
		access_deny();
	}
}

/*
 * Export
 */
if ($isExportData) {
	$screens = getRequest('screens', array());
	$export = new CConfigurationExport(array('screens' => $screens));
	$export->setBuilder(new CConfigurationExportBuilder());
	$export->setWriter(CExportWriterFactory::getWriter(CExportWriterFactory::XML));
	$exportData = $export->export();
	if (hasErrorMesssages()) {
		show_messages();
	}
	else {
		print($exportData);
	}

	exit;
}


/*
 * Actions
 */
if (isset($_REQUEST['clone']) && isset($_REQUEST['screenid'])) {
	unset($_REQUEST['screenid']);
	$_REQUEST['form'] = 'clone';
}
elseif (hasRequest('add') || hasRequest('update')) {
	DBstart();

	if (hasRequest('update')) {
		$screen = array(
			'screenid' => getRequest('screenid'),
			'name' => getRequest('name'),
			'hsize' => getRequest('hsize'),
			'vsize' => getRequest('vsize')
		);

		$messageSuccess = _('Screen updated');
		$messageFailed = _('Cannot update screen');

		if (hasRequest('templateid')) {
			$screenOld = API::TemplateScreen()->get(array(
				'screenids' => getRequest('screenid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			));
			$screenOld = reset($screenOld);

			$result = API::TemplateScreen()->update($screen);
		}
		else {
			$screenOld = API::Screen()->get(array(
				'screenids' => getRequest('screenid'),
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			));
			$screenOld = reset($screenOld);

			$result = API::Screen()->update($screen);
		}

		if ($result) {
			add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name'], 'screens', $screenOld, $screen);
		}
	}
	else {
		$screen = array(
			'name' => getRequest('name'),
			'hsize' => getRequest('hsize'),
			'vsize' => getRequest('vsize')
		);

		$messageSuccess = _('Screen added');
		$messageFailed = _('Cannot add screen');

		if (hasRequest('templateid')) {
			$screen['templateid'] = getRequest('templateid');
			$screenids = API::TemplateScreen()->create($screen);
		}
		else {
			$screenids = API::Screen()->create($screen);
		}

		$result = (bool) $screenids;
		if ($result) {
			$screenid = reset($screenids);
			$screenid = reset($screenid);
			add_audit_details(AUDIT_ACTION_ADD, AUDIT_RESOURCE_SCREEN, $screenid, $screen['name']);
		}
	}

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['form'], $_REQUEST['screenid']);
		uncheckTableRows();
	}
	show_messages($result, $messageSuccess, $messageFailed);
}
elseif ((hasRequest('delete') && hasRequest('screenid')) || (hasRequest('action') && getRequest('action') == 'screen.massdelete' && hasRequest('screens'))) {
	$screenids = getRequest('screens', array());
	if (hasRequest('screenid')) {
		$screenids[] = getRequest('screenid');
	}

	DBstart();

	$screens = API::Screen()->get(array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	));

	if ($screens) {
		$result = API::Screen()->delete($screenids);

		if ($result) {
			foreach ($screens as $screen) {
				add_audit_details(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name']);
			}
		}
	}
	else {
		$result = API::TemplateScreen()->delete($screenids);

		if ($result) {
			$templatedScreens = API::TemplateScreen()->get(array(
				'screenids' => $screenids,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			));

			foreach ($templatedScreens as $screen) {
				add_audit_details(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCREEN, $screen['screenid'], $screen['name']);
			}
		}
	}

	$result = DBend($result);

	if ($result) {
		unset($_REQUEST['screenid'], $_REQUEST['form']);
		uncheckTableRows();
	}
	show_messages($result, _('Screen deleted'), _('Cannot delete screen'));
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => getRequest('form'),
		'screenid' => getRequest('screenid'),
		'templateid' => getRequest('templateid')
	);

	// screen
	if (!empty($data['screenid'])) {
		$options = array(
			'screenids' => $data['screenid'],
			'editable' => true,
			'output' => API_OUTPUT_EXTEND
		);
		if (!empty($data['templateid'])) {
			$screens = API::TemplateScreen()->get($options);
		}
		else {
			$screens = API::Screen()->get($options);
		}
		$data['screen'] = reset($screens);
	}

	if (!empty($data['screenid']) && !isset($_REQUEST['form_refresh'])) {
		$data['name'] = $data['screen']['name'];
		$data['hsize'] = $data['screen']['hsize'];
		$data['vsize'] = $data['screen']['vsize'];
		if (!empty($data['screen']['templateid'])) {
			$data['templateid'] = $data['screen']['templateid'];
		}
	}
	else {
		$data['name'] = getRequest('name', '');
		$data['hsize'] = getRequest('hsize', 1);
		$data['vsize'] = getRequest('vsize', 1);
	}

	// render view
	$screenView = new CView('configuration.screen.edit', $data);
	$screenView->render();
	$screenView->show();
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	$config = select_config();

	$data = array(
		'templateid' => getRequest('templateid'),
		'sort' => $sortField,
		'sortorder' => $sortOrder
	);

	$options = array(
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'templateids' => $data['templateid'],
		'sortfield' => $sortField,
		'limit' => $config['search_limit']
	);
	if (!empty($data['templateid'])) {
		$data['screens'] = API::TemplateScreen()->get($options);
	}
	else {
		$data['screens'] = API::Screen()->get($options);
	}
	order_result($data['screens'], $sortField, $sortOrder);

	// paging
	$data['paging'] = getPagingLine($data['screens']);

	// render view
	$screenView = new CView('configuration.screen.list', $data);
	$screenView->render();
	$screenView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
