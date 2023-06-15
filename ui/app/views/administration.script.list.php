<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @var array $data
 */

$this->includeJsFile('administration.script.list.js.php');
$this->addJsFile('multilineinput.js');

if ($data['uncheck']) {
	uncheckTableRows('script');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Scripts'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ALERTS_SCRIPT_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Create script')))->setId('js-create')
			)
		))
		->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'script.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Name'), 'filter_name'),
					new CFormField(
						(new CTextBox('filter_name', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAttribute('autofocus', 'autofocus')
					)
				]),
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Scope')),
					new CFormField(
						(new CRadioButtonList('filter_scope', (int) $data['filter']['scope']))
							->addValue(_('Any'), -1)
							->addValue(_('Action operation'), ZBX_SCRIPT_SCOPE_ACTION)
							->addValue(_('Manual host action'), ZBX_SCRIPT_SCOPE_HOST)
							->addValue(_('Manual event action'), ZBX_SCRIPT_SCOPE_EVENT)
							->setModern()
					)
				])
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
						$actions[] = [' ', HELLIP()];

						break;
					}

					if ($actions) {
						$actions[] = ', ';
					}

					switch ($action['eventsource']) {
						case EVENT_SOURCE_TRIGGERS:
							$has_access = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);
							break;
						case EVENT_SOURCE_SERVICE:
							$has_access = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
							break;
						case EVENT_SOURCE_DISCOVERY:
							$has_access = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);
							break;
						case EVENT_SOURCE_AUTOREGISTRATION:
							$has_access = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);
							break;
						case EVENT_SOURCE_INTERNAL:
							$has_access = CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);
							break;
					}

					if ($has_access) {
						$actions[] = (new CLink($action['name']))
							->addClass('js-action-edit')
							->setAttribute('data-actionid', $action['actionid'])
							->setAttribute('data-eventsource', $action['eventsource'])
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

		case ZBX_SCRIPT_TYPE_URL:
			$type = _('URL');
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

	$link = (new CLink($script['name']))
		->addClass('js-edit')
		->setAttribute('data-scriptid', $script['scriptid']);

	$scriptsTable->addRow([
		new CCheckBox('scriptids['.$script['scriptid'].']', $script['scriptid']),
		(new CCol($script['menu_path'] === '' ? $link : [$script['menu_path'].'/', $link]))->addClass(ZBX_STYLE_NOWRAP),
		$scope,
		$actions,
		$type,
		$execute_on,
		(new CCol(zbx_nl2br($script['command'])))->addClass(ZBX_STYLE_MONOSPACE_FONT),
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
		'script.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdelete')
				->addClass('js-no-chkbxrange')
		]
	], 'script')
]);

// append form to widget
$html_page
	->addItem($scriptsForm)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
