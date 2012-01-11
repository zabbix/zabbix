<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once('include/config.inc.php');

$page['title'] = _('Configuration of Zabbix');
$page['file'] = 'adm.valuemapping.php';

require_once('include/page_header.php');
?>
<?php
$fields = array(
	// VAR					        TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	'valuemapid'=>				array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,			'isset({form})&&({form}=="update")'),
	'mapname'=>					array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 		'isset({save})'),
	'valuemap'=>				array(T_ZBX_STR, O_OPT,	null,	null,	null),
	'add_map'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'del_map'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'rem_value'=>				array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,65535), null),
	'add_value'=>				array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 'isset({add_map})'),
	'add_newvalue'=>			array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY, 'isset({add_map})'),

	'save'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'delete'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
	'form' =>					array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT,	null,	null,	null)
);
?>
<?php
check_fields($fields);


$_REQUEST['valuemap'] = get_request('valuemap', array());
if (isset($_REQUEST['add_map'])) {
	if (!zbx_is_int($_REQUEST['add_value'])) {
		info(_('Value maps are used to create a mapping between numeric values and string representations.'));
		show_messages(false, null, _('Cannot add value map'));
	}
	else {
		$added = false;
		foreach ($_REQUEST['valuemap'] as $num => $valueMap) {
			if ($valueMap['value'] == $_REQUEST['add_value']) {
				$_REQUEST['valuemap'][$num]['newvalue'] = $_REQUEST['add_newvalue'];
				$added = true;
				break;
			}
		}

		if (!$added) {
			$_REQUEST['valuemap'][] = array(
				'value' => $_REQUEST['add_value'],
				'newvalue' => $_REQUEST['add_newvalue']
			);
		}

		unset($_REQUEST['add_value'], $_REQUEST['add_newvalue']);
	}
}
elseif (isset($_REQUEST['del_map']) && isset($_REQUEST['rem_value'])) {
	$_REQUEST['valuemap'] = get_request('valuemap', array());
	foreach ($_REQUEST['rem_value'] as $val) {
		unset($_REQUEST['valuemap'][$val]);
	}
}
elseif (isset($_REQUEST['save'])) {
	$mapping = get_request('valuemap', array());
	$prevMap = getValuemapByName($_REQUEST['mapname']);
	if (!$prevMap || (isset($_REQUEST['valuemapid']) && bccomp($_REQUEST['valuemapid'], $prevMap['valuemapid']) == 0) ) {
		if (isset($_REQUEST['valuemapid'])) {
			$result = update_valuemap($_REQUEST['valuemapid'], $_REQUEST['mapname'], $mapping);
			$audit_action = AUDIT_ACTION_UPDATE;
			$msg_ok = _('Value map updated');
			$msg_fail = _('Cannot update value map');
			$valuemapid = $_REQUEST['valuemapid'];
		}
		else {
			$result = add_valuemap($_REQUEST['mapname'], $mapping);
			$audit_action = AUDIT_ACTION_ADD;
			$msg_ok = _('Value map added');
			$msg_fail = _('Cannot add value map');
			$valuemapid = $result;
		}
	}
	else {
		$msg_ok = _('Value map added');
		$msg_fail = _s('Cannot add or update value map. Map with name "%s" already exists', $_REQUEST['mapname']);
		$result = 0;
	}
	if ($result) {
		add_audit($audit_action, AUDIT_RESOURCE_VALUE_MAP, _('Value map').' ['.$_REQUEST['mapname'].'] ['.$valuemapid.']');
		unset($_REQUEST['form']);
	}
	show_messages($result, $msg_ok, $msg_fail);
}
elseif (isset($_REQUEST['delete']) && isset($_REQUEST['valuemapid'])) {
	$result = false;

	$sql = 'SELECT m.name, m.valuemapid'.
			' FROM valuemaps m WHERE '.DBin_node('m.valuemapid').
			' AND m.valuemapid='.$_REQUEST['valuemapid'];
	if ($map_data = DBfetch(DBselect($sql))) {
		$result = delete_valuemap($_REQUEST['valuemapid']);
	}

	if ($result) {
		add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_VALUE_MAP, _('Value map').' ['.$map_data['name'].'] ['.$map_data['valuemapid'].']');
		unset($_REQUEST['form']);
	}
	show_messages($result, _('Value map deleted'), _('Cannot delete value map'));
}


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
	'adm.triggerdisplayingoptions.php' => _('Trigger displaying options'),
	'adm.other.php' => _('Other')
));
$form->addItem($cmbConf);
if (!isset($_REQUEST['form'])) {
	$form->addItem(new CSubmit('form', _('Create value map')));
}


$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF ZABBIX'), $form);

$data = array();
if (isset($_REQUEST['form'])) {
	$data['form'] = get_request('form', 1);
	$data['form_refresh'] = get_request('form_refresh', 0);
	$data['valuemapid'] = get_request('valuemapid');
	$data['valuemap'] = array();
	$data['mapname'] = '';
	$data['title'] = '';
	$data['confirmMessage'] = null;
	$data['add_value'] = get_request('add_value');
	$data['add_newvalue'] = get_request('add_newvalue');

	if (!empty($data['valuemapid'])) {
		$db_valuemap = DBfetch(DBselect('SELECT v.name FROM valuemaps v WHERE v.valuemapid = '.$data['valuemapid']));
		$data['mapname'] = $db_valuemap['name'];
		$data['title'] = ' "'.$data['mapname'].'"';

		if (empty($data['form_refresh'])) {
			$db_mappings = DBselect('SELECT m.value, m.newvalue FROM mappings m WHERE m.valuemapid = '.$data['valuemapid']);
			while ($mapping = DBfetch($db_mappings)) {
				$data['valuemap'][] = array(
					'value' => $mapping['value'],
					'newvalue' => $mapping['newvalue']
				);
			}
		}
		else {
			$data['mapname'] = get_request('mapname', '');
			$data['valuemap'] = get_request('valuemap', array());
		}

		$valuemap_count = DBfetch(DBselect('SELECT COUNT(i.itemid) as cnt FROM items i WHERE i.valuemapid='.$data['valuemapid']));
		if ($valuemap_count['cnt']) {
			$data['confirmMessage'] = _n('Delete selected value mapping? It is used for %d item!', 'Delete selected value mapping? It is used for %d items!', $valuemap_count['cnt']);
		}
		else {
			$data['confirmMessage'] = _('Delete selected value mapping?');
		}
	}

	if (empty($data['valuemapid']) && !empty($data['form_refresh'])) {
		$data['mapname'] = get_request('mapname', '');
		$data['valuemap'] = get_request('valuemap', array());
	}

	order_result($data['valuemap'], 'value');

	$valueMappingForm = new CView('administration.general.valuemapping.edit', $data);
}
else {
	$cnf_wdgt->addHeader(_('Value mapping'));
	$data['valuemaps'] = array();

	$db_valuemaps = DBselect('SELECT v.valuemapid, v.name FROM valuemaps v WHERE '.DBin_node('valuemapid'));
	while ($db_valuemap = DBfetch($db_valuemaps)) {
		$data['valuemaps'][$db_valuemap['valuemapid']] = $db_valuemap;
		$data['valuemaps'][$db_valuemap['valuemapid']]['maps'] = array();
	}

	$db_maps = DBselect('SELECT m.valuemapid, m.value, m.newvalue FROM mappings m WHERE '.DBin_node('mappingid'));
	while ($db_map = DBfetch($db_maps)) {
		$data['valuemaps'][$db_map['valuemapid']]['maps'][] = array(
			'value' => $db_map['value'],
			'newvalue' => $db_map['newvalue']
		);
	}
	order_result($data['valuemaps'], 'name');

	$valueMappingForm = new CView('administration.general.valuemapping.list', $data);
}

$cnf_wdgt->addItem($valueMappingForm->render());
$cnf_wdgt->show();

require_once('include/page_footer.php');
?>
