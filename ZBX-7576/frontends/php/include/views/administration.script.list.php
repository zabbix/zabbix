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

$scriptsWidget = new CWidget();

$createForm = new CForm('get');
$createForm->addItem(new CSubmit('form', _('Create script')));

$scriptsWidget->addPageHeader(_('CONFIGURATION OF SCRIPTS'), $createForm);
$scriptsWidget->addHeader(_('Scripts'));
$scriptsWidget->addHeaderRowNumber();

$scriptsForm = new CForm();
$scriptsForm->setName('scriptsForm');
$scriptsForm->setAttribute('id', 'scripts');

$scriptsTable = new CTableInfo(_('No scripts defined.'));
$scriptsTable->setHeader(array(
	new CCheckBox('all_scripts', null, "checkAll('".$scriptsForm->getName()."', 'all_scripts', 'scripts');"),
	make_sorting_header(_('Name'), 'name'),
	_('Type'),
	_('Execute on'),
	make_sorting_header(_('Commands'), 'command'),
	_('User group'),
	_('Host group'),
	_('Host access')
));

foreach ($this->data['scripts'] as $script) {
	$scriptid = $script['scriptid'];

	switch ($script['type']) {
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

	if ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) {
		switch ($script['execute_on']) {
			case ZBX_SCRIPT_EXECUTE_ON_AGENT:
				$scriptExecuteOn = _('Agent');
				break;
			case ZBX_SCRIPT_EXECUTE_ON_SERVER:
				$scriptExecuteOn = _('Server');
				break;
		}
	}
	else {
		$scriptExecuteOn = '';
	}

	$scriptsTable->addRow(array(
		new CCheckBox('scripts['.$script['scriptid'].']', 'no', null, $script['scriptid']),
		new CLink($script['name'], 'scripts.php?form=1&scriptid='.$script['scriptid']),
		$scriptType,
		$scriptExecuteOn,
		zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8')),
		('' == $script['userGroupName']) ? _('All') : $script['userGroupName'],
		('' == $script['hostGroupName']) ? _('All') : $script['hostGroupName'],
		((PERM_READ_WRITE == $script['host_access']) ? _('Write') : _('Read'))
	));
}

// create go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected scripts?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "scripts";');

// append table to form
$scriptsForm->addItem(array($this->data['paging'], $scriptsTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$scriptsWidget->addItem($scriptsForm);
return $scriptsWidget;
