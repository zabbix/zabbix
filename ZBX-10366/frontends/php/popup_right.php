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

$page['title'] = _('Resource');
$page['file'] = 'popup_right.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR					TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'dstfrm' =>		array(T_ZBX_STR, O_MAND,P_SYS, NOT_EMPTY,	null),
	'permission' =>	array(T_ZBX_INT, O_MAND,P_SYS, IN(PERM_DENY.','.PERM_READ.','.PERM_READ_WRITE), null),
	'nodeid' =>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID,		null)
);
check_fields($fields);

$dstfrm = getRequest('dstfrm', 0);
$permission = getRequest('permission', PERM_DENY);

$nodeId = null;
$availableNodeIds = null;

if (ZBX_DISTRIBUTED) {
	$nodeId = getRequest('nodeid', CProfile::get('web.popup_right.nodeid.last', get_current_nodeid(false)));
	$availableNodeIds = get_accessible_nodes_by_user(CWebUser::$data, PERM_READ, PERM_RES_IDS_ARRAY);

	$profileNodeId = $nodeId;

	if (!isset($availableNodeIds[$nodeId])) {
		$nodeId = null;

		if ($nodeId != 0) {
			$profileNodeId = null;
		}
	}

	CProfile::update('web.popup_right.nodeid.last', $profileNodeId, PROFILE_TYPE_ID);
}

/*
 * Display
 */
// node combobox
$titleFrom = new CForm();
$titleFrom->addVar('dstfrm', $dstfrm);
$titleFrom->addVar('permission', $permission);

if ($availableNodeIds !== null) {
	$nodeComboBox = new CComboBox('nodeid', $nodeId, 'submit();');
	$nodeComboBox->addItem(0, _('All'));

	$nodes = DBselect('SELECT n.name,n.nodeid FROM nodes n WHERE '.dbConditionInt('n.nodeid', $availableNodeIds));

	while ($node = DBfetch($nodes)) {
		$nodeComboBox->addItem($node['nodeid'], $node['name']);
	}

	$titleFrom->addItem(array(_('Node'), SPACE, $nodeComboBox));
}

show_table_header(permission2str($permission), $titleFrom);

// host groups
$hostGroupForm = new CForm();
$hostGroupForm->setAttribute('id', 'groups');

$hostGroupTable = new CTableInfo(_('No host groups found.'));
$hostGroupTable->setHeader(new CCol(array(
	new CCheckBox('all_groups', null, 'checkAll(this.checked)'),
	_('Name')
)));

$hostGroups = API::HostGroup()->get(array(
	'output' => array('groupid', 'name'),
	'nodeids' => ($nodeId === null) ? $availableNodeIds : $nodeId
));

if ($availableNodeIds && $nodeId === null) {
	foreach ($hostGroups as $key => $hostGroup) {
		$hostGroups[$key]['name'] = get_node_name_by_elid($hostGroup['groupid'], true, NAME_DELIMITER).
			$hostGroup['name'];
	}
}

order_result($hostGroups, 'name');

foreach ($hostGroups as $hostGroup) {
	$hostGroupCheckBox = new CCheckBox();
	$hostGroupCheckBox->setAttribute('data-id', $hostGroup['groupid']);
	$hostGroupCheckBox->setAttribute('data-name', $hostGroup['name']);
	$hostGroupCheckBox->setAttribute('data-permission', $permission);

	$hostGroupTable->addRow(new CCol(array($hostGroupCheckBox, $hostGroup['name'])));
}

$hostGroupTable->setFooter(new CCol(new CButton('select', _('Select'), 'addGroups("'.$dstfrm.'")'), 'right'));

$hostGroupForm->addItem($hostGroupTable);
$hostGroupForm->show();

?>
<script type="text/javascript">
	function addGroups(formName) {
		var parentDocument = window.opener.document;

		if (!parentDocument) {
			return close_window();
		}

		jQuery('#groups input[type=checkbox]').each(function() {
			var obj = jQuery(this);

			if (obj.attr('name') !== 'all_groups' && obj.prop('checked')) {
				var id = obj.data('id');

				add_variable('input', 'new_right[' + id + '][permission]', obj.data('permission'), formName,
					parentDocument);
				add_variable('input', 'new_right[' + id + '][name]', obj.data('name'), formName, parentDocument);
			}
		});

		parentDocument.forms[formName].submit();

		close_window();
	}

	function checkAll(value) {
		jQuery('#groups input[type=checkbox]').each(function() {
			jQuery(this).prop('checked', value);
		});
	}
</script>
<?php

require_once dirname(__FILE__).'/include/page_footer.php';
