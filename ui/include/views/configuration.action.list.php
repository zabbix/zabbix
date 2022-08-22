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
 * @var array $data
 */

require_once dirname(__FILE__).'/js/configuration.action.list.js.php';
$this->addJsFile('popup.condition.common.js');

if ($data['eventsource'] == EVENT_SOURCE_SERVICE) {
	$title = _('Service actions');
	$doc_url = CDocHelper::CONFIGURATION_SERVICES_ACTION_LIST;
}

	$submenu_source = [
		EVENT_SOURCE_TRIGGERS => _('Trigger actions'),
		EVENT_SOURCE_DISCOVERY => _('Discovery actions'),
		EVENT_SOURCE_AUTOREGISTRATION => _('Autoregistration actions'),
		EVENT_SOURCE_INTERNAL => _('Internal actions'),
		EVENT_SOURCE_SERVICE => _('Service actions')
	];

	$title = array_key_exists($data['eventsource'], $submenu_source) ? $submenu_source[$data['eventsource']] : null;
	$submenu = [];
	$doc_url = CDocHelper::CONFIGURATION_ACTION_LIST;

if ($data['eventsource'] == EVENT_SOURCE_SERVICE) {
	$doc_url = CDocHelper::CONFIGURATION_SERVICES_ACTION_LIST;
}

	foreach ($submenu_source as $value => $label) {
		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'action.list')
			->setArgument('eventsource', $value)
			->getUrl();
		$submenu[$url] = $label;
	}

$widget = (new CWidget())
	->setTitle($title)
	->setTitleSubmenu($submenu ? ['main_section' => ['items' => $submenu]] : null)
	->setDocUrl(CDocHelper::getUrl($doc_url))
	->setControls((new CTag('nav', true,
		(new CForm('get'))
			->cleanItems()
			->addItem(new CInput('hidden', 'eventsource', $data['eventsource']))
			->addItem(
				(new CSimpleButton(_('Create action')))
					->addClass('js-action-create')
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$current_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'action.list')
	->setArgument('eventsource', $data['eventsource']);

$filter = (new CFilter())
	->setResetUrl($current_url)
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
		]
	);

if (in_array($data['eventsource'], [0,1,2,3])) {
	$filter->addVar('action', 'action.list');
}

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

		order_result($action['filter']['conditions'], 'conditiontype', ZBX_SORT_DOWN);

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

		if ($action['status'] == ACTION_STATUS_DISABLED) {
			$status = (new CLink(_('Disabled'),
				'zabbix.php?action=action.enable&g_actionid[]='.$action['actionid'].url_param('eventsource'))
				)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID();
		}
		else {
			$status = (new CLink(_('Enabled'),
				'zabbix.php?action=action.disable&g_actionid[]='.$action['actionid'].url_param('eventsource'))
			)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID();
		}

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
		'action.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected actions?')],
		'action.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected actions?')],
		'action.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->setAttribute('confirm', _('Delete selected actions?'))
				->addClass('action-delete')
				->addClass('no-chkbxrange')
				->removeId()
		]
	], 'g_actionid')
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
