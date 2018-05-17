<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	uncheckTableRows('script');
}

$widget = (new CWidget())
	->setTitle(_('Scripts'))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create script'), 'zabbix.php?action=script.edit'))
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setProfile('web.scripts.filter', 0)
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
		])
		->addVar('action', 'script.list')
	);

$scriptsForm = (new CForm())
	->setName('scriptsForm')
	->setId('scripts');

$scriptsTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_scripts'))->onClick("checkAll('".$scriptsForm->getName()."', 'all_scripts', 'scriptids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
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
			case ZBX_SCRIPT_EXECUTE_ON_PROXY:
				$scriptExecuteOn = _('Server (proxy)');
				break;
		}
	}
	else {
		$scriptExecuteOn = '';
	}

	$scriptsTable->addRow([
		new CCheckBox('scriptids['.$script['scriptid'].']', $script['scriptid']),
		(new CCol(
			new CLink($script['name'], 'zabbix.php?action=script.edit&scriptid='.$script['scriptid'])
		))->addClass(ZBX_STYLE_NOWRAP),
		$scriptType,
		$scriptExecuteOn,
		zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8')),
		($script['userGroupName'] === null) ? _('All') : $script['userGroupName'],
		($script['hostGroupName'] === null) ? _('All') : $script['hostGroupName'],
		($script['host_access'] == PERM_READ_WRITE) ? _('Write') : _('Read')
	]);
}

// append table to form
$scriptsForm->addItem([
	$scriptsTable,
	$data['paging'],
	new CActionButtonList('action', 'scriptids', [
		'script.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected scripts?')]
	], 'script')
]);

// append form to widget
$widget->addItem($scriptsForm)->show();
