<?php
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

$this->addJsFile('items.js');
$this->addJsFile('multilineinput.js');
$this->includeJsFile('trigger.prototype.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows($data['parent_discoveryid']);
}

$html_page = (new CHtmlPage())
	->setTitle(_('Trigger prototypes'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_TRIGGER_PROTOTYPE_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATES_TRIGGER_PROTOTYPE_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem((new CButton('create_trigger',_('Create trigger prototype')))->setId('js-create'))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'trigger.prototype.list')
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

$trigger_form = (new CForm('post', $url))
	->setName('trigger_form')
	->addVar('parent_discoveryid', $data['parent_discoveryid'], 'form_parent_discoveryid')
	->addVar('context', $data['context'], 'form_context');

$trigger_table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_triggers'))->onClick("checkAll('".$trigger_form->getName()."', 'all_triggers', 'g_triggerid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Severity'), 'priority', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Name'), 'description', $data['sort'], $data['sortorder'], $url),
		_('Operational data'),
		_('Expression'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url),
		_('Tags')
	])
	->setPageNavigation($data['paging']);

$data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['triggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression'],
	'context' => $data['context']
]);

$csrf_token = CCsrfTokenHelper::get('trigger');

foreach ($data['triggers'] as $trigger) {
	$triggerid = $trigger['triggerid'];
	$trigger['discoveryRuleid'] = $data['parent_discoveryid'];

	$description = [];
	$description[] = makeTriggerTemplatePrefix($trigger['triggerid'], $data['parent_templates'],
		ZBX_FLAG_DISCOVERY_PROTOTYPE, $data['allowed_ui_conf_templates']
	);

	$trigger_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'trigger.prototype.edit')
		->setArgument('parent_discoveryid', $data['parent_discoveryid'])
		->setArgument('triggerid', $triggerid)
		->setArgument('context', $data['context']);

	$description[] = (new CLink($trigger['description'], $trigger_url))
		->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
		->setAttribute('data-triggerid', $triggerid)
		->setAttribute('data-context', $data['context'])
		->setAttribute('data-action', 'trigger.prototype.edit');

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$trigger_dependencies = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$dep_trigger = $data['dependencyTriggers'][$dependency['triggerid']];

			$dep_trigger_description =
				implode(', ', array_column($dep_trigger['hosts'], 'name')).NAME_DELIMITER.$dep_trigger['description'];

			if ($dep_trigger['flags'] == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$dep_trigger_prototype_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'trigger.prototype.edit')
					->setArgument('triggerid', $dep_trigger['triggerid'])
					->setArgument('context', $data['context'])
					->setArgument('parent_discoveryid', $data['parent_discoveryid'])
					->getUrl();

				$trigger_dependencies[] = (new CLink($dep_trigger_description, $dep_trigger_prototype_url))
					->addClass(triggerIndicatorStyle($dep_trigger['status']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->setAttribute('data-action', 'trigger.prototype.edit')
					->setAttribute('data-triggerid', $dep_trigger['triggerid'])
					->setAttribute('data-context', $data['context'])
					->setAttribute('data-parent_discoveryid', $data['parent_discoveryid']);
			}
			elseif ($dep_trigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				$dep_trigger_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'trigger.edit')
					->setArgument('triggerid', $dep_trigger['triggerid'])
					->setArgument('context', $data['context'])
					->getUrl();

				$trigger_dependencies[] = (new CLink($dep_trigger_description, $dep_trigger_url))
					->addClass(triggerIndicatorStyle($dep_trigger['status']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->setAttribute('data-action', 'trigger.edit')
					->setAttribute('data-triggerid', $dep_trigger['triggerid'])
					->setAttribute('data-context', $data['context']);
			}

			$trigger_dependencies[] = BR();
		}
		array_pop($trigger_dependencies);

		$description = array_merge($description, [(new CDiv($trigger_dependencies))->addClass('dependencies')]);
	}

	$status = (new CLink(
		($trigger['status'] == TRIGGER_STATUS_DISABLED) ? _('No') : _('Yes')))
			->setAttribute('data-triggerid', $triggerid)
			->setAttribute('data-status', ($trigger['status'] == TRIGGER_STATUS_DISABLED)
				? TRIGGER_STATUS_ENABLED
				: TRIGGER_STATUS_DISABLED
			)
			->addClass(($trigger['status'] == TRIGGER_STATUS_DISABLED) ? 'js-enable-trigger' : 'js-disable-trigger')
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(triggerIndicatorStyle($trigger['status']));

	$nodiscover = ($trigger['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
	$discover = (new CLink($nodiscover ? _('No') : _('Yes')))
		->setAttribute('data-triggerid', $triggerid)
		->setAttribute('data-discover', $nodiscover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
		->addClass($nodiscover ? 'js-enable-trigger' : 'js-disable-trigger')
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

	if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
		$expression = [
			_('Problem'), ': ', $trigger['expression'], BR(),
			_('Recovery'), ': ', $trigger['recovery_expression']
		];
	}
	else {
		$expression = $trigger['expression'];
	}

	$checkBox = new CCheckBox('g_triggerid['.$triggerid.']', $triggerid);

	$trigger_table->addRow([
		$checkBox,
		CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
		(new CCol($description))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($trigger['opdata']))->addClass(ZBX_STYLE_WORDBREAK),
		(new CDiv($expression))->addClass(ZBX_STYLE_WORDBREAK),
		$status,
		$discover,
		$data['tags'][$triggerid]
	]);
}

// append table to form
$trigger_form->addItem([
	$trigger_table,
	new CActionButtonList('action', 'g_triggerid',
		[
			'trigger.prototype.massenable' => [
				'content' => (new CSimpleButton(_('Create enabled')))
					->setAttribute('data-status', TRIGGER_STATUS_ENABLED)
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-no-chkbxrange')
					->setId('js-massenable-trigger')
			],
			'trigger.prototype.massdisable' => [
				'content' => (new CSimpleButton(_('Create disabled')))
					->setAttribute('data-status', TRIGGER_STATUS_DISABLED)
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-no-chkbxrange')
					->setId('js-massdisable-trigger')
			],
			'trigger.prototype.massupdate' => [
				'content' => (new CSimpleButton(_('Mass update')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->setId('js-massupdate-trigger')
			],
			'trigger.prototype.massdelete' => [
				'content' => (new CSimpleButton(_('Delete')))
					->addClass(ZBX_STYLE_BTN_ALT)
					->addClass('js-no-chkbxrange')
					->setId('js-massdelete-trigger')
			]
		],
		$this->data['parent_discoveryid']
	)
]);

$html_page
	->addItem($trigger_form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'context' => $data['context'],
		'hostid' => $data['hostid'],
		'parent_discoveryid' => $data['parent_discoveryid'],
		'token' => [CSRF_TOKEN_NAME => CCsrfTokenHelper::get('trigger')]
	]).');
'))
	->setOnDocumentReady()
	->show();
