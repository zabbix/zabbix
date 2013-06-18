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

$page['title'] = _('Configuration of value mapping');
$page['file'] = 'adm.valuemapping.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'valuemapid' =>		array(T_ZBX_INT, O_NO,	P_SYS,			DB_ID,		'(isset({form})&&({form}=="update"))||isset({delete})'),
	'mapname' =>		array(T_ZBX_STR, O_OPT,	null,			NOT_EMPTY,	'isset({save})'),
	'mappings' =>		array(T_ZBX_STR, O_OPT,	null,			null,		null),
	'save' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'delete' =>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,		null),
	'form' =>			array(T_ZBX_STR, O_OPT,	P_SYS,			null,		null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT,	null,			null,		null)
);
check_fields($fields);

/*
 * Permissions
 */
if (isset($_REQUEST['valuemapid'])) {
	$dbValueMap = DBfetch(DBselect('SELECT v.name FROM valuemaps v WHERE v.valuemapid='.get_request('valuemapid')));
	if (empty($dbValueMap)) {
		access_deny();
	}
}

/*
 * Actions
 */
try {
	if (isset($_REQUEST['save'])) {
		DBstart();

		$valueMap = array('name' => get_request('mapname'));
		$mappings = get_request('mappings', array());

		if (isset($_REQUEST['valuemapid'])) {
			$msg_ok = _('Value map updated');
			$msg_fail = _('Cannot update value map');
			$audit_action = AUDIT_ACTION_UPDATE;

			$valueMap['valuemapid'] = get_request('valuemapid');
			updateValueMap($valueMap, $mappings);
		}
		else {
			$msg_ok = _('Value map added');
			$msg_fail = _('Cannot add value map');
			$audit_action = AUDIT_ACTION_ADD;

			addValueMap($valueMap, $mappings);
		}

		add_audit($audit_action, AUDIT_RESOURCE_VALUE_MAP, _s('Value map "%1$s".', $valueMap['name']));
		show_messages(true, $msg_ok);
		unset($_REQUEST['form']);

		DBend(true);
	}
	elseif (isset($_REQUEST['delete']) && isset($_REQUEST['valuemapid'])) {
		DBstart();

		$msg_ok = _('Value map deleted');
		$msg_fail = _('Cannot delete value map');

		$sql = 'SELECT v.name,v.valuemapid'.
				' FROM valuemaps v'.
				' WHERE v.valuemapid='.$_REQUEST['valuemapid'].
					andDbNode('v.valuemapid');
		if ($valueMapToDelete = DBfetch(DBselect($sql))) {
			deleteValueMap($_REQUEST['valuemapid']);
		}
		else {
			throw new Exception(_s('Value map with valuemapid "%1$s" does not exist.', $_REQUEST['valuemapid']));
		}

		add_audit(
			AUDIT_ACTION_DELETE,
			AUDIT_RESOURCE_VALUE_MAP,
			_s('Value map "%1$s" "%2$s".', $valueMapToDelete['name'], $valueMapToDelete['valuemapid'])
		);
		show_messages(true, $msg_ok);
		unset($_REQUEST['form']);

		DBend(true);
	}
}
catch (Exception $e) {
	DBend(false);

	error($e->getMessage());
	show_messages(false, null, $msg_fail);
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$cmbConf = new CComboBox('configDropDown', 'adm.valuemapping.php', 'redirect(this.options[this.selectedIndex].value);');
$cmbConf->addItems(array(
	'adm.gui.php' => _('GUI'),
	'adm.housekeeper.php' => _('Housekeeper'),
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
$form->addItem($cmbConf);
if (!isset($_REQUEST['form'])) {
	$form->addItem(new CSubmit('form', _('Create value map')));
}

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF VALUE MAPPING'), $form);

$data = array();
if (isset($_REQUEST['form'])) {
	$data['form'] = get_request('form', 1);
	$data['form_refresh'] = get_request('form_refresh', 0);
	$data['valuemapid'] = get_request('valuemapid');
	$data['mappings'] = array();
	$data['mapname'] = '';
	$data['confirmMessage'] = null;
	$data['add_value'] = get_request('add_value');
	$data['add_newvalue'] = get_request('add_newvalue');

	if (isset($data['valuemapid'])) {

		$data['mapname'] = $dbValueMap['name'];

		if (empty($data['form_refresh'])) {
			$data['mappings'] = DBfetchArray(DBselect('SELECT m.mappingid,m.value,m.newvalue FROM mappings m WHERE m.valuemapid='.$data['valuemapid']));
		}
		else {
			$data['mapname'] = get_request('mapname', '');
			$data['mappings'] = get_request('mappings', array());
		}

		$valuemap_count = DBfetch(DBselect('SELECT COUNT(i.itemid) AS cnt FROM items i WHERE i.valuemapid='.$data['valuemapid']));
		if ($valuemap_count['cnt']) {
			$data['confirmMessage'] = _n('Delete selected value mapping? It is used for %d item!', 'Delete selected value mapping? It is used for %d items!', $valuemap_count['cnt']);
		}
		else {
			$data['confirmMessage'] = _('Delete selected value mapping?');
		}
	}

	if (empty($data['valuemapid']) && !empty($data['form_refresh'])) {
		$data['mapname'] = get_request('mapname', '');
		$data['mappings'] = get_request('mappings', array());
	}

	order_result($data['mappings'], 'value');

	$valueMappingForm = new CView('administration.general.valuemapping.edit', $data);
}
else {
	$cnf_wdgt->addHeader(_('Value mapping'));
	$cnf_wdgt->addItem(BR());

	$data['valuemaps'] = array();
	$dbValueMaps = DBselect(
			'SELECT v.valuemapid,v.name'.
			' FROM valuemaps v'.
			whereDbNode('v.valuemapid')
	);
	while ($dbValueMap = DBfetch($dbValueMaps)) {
		$data['valuemaps'][$dbValueMap['valuemapid']] = $dbValueMap;
		$data['valuemaps'][$dbValueMap['valuemapid']]['maps'] = array();
	}
	order_result($data['valuemaps'], 'name');

	$db_maps = DBselect(
			'SELECT m.valuemapid,m.value,m.newvalue'.
			' FROM mappings m'.
			whereDbNode('m.mappingid')
	);
	while ($db_map = DBfetch($db_maps)) {
		$data['valuemaps'][$db_map['valuemapid']]['maps'][] = array(
			'value' => $db_map['value'],
			'newvalue' => $db_map['newvalue']
		);
	}

	$valueMappingForm = new CView('administration.general.valuemapping.list', $data);
}

$cnf_wdgt->addItem($valueMappingForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
