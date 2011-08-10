<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	$scripts_wdgt = new CWidget();

	$frmForm = new CForm('get');
	$frmForm->addItem(new CSubmit('form', _('Create script')));
	$scripts_wdgt->addPageHeader(_('CONFIGURATION OF SCRIPTS'), $frmForm);

	$scripts_wdgt->addHeader(_('SCRIPTS'));
	$numrows = new CDiv();
	$numrows->setAttribute('name','numrows');
	$scripts_wdgt->addHeader($numrows);

	$form = new CForm();
	$form->setName('frm_scripts');
	$form->setAttribute('id', 'scripts');

	$table = new CTableInfo(_('No scripts defined.'));
	$table->setHeader(array(
		new CCheckBox('all_scripts', null, "checkAll('".$form->getName()."','all_scripts','scripts');"),
		make_sorting_header(_('Name'), 'name'),
		_('Type'),
		_('Execute on'),
		make_sorting_header(_('Commands'), 'command'),
		_('User group'),
		_('Host group'),
		_('Host access')
	));

	$sortfield = getPageSortField('name');
	$sortorder = getPageSortOrder();

	$scripts = $this->getArray('scripts');

// sorting
	order_result($scripts, $sortfield, $sortorder);
	$paging = getPagingLine($scripts);

	foreach($scripts as $snum => $script){
		$scriptid = $script['scriptid'];

		switch($script['type']){
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			$scriptType = _('Script');
			break;
		case ZBX_SCRIPT_TYPE_IPMI:
			$scriptType = _('IPMI');
			break;
		default:
			$scriptType = '';
			break;
		}

		if($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT){
			switch($script['execute_on']){
				case ZBX_SCRIPT_EXECUTE_ON_AGENT:
					$scriptExecuteOn = _('Agent');
					break;
				case ZBX_SCRIPT_EXECUTE_ON_SERVER:
					$scriptExecuteOn = _('Server');
					break;
			}
		}
		else{
			$scriptExecuteOn = '';
		}

		$table->addRow(array(
			new CCheckBox('scripts['.$script['scriptid'].']', 'no', NULL, $script['scriptid']),
			new CLink($script['name'], 'scripts.php?form=1'.'&scriptid='.$script['scriptid']),
			$scriptType,
			$scriptExecuteOn,
			zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8')),
			('' == $script['userGroupName']) ? _('All') : $script['userGroupName'],
			('' == $script['hostGroupName']) ? _('All') : $script['hostGroupName'],
			((PERM_READ_WRITE == $script['host_access']) ? _('Write') : _('Read'))
		));
	}


//----- GO ------
	$goBox = new CComboBox('go');
	$goOption = new CComboItem('delete', _('Delete selected'));
	$goOption->setAttribute('confirm', _('Delete selected scripts?'));
	$goBox->addItem($goOption);

// goButton name is necessary!!!
	$goButton = new CSubmit('goButton', _('Go'));
	$goButton->setAttribute('id','goButton');

	zbx_add_post_js('chkbxRange.pageGoName = "scripts";');

	$footer = get_table_header(array($goBox, $goButton));
//----

	$form->addItem(array($paging,$table,$paging,$footer));
	$scripts_wdgt->addItem($form);

	return $scripts_wdgt;
?>
