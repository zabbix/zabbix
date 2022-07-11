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

require_once dirname(__FILE__).'/js/configuration.item.list.js.php';

$widget = (new CWidget())
	->setTitle(_('Items'))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				($data['hostid'] != 0)
					? new CRedirectButton(_('Create item'), (new CUrl('items.php'))
						->setArgument('form', 'create')
						->setArgument('hostid', $data['hostid'])
						->setArgument('context', $data['context'])
						->getUrl()
					)
					: (new CButton('form',
						($data['context'] === 'host')
							? _('Create item (select host first)')
							: _('Create item (select template first)')
					))->setEnabled(false)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['hostid'] != 0) {
	$widget->setNavigation(getHostNavigation('items', $data['hostid']));
}

$widget->addItem(new CPartial('configuration.filter.items', [
	'filter_data' => $data['filter_data'],
	'subfilter' => $data['subfilter'],
	'context' => $data['context']
]));

$url = (new CUrl('items.php'))
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$itemForm = (new CForm('post', $url))
	->setName('items')
	->addVar('checkbox_hash', $data['checkbox_hash'])
	->addVar('context', $data['context'], 'form_context');

if (!empty($data['hostid'])) {
	$itemForm->addVar('hostid', $data['hostid']);
}

// create table
$itemTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		'',
		($data['hostid'] == 0)
			? ($data['context'] === 'host')
				? _('Host')
				: _('Template')
			: null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Triggers'),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('History'), 'history', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Trends'), 'trends', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		_('Tags'),
		($data['context'] === 'host') ? _('Info') : null
	]);

$current_time = time();

$data['itemTriggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['itemTriggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression'],
	'context' => $data['context']
]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

foreach ($data['items'] as $item) {
	// description
	$description = [];
	$description[] = makeItemTemplatePrefix($item['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_NORMAL,
		$data['allowed_ui_conf_templates']
	);

	if (!empty($item['discoveryRule'])) {
		$description[] = (new CLink(CHtml::encode($item['discoveryRule']['name']),
			(new CUrl('disc_prototypes.php'))
				->setArgument('parent_discoveryid', $item['discoveryRule']['itemid'])
				->setArgument('context', $data['context'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		if ($item['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = CHtml::encode($item['master_item']['name']);
		}
		else {
			$description[] = (new CLink(CHtml::encode($item['master_item']['name']),
				(new CUrl('items.php'))
					->setArgument('form', 'update')
					->setArgument('hostid', $item['hostid'])
					->setArgument('itemid', $item['master_item']['itemid'])
					->setArgument('context', $data['context'])
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(CHtml::encode($item['name']),
		(new CUrl('items.php'))
			->setArgument('form', 'update')
			->setArgument('hostid', $item['hostid'])
			->setArgument('itemid', $item['itemid'])
			->setArgument('context', $data['context'])
	);

	// status
	$status = new CCol((new CLink(
			itemIndicator($item['status'], $item['state']),
			(new CUrl('items.php'))
				->setArgument('group_itemid[]', $item['itemid'])
				->setArgument('hostid', $item['hostid'])
				->setArgument('action', ($item['status'] == ITEM_STATUS_DISABLED)
					? 'item.massenable'
					: 'item.massdisable'
				)
				->setArgument('context', $data['context'])
				->getUrl()
		))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($item['status'], $item['state']))
		->addSID()
	);

	// triggers info
	$triggerHintTable = (new CTableInfo())->setHeader([_('Severity'), _('Name'), _('Expression'), _('Status')]);

	$backurl = (new CUrl('items.php'))
		->setArgument('context', $data['context'])
		->getUrl();

	foreach ($item['triggers'] as $num => &$trigger) {
		$trigger = $data['itemTriggers'][$trigger['triggerid']];

		$trigger_description = [];
		$trigger_description[] = makeTriggerTemplatePrefix($trigger['triggerid'], $data['trigger_parent_templates'],
			ZBX_FLAG_DISCOVERY_NORMAL, $data['allowed_ui_conf_templates']
		);

		$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

		$trigger_description[] = new CLink(
			CHtml::encode($trigger['description']),
			(new CUrl('triggers.php'))
				->setArgument('form', 'update')
				->setArgument('hostid', key($trigger['hosts']))
				->setArgument('triggerid', $trigger['triggerid'])
				->setArgument('context', $data['context'])
				->setArgument('backurl', $backurl)
		);

		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$trigger['error'] = '';
		}

		if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
			$expression = [
				_('Problem'), ': ', $trigger['expression'], BR(),
				_('Recovery'), ': ', $trigger['recovery_expression']
			];
		}
		else {
			$expression = $trigger['expression'];
		}

		$triggerHintTable->addRow([
			CSeverityHelper::makeSeverityCell((int) $trigger['priority']),
			$trigger_description,
			(new CDiv($expression))->addClass(ZBX_STYLE_WORDWRAP),
			(new CSpan(triggerIndicator($trigger['status'], $trigger['state'])))
				->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		]);
	}
	unset($trigger);

	if ($triggerHintTable->getNumRows()) {
		$triggerInfo = (new CLinkAction(_('Triggers')))->setHint($triggerHintTable);
		$triggerInfo = [$triggerInfo];
		$triggerInfo[] = CViewHelper::showNum($triggerHintTable->getNumRows());

		$triggerHintTable = [];
	}
	else {
		$triggerInfo = '';
	}

	$wizard = (new CButton(null))
		->addClass(ZBX_STYLE_ICON_WIZARD_ACTION)
		->setMenuPopup(CMenuPopupHelper::getItemConfiguration([
			'itemid' => $item['itemid'],
			'context' => $data['context'],
			'backurl' => $backurl
		]));

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
		$item['trends'] = '';
	}

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
			|| $item['type'] == ITEM_TYPE_DEPENDENT
			|| ($item['type'] == ITEM_TYPE_ZABBIX_ACTIVE && strncmp($item['key_'], 'mqtt.get', 8) === 0)) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	// info
	if ($data['context'] === 'host') {
		$info_icons = [];

		if ($item['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($item['error'])) {
			$info_icons[] = makeErrorIcon($item['error']);
		}

		// discovered item lifetime indicator
		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['itemDiscovery']['ts_delete'] != 0) {
			$info_icons[] = getItemLifetimeIndicator($current_time, $item['itemDiscovery']['ts_delete']);
		}
	}

	$itemTable->addRow([
		new CCheckBox('group_itemid['.$item['itemid'].']', $item['itemid']),
		$wizard,
		($data['hostid'] == 0) ? $item['host'] : null,
		(new CCol($description))->addClass(ZBX_STYLE_WORDBREAK),
		$triggerInfo,
		(new CDiv(CHtml::encode($item['key_'])))->addClass(ZBX_STYLE_WORDWRAP),
		$item['delay'],
		$item['history'],
		$item['trends'],
		item_type2str($item['type']),
		$status,
		$data['tags'][$item['itemid']],
		($data['context'] === 'host') ? makeInformationList($info_icons) : null
	]);
}

$button_list = [
	'item.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected items?')],
	'item.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected items?')]
];

if ($data['context'] === 'host') {
	$massclearhistory = [
		'name' => _('Clear history'),
		'confirm' => _('Delete history of selected items?'),
		'disabled' => $data['is_template']
	];

	if ($data['config']['compression_status']) {
		unset($massclearhistory['confirm']);
	}

	$button_list += [
		'item.masscheck_now' => ['name' => _('Execute now'), 'disabled' => $data['is_template']],
		'item.massclearhistory' => $massclearhistory
	];
}

$button_list += [
	'item.masscopyto' => ['name' => _('Copy')],
	'popup.massupdate.item' => [
		'content' => (new CButton('', _('Mass update')))
			->onClick(
				"openMassupdatePopup('popup.massupdate.item', {}, {
					dialogue_class: 'modal-popup-preprocessing',
					trigger_element: this
				});"
			)
			->addClass(ZBX_STYLE_BTN_ALT)
			->removeAttribute('id')
	],
	'item.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected items?')]
];

// Append table to form.
$itemForm->addItem([$itemTable, $data['paging'], new CActionButtonList('action', 'group_itemid', $button_list,
	$data['checkbox_hash']
)]);

// Append form to widget.
$widget->addItem($itemForm);

$widget->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
