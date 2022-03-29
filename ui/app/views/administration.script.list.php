<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 */

if ($data['uncheck']) {
	uncheckTableRows('script');
}

$widget = (new CWidget())
	->setTitle(_('Scripts'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_SCRIPT_LIST))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create script'), 'zabbix.php?action=script.edit'))
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'script.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Scope'),
				(new CRadioButtonList('filter_scope', (int) $data['filter']['scope']))
					->addValue(_('Any'), -1)
					->addValue(_('Action operation'), ZBX_SCRIPT_SCOPE_ACTION)
					->addValue(_('Manual host action'), ZBX_SCRIPT_SCOPE_HOST)
					->addValue(_('Manual event action'), ZBX_SCRIPT_SCOPE_EVENT)
					->setModern(true)
			)
		])
		->addVar('action', 'script.list')
	);

$scriptsForm = (new CForm())
	->setName('scriptsForm')
	->setId('scripts');

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'script.list')
	->getUrl();

$scriptsTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_scripts'))
				->onClick("checkAll('".$scriptsForm->getName()."', 'all_scripts', 'scriptids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Scope'),
		_('Used in actions'),
		_('Type'),
		_('Execute on'),
		make_sorting_header(_('Commands'), 'command', $data['sort'], $data['sortorder'], $url),
		_('User group'),
		_('Host group'),
		_('Host access')
	]);

foreach ($data['scripts'] as $script) {
	$actions = [];

	switch ($script['scope']) {
		case ZBX_SCRIPT_SCOPE_ACTION:
			$scope = _('Action operation');

			if ($script['actions']) {
				$i = 0;

				foreach ($script['actions'] as $action) {
					$i++;

					if ($i > $data['config']['max_in_table']) {
						$actions[] = ' &hellip;';

						break;
					}

					if ($actions) {
						$actions[] = ', ';
					}

					$has_access = $action['eventsource'] == EVENT_SOURCE_SERVICE
						? CWebUser::checkAccess(CRoleHelper::UI_SERVICES_ACTIONS)
						: CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS);

					if ($has_access) {
						$url = (new CUrl('actionconf.php'))
							->setArgument('eventsource', $action['eventsource'])
							->setArgument('form', 'update')
							->setArgument('actionid', $action['actionid']);

						$actions[] = (new CLink($action['name'], $url))
							->addClass(ZBX_STYLE_LINK_ALT)
							->addClass(ZBX_STYLE_GREY);
					}
					else {
						$actions[] = (new CSpan($action['name']))->addClass(ZBX_STYLE_GREY);
					}
				}
			}
			break;

		case ZBX_SCRIPT_SCOPE_HOST:
			$scope = _('Manual host action');
			break;

		case ZBX_SCRIPT_SCOPE_EVENT:
			$scope = _('Manual event action');
			break;
	}

	switch ($script['type']) {
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			$type = _('Script');
			break;

		case ZBX_SCRIPT_TYPE_IPMI:
			$type = _('IPMI');
			break;

		case ZBX_SCRIPT_TYPE_SSH:
			$type = _('SSH');
			break;

		case ZBX_SCRIPT_TYPE_TELNET:
			$type = _('Telnet');
			break;

		case ZBX_SCRIPT_TYPE_WEBHOOK:
			$type = _('Webhook');
			break;
	}

	if ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) {
		switch ($script['execute_on']) {
			case ZBX_SCRIPT_EXECUTE_ON_AGENT:
				$execute_on = _('Agent');
				break;

			case ZBX_SCRIPT_EXECUTE_ON_SERVER:
				$execute_on = _('Server');
				break;

			case ZBX_SCRIPT_EXECUTE_ON_PROXY:
				$execute_on = _('Server (proxy)');
				break;
		}
	}
	else {
		$execute_on = '';
	}

	$scriptsTable->addRow([
		new CCheckBox('scriptids['.$script['scriptid'].']', $script['scriptid']),
		(new CCol(
			new CLink($script['name'], 'zabbix.php?action=script.edit&scriptid='.$script['scriptid'])
		))->addClass(ZBX_STYLE_NOWRAP),
		$scope,
		$actions,
		$type,
		$execute_on,
		(new CCol(
			zbx_nl2br(htmlspecialchars($script['command'], ENT_COMPAT, 'UTF-8'))
		))->addClass(ZBX_STYLE_MONOSPACE_FONT),
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
$widget
	->addItem($scriptsForm)
	->show();
