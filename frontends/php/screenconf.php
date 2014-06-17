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
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/ident.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';

if (isset($_REQUEST['go']) && $_REQUEST['go'] == 'export' && isset($_REQUEST['screens'])) {
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
	'screenid' =>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,			'isset({form})&&{form}=="update"'),
	'templateid' =>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,			null),
	'name' =>			array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,		'isset({save})', _('Name')),
	'hsize' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({save})', _('Columns')),
	'vsize' =>			array(T_ZBX_INT, O_OPT, null,	BETWEEN(1, 100), 'isset({save})', _('Rows')),
	// actions
	'go' =>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'clone' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'delete' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null),
	'cancel' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form' =>			array(T_ZBX_STR, O_OPT, P_SYS,	null,			null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,	null,			null),
	// import
	'rules' =>			array(T_ZBX_STR, O_OPT, null,	DB_ID,			null),
	'import' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,		null)
);
check_fields($fields);
validate_sort_and_sortorder('name', ZBX_SORT_UP, array('name'));

CProfile::update('web.screenconf.config', get_request('config', 0), PROFILE_TYPE_INT);
$_REQUEST['go'] = get_request('go', 'none');

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
	$screens = get_request('screens', array());
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
elseif (isset($_REQUEST['save'])) {
	DBstart();

	if (isset($_REQUEST['screenid'])) {
		$screen = array(
			'screenid' => $_REQUEST['screenid'],
			'name' => $_REQUEST['name'],
			'hsize' => $_REQUEST['hsize'],
			'vsize' => $_REQUEST['vsize']
		);

		$messageSuccess = _('Screen updated');
		$messageFailed = _('Cannot update screen');

		if (isset($_REQUEST['templateid'])) {
			$screenOld = API::TemplateScreen()->get(array(
				'screenids' => $_REQUEST['screenid'],
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			));
			$screenOld = reset($screenOld);

			$result = API::TemplateScreen()->update($screen);
		}
		else {
			$screenOld = API::Screen()->get(array(
				'screenids' => $_REQUEST['screenid'],
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
			'name' => $_REQUEST['name'],
			'hsize' => $_REQUEST['hsize'],
			'vsize' => $_REQUEST['vsize']
		);

		$messageSuccess = _('Screen added');
		$messageFailed = _('Cannot add screen');

		if (isset($_REQUEST['templateid'])) {
			$screen['templateid'] = get_request('templateid');
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
	}
	show_messages($result, $messageSuccess, $messageFailed);
	clearCookies($result);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['screenid']) || $_REQUEST['go'] == 'delete') {
	$screenids = get_request('screens', array());
	if (isset($_REQUEST['screenid'])) {
		$screenids[] = $_REQUEST['screenid'];
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
	}

	show_messages($result, _('Screen deleted'), _('Cannot delete screen'));
	clearCookies($result);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = array(
		'form' => get_request('form', null),
		'screenid' => get_request('screenid', null),
		'templateid' => get_request('templateid', null)
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
		$data['name'] = get_request('name', '');
		$data['hsize'] = get_request('hsize', 1);
		$data['vsize'] = get_request('vsize', 1);
	}

	// render view
	$screenView = new CView('configuration.screen.edit', $data);
	$screenView->render();
	$screenView->show();
}
else {
	$data = array(
		'templateid' => get_request('templateid', null)
	);

	$sortfield = getPageSortField('name');
	$options = array(
		'editable' => true,
		'output' => API_OUTPUT_EXTEND,
		'templateids' => $data['templateid'],
		'sortfield' => $sortfield,
		'limit' => $config['search_limit']
	);
	if (!empty($data['templateid'])) {
		$data['screens'] = API::TemplateScreen()->get($options);
	}
	else {
		$data['screens'] = API::Screen()->get($options);
	}
	order_result($data['screens'], $sortfield, getPageSortOrder());

	// paging
	$data['paging'] = getPagingLine($data['screens']);

	// render view
	$screenView = new CView('configuration.screen.list', $data);
	$screenView->render();
	$screenView->show();
}

require_once dirname(__FILE__).'/include/page_footer.php';
