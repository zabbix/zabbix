<?php declare(strict_types = 0);
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
 * @var array $data
 */

require_once __DIR__ .'/js/action.list.js.php';

$submenu_source = [];

if (CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS)) {
	$submenu_source[EVENT_SOURCE_TRIGGERS] = _('Trigger actions');
}

if (CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS)) {
	$submenu_source[EVENT_SOURCE_SERVICE] = _('Service actions');
}

if (CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS)) {
	$submenu_source[EVENT_SOURCE_DISCOVERY] = _('Discovery actions');
}

if (CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS)) {
	$submenu_source[EVENT_SOURCE_AUTOREGISTRATION] = _('Autoregistration actions');
}

if (CWebUser::checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS)) {
	$submenu_source[EVENT_SOURCE_INTERNAL] = _('Internal actions');
}

$title = $submenu_source[$data['eventsource']];
$submenu = [];

foreach ($submenu_source as $value => $label) {
	$url = (new CUrl('zabbix.php'))
		->setArgument('action', 'action.list')
		->setArgument('eventsource', $value)
		->getUrl();
	$submenu[$url] = $label;
}

$current_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'action.list')
	->setArgument('eventsource', $data['eventsource']);

$widget = (new CWidget())
	->setTitle($title)
	->setTitleSubmenu(['main_section' => ['items' => $submenu]])
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ALERTS_ACTION_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem((new CSimpleButton(_('Create action')))->addClass('js-action-create'))
		))->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CFilter())
	->setResetUrl($current_url)
	->addVar('action', 'action.list')
	->addVar('eventsource', $data['eventsource'])
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())->addItem([
			new CLabel(_('Name'), 'filter_name'),
			(new CTextBox('filter_name', $data['filter']['name']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		]),
		(new CFormGrid())->addItem([
			new CLabel(_('Status')),
			new CFormField(
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), ACTION_STATUS_ENABLED)
					->addValue(_('Disabled'), ACTION_STATUS_DISABLED)
					->setModern(true)
			)
		])
	]);

$widget->addItem($filter);
$current_url->removeArgument('filter_rst');

// create form
$actionForm = (new CForm())
	->setName('actionForm')
	->setAction($current_url->getUrl());

// create table
$actionTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))
				->onClick("checkAll('".$actionForm->getName()."', 'all_items', 'g_actionid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $current_url->getUrl()),
		_('Conditions'),
		_('Operations'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $current_url->getUrl())
	]);

if ($this->data['actions']) {
	$actionConditionStringValues = actionConditionValueToString($this->data['actions']);

	$actionOperationDescriptions = getActionOperationDescriptions($data['eventsource'], $data['actions'],
		ACTION_OPERATION
	);

	foreach ($this->data['actions'] as $aIdx => $action) {
		$conditions = [];
		$operations = [];

		order_result($action['filter']['conditions'], 'conditiontype');

		foreach ($action['filter']['conditions'] as $cIdx => $condition) {
			$conditions[] = getConditionDescription($condition['conditiontype'], $condition['operator'],
				$actionConditionStringValues[$aIdx][$cIdx], $condition['value2']
			);
			$conditions[] = BR();
		}

		sortOperations($data['eventsource'], $action['operations']);

		foreach ($action['operations'] as $oIdx => $operation) {
			$operations[] = $actionOperationDescriptions[$aIdx][$oIdx];
		}

		$status = ($action['status'] == ACTION_STATUS_ENABLED)
			? (new CLink(_('Enabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addClass('js-disable-action')
				->setAttribute('actionid', $action['actionid'])
			: (new CLink(_('Disabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addClass('js-enable-action')
				->setAttribute('actionid', $action['actionid']);

		$actionTable->addRow([
			new CCheckBox('g_actionid['.$action['actionid'].']', $action['actionid']),
			(new CLink($action['name']))
				->addClass('js-action-edit')
				->setAttribute('actionid', $action['actionid']),
			$conditions,
			$operations,
			$status
		]);
	}
}

$actionForm->addItem([
	$actionTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_actionid', [
		'action.massenable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-action')
				->addClass('no-chkbxrange')
		],
		'action.massdisable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-action')
				->addClass('no-chkbxrange')
		],
		'action.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-action')
				->addClass('no-chkbxrange')
		]
	], 'action_'.$data['eventsource'])
]);

$widget->addItem($actionForm);
$widget->show();

(new CScriptTag('
	view.init('.json_encode([
		'eventsource' => $data['eventsource'],
	]).');
'))
	->setOnDocumentReady()
	->show();
