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
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('User groups');
$page['file'] = 'popup_usrgrp.php';

define('ZBX_PAGE_NO_MENU', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=[
		'dstfrm'=>		[T_ZBX_STR, O_MAND,	P_SYS,	NOT_EMPTY,	NULL],
		'new_groups'=>	[T_ZBX_STR, O_OPT,		P_SYS,	NOT_EMPTY,	NULL],

		'select'=>		[T_ZBX_STR, O_OPT,		P_SYS|P_ACT,	NULL,	NULL]
	];

	check_fields($fields);

// destination form
	$dstfrm	= getRequest('dstfrm',	0);
	$new_groups = getRequest('new_groups', []);
?>
<script language="JavaScript" type="text/javascript">
<!--
function add_var_to_opener_obj(obj,name,value){
        new_variable = window.opener.document.createElement('input');
        new_variable.type = 'hidden';
        new_variable.name = name;
        new_variable.value = value;

        obj.appendChild(new_variable);
}
-->
</script>
<?php

	if(isset($_REQUEST['select']) && count($new_groups) > 0){
?>
<script language="JavaScript" type="text/javascript">
form = window.opener.document.forms['<?php echo $dstfrm; ?>'];
<!--
<?php
		foreach($new_groups as $id){
			echo 'add_var_to_opener_obj(form,"new_groups['.$id.']","'.$id.'")'."\r";
		}
?>
if(form){
	form.submit();
	close_window();
}
-->
</script>
<?php
	}

	$form = (new CForm())
		->setName('groups')
		->addVar('dstfrm', $dstfrm);

	$table = (new CTableInfo())
		->setHeader([
			(new CColHeader(
				(new CCheckBox('all_groups'))->onClick("checkAll('".$form->getName()."','all_groups','new_groups');")
			))->addClass(ZBX_STYLE_CELL_WIDTH),
			_('Name')
		]);

	$userGroups = DBfetchArray(DBselect('SELECT ug.usrgrpid,ug.name FROM usrgrp ug'));

	order_result($userGroups, 'name');

	foreach ($userGroups as $userGroup) {
		$table->addRow([
			(new CCheckBox('new_groups['.$userGroup['usrgrpid'].']', $userGroup['usrgrpid']))
				->setChecked(isset($new_groups[$userGroup['usrgrpid']])),
			$userGroup['name']
		]);
	}

	$table->setFooter(new CCol(new CSubmit('select', _('Select'))));

	$form->addItem($table);

	(new CWidget())
		->setTitle($page['title'])
		->addItem($form)
		->show();

require_once dirname(__FILE__).'/include/page_footer.php';
