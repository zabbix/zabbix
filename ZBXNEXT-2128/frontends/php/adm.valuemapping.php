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
	'mapname' =>		[T_ZBX_STR, O_OPT,	null,			NOT_EMPTY,	'isset({add}) || isset({update})'],
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
if (isset($_REQUEST['valuemapid'])) {
	$dbValueMap = DBfetch(DBselect('SELECT v.name FROM valuemaps v WHERE v.valuemapid='.zbx_dbstr(getRequest('valuemapid'))));
	if (empty($dbValueMap)) {
		access_deny();
	}
}

/*
 * Actions
 */
try {
	if (hasRequest('add') || hasRequest('update')) {
		DBstart();

		$valueMap = ['name' => getRequest('mapname')];
		$mappings = getRequest('mappings', []);

		if (hasRequest('update')) {
			$messageSuccess = _('Value map updated');
			$messageFailed = _('Cannot update value map');
			$auditAction = AUDIT_ACTION_UPDATE;

			$valueMap['valuemapid'] = getRequest('valuemapid');
			$result = updateValueMap($valueMap, $mappings);
		}
		else {
			$messageSuccess = _('Value map added');
			$messageFailed = _('Cannot add value map');
			$auditAction = AUDIT_ACTION_ADD;

			$result = addValueMap($valueMap, $mappings);
		}

		if ($result) {
			add_audit($auditAction, AUDIT_RESOURCE_VALUE_MAP, _s('Value map "%1$s".', $valueMap['name']));
		}
		unset($_REQUEST['form']);

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
	elseif (isset($_REQUEST['delete']) && isset($_REQUEST['valuemapid'])) {
		$messageSuccess = _('Value map deleted');
		$messageFailed = _('Cannot delete value map');

		DBstart();

		$sql = 'SELECT v.name,v.valuemapid'.
				' FROM valuemaps v'.
				' WHERE v.valuemapid='.zbx_dbstr($_REQUEST['valuemapid']);

		if ($valueMapToDelete = DBfetch(DBselect($sql))) {
			$result = deleteValueMap($_REQUEST['valuemapid']);

			if ($result) {
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_VALUE_MAP,
					_s('Value map "%1$s" "%2$s".', $valueMapToDelete['name'], $valueMapToDelete['valuemapid'])
				);
			}
		}
		else {
			throw new Exception(_s('Value map with valuemapid "%1$s" does not exist.', $_REQUEST['valuemapid']));
		}

		unset($_REQUEST['form']);

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
catch (Exception $e) {
	DBend(false);
	error($e->getMessage());
	show_messages(false, null, $messageFailed);
}

/*
 * Display
 */
if (isset($_REQUEST['form'])) {
	$data = [
		'form' => getRequest('form', 1),
		'valuemapid' => getRequest('valuemapid', 0),
		'confirmMessage' => null
	];

	if ($data['valuemapid'] != 0 && !hasRequest('form_refresh')) {
		$data['mapname'] = $dbValueMap['name'];
		$data['mappings'] = DBfetchArray(DBselect(
			'SELECT m.mappingid,m.value,m.newvalue FROM mappings m WHERE m.valuemapid='.zbx_dbstr($data['valuemapid'])
		));

		order_result($data['mappings'], 'value');
	}
	else {
		$data['mapname'] = getRequest('mapname', '');
		$data['mappings'] = getRequest('mappings', []);
	}

	if ($data['valuemapid'] != 0) {
		$valueMapCount = DBfetch(DBselect(
			'SELECT COUNT(*) AS cnt FROM items i WHERE i.valuemapid='.zbx_dbstr($data['valuemapid'])
		));

		$data['confirmMessage'] = $valueMapCount['cnt']
			? _n('Delete selected value mapping? It is used for %d item!',
					'Delete selected value mapping? It is used for %d items!', $valueMapCount['cnt'])
			: _('Delete selected value mapping?');
	}

	$view = new CView('administration.general.valuemapping.edit', $data);
}
else {
	$data = [
		'valuemaps' => []
	];

	$dbValueMaps = DBselect('SELECT v.valuemapid,v.name FROM valuemaps v');

	while ($dbValueMap = DBfetch($dbValueMaps)) {
		$data['valuemaps'][$dbValueMap['valuemapid']] = $dbValueMap;
		$data['valuemaps'][$dbValueMap['valuemapid']]['maps'] = [];
	}
	order_result($data['valuemaps'], 'name');

	$dbMaps = DBselect('SELECT m.valuemapid,m.value,m.newvalue FROM mappings m');

	while ($dbMap = DBfetch($dbMaps)) {
		$data['valuemaps'][$dbMap['valuemapid']]['maps'][] = [
			'value' => $dbMap['value'],
			'newvalue' => $dbMap['newvalue']
		];
	}

	$view = new CView('administration.general.valuemapping.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
