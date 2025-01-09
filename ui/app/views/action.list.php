<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('action.list.js.php');

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

$html_page = (new CHtmlPage())
	->setTitle($title)
	->setTitleSubmenu(['main_section' => ['items' => $submenu]])
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ALERTS_ACTION_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Create action')))->addClass('js-action-create')
			)
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

$current_url->removeArgument('filter_rst');

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('action')))->removeId())
	->setId('action-list')
	->setName('action_list');

$action_list = (new CTableInfo())
	->setHeader([
		(new CColHeader((new CCheckBox('all_actions'))
			->onClick("checkAll('".$form->getName()."', 'all_actions', 'actionids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $current_url->getUrl()),
		_('Conditions'),
		_('Operations'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $current_url->getUrl())
	])
	->setPageNavigation($data['paging']);

if ($data['actions']) {
	$actionConditionStringValues = $data['actionConditionStringValues'];

	foreach ($data['actions'] as $aIdx => $action) {
		$conditions = [];

		foreach ($action['filter']['conditions'] as $cIdx => $condition) {
			$conditions[] = getConditionDescription($condition['conditiontype'], $condition['operator'],
				$actionConditionStringValues[$aIdx][$cIdx], $condition['value2']
			);
			$conditions[] = BR();
		}

		$operations = getActionOperationDescriptions(
			$data['actions'][$aIdx]['operations'], $data['eventsource'], $data['operation_descriptions']
		);

		$status = $action['status'] == ACTION_STATUS_ENABLED
			? (new CLink(_('Enabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addClass('js-disable-action')
				->setAttribute('data-actionid', $action['actionid'])
			: (new CLink(_('Disabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addClass('js-enable-action')
				->setAttribute('data-actionid', $action['actionid']);

		$action_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'action.edit')
			->setArgument('actionid', $action['actionid'])
			->setArgument('eventsource', $data['eventsource'])
			->getUrl();

		$action_list->addRow([
			new CCheckBox('actionids['.$action['actionid'].']', $action['actionid']),
			(new CCol(
				(new CLink($action['name'], $action_url))
					->setAttribute('data-actionid', $action['actionid'])
					->setAttribute('data-eventsource', $data['eventsource'])
					->setAttribute('data-action', 'action.edit')
			))->addClass(ZBX_STYLE_WORDBREAK),
			(new CCol($conditions))->addClass(ZBX_STYLE_WORDBREAK),
			(new CCol($operations))->addClass(ZBX_STYLE_WORDBREAK),
			$status
		]);
	}
}

$form->addItem([
	$action_list,
	new CActionButtonList('action', 'actionids', [
		'action.massenable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-action')
				->addClass('js-no-chkbxrange')
		],
		'action.massdisable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-action')
				->addClass('js-no-chkbxrange')
		],
		'action.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-action')
				->addClass('js-no-chkbxrange')
		]
	], 'action_'.$data['eventsource'])
]);

$html_page
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'eventsource' => $data['eventsource']
	]).');
'))
	->setOnDocumentReady()
	->show();
