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


if ($data['uncheck']) {
	uncheckTableRows();
}

$scriptsWidget = (new CWidget())->setTitle(_('Scripts'));

$createForm = new CForm('get');
$controls = new CList();
$controls->addItem(new CRedirectButton(_('Create script'), 'zabbix.php?action=script.edit'));

$createForm->addItem($controls);
$scriptsWidget->setControls($createForm);

$scriptsForm = new CForm();
$scriptsForm->setName('scriptsForm');
$scriptsForm->setAttribute('id', 'scripts');

$scriptsTable = new CTableInfo();
$scriptsTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_scripts', null, "checkAll('".$scriptsForm->getName()."', 'all_scripts', 'scriptids');")))->
		addClass('cell-width'),
	make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder']),
	_('Type'),
	_('Execute on'),
	make_sorting_header(_('Commands'), 'command', $data['sort'], $data['sortorder']),
	_('User group'),
	_('Host group'),
	_('Host access')
]);

foreach ($data['scripts'] as $script) {
	switch ($script['type']) {
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			$scriptType = _('Script');
			break;
		case ZBX_SCRIPT_TYPE_IPMI:
			$scriptType = _('IPMI');
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

	$name = new CLink($script['name'], 'zabbix.php?action=script.edit&scriptid='.$script['scriptid']);

	$scriptsTable->addRow([
		new CCheckBox('scriptids['.$script['scriptid'].']', 'no', null, $script['scriptid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		$scriptType,
		$scriptExecuteOn,
		zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8')),
		$script['userGroupName'] === null ?  _('All') : $script['userGroupName'],
		$script['hostGroupName'] === null ?  _('All') : $script['hostGroupName'],
		($script['host_access'] == PERM_READ_WRITE) ? _('Write') : _('Read')
	]);
}

// append table to form
$scriptsForm->addItem([
	$scriptsTable,
	$data['paging'],
	new CActionButtonList('action', 'scriptids', [
		'script.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected scripts?')]
	])
]);

// append form to widget
$scriptsWidget->addItem($scriptsForm)->show();
