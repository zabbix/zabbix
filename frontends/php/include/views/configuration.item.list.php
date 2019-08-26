<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
						->getUrl()
					)
					: (new CButton('form', _('Create item (select host first)')))->setEnabled(false)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

if ($data['hostid'] != 0) {
	$widget->addItem(get_header_host_table('items', $data['hostid']));
}
$widget->addItem($data['main_filter']);

// create form
$itemForm = (new CForm())->setName('items');
$itemForm->addVar('checkbox_hash', $data['checkbox_hash']);
if (!empty($data['hostid'])) {
	$itemForm->addVar('hostid', $data['hostid']);
}

$url = (new CUrl('items.php'))->getUrl();

// create table
$itemTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Wizard'),
		($data['hostid'] == 0) ? _('Host') : null,
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Triggers'),
		make_sorting_header(_('Key'), 'key_', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Interval'), 'delay', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('History'), 'history', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Trends'), 'trends', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder'], $url),
		_('Applications'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		_('Info')
	]);

$current_time = time();

$data['itemTriggers'] = CMacrosResolverHelper::resolveTriggerExpressions($data['itemTriggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression']
]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

foreach ($data['items'] as $item) {
	// description
	$description = [];
	$description[] = makeItemTemplatePrefix($item['itemid'], $data['parent_templates'], ZBX_FLAG_DISCOVERY_NORMAL);

	if (!empty($item['discoveryRule'])) {
		$description[] = (new CLink(CHtml::encode($item['discoveryRule']['name']),
			(new CUrl('disc_prototypes.php'))->setArgument('parent_discoveryid', $item['discoveryRule']['itemid'])
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		if ($item['master_item']['type'] == ITEM_TYPE_HTTPTEST) {
			$description[] = CHtml::encode($item['master_item']['name_expanded']);
		}
		else {
			$description[] = (new CLink(CHtml::encode($item['master_item']['name_expanded']),
				'?form=update&hostid='.$item['hostid'].'&itemid='.$item['master_item']['itemid']
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_TEAL);
		}

		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink(CHtml::encode($item['name_expanded']),
		'?form=update&hostid='.$item['hostid'].'&itemid='.$item['itemid']
	);

	// status
	$status = new CCol((new CLink(
		itemIndicator($item['status'], $item['state']),
		'?group_itemid[]='.$item['itemid'].
			'&hostid='.$item['hostid'].
			'&action='.($item['status'] == ITEM_STATUS_DISABLED ? 'item.massenable' : 'item.massdisable')))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($item['status'], $item['state']))
		->addSID()
	);

	// info
	$info_icons = [];

	if ($item['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($item['error'])) {
		$info_icons[] = makeErrorIcon($item['error']);
	}

	// discovered item lifetime indicator
	if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['itemDiscovery']['ts_delete'] != 0) {
		$info_icons[] = getItemLifetimeIndicator($current_time, $item['itemDiscovery']['ts_delete']);
	}

	// triggers info
	$triggerHintTable = (new CTableInfo())->setHeader([_('Severity'), _('Name'), _('Expression'), _('Status')]);

	foreach ($item['triggers'] as $num => &$trigger) {
		$trigger = $data['itemTriggers'][$trigger['triggerid']];

		$trigger_description = [];
		$trigger_description[] = makeTriggerTemplatePrefix($trigger['triggerid'], $data['trigger_parent_templates'],
			ZBX_FLAG_DISCOVERY_NORMAL
		);

		$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

		if ($trigger['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
			$trigger_description[] = new CSpan(CHtml::encode($trigger['description']));
		}
		else {
			$trigger_description[] = new CLink(
				CHtml::encode($trigger['description']),
				'triggers.php?form=update&hostid='.key($trigger['hosts']).'&triggerid='.$trigger['triggerid']
			);
		}

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
			getSeverityCell($trigger['priority'], $data['config']),
			$trigger_description,
			$expression,
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

	$wizard = (new CSpan(
		(new CButton(null))
			->addClass(ZBX_STYLE_ICON_WZRD_ACTION)
			->setMenuPopup(CMenuPopupHelper::getItem($item['itemid']))
	))->addClass(ZBX_STYLE_REL_CONTAINER);

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
		$item['trends'] = '';
	}
	else if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
		$wizard = '';
	}

	// Hide zeros for trapper, SNMP trap and dependent items.
	if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
			|| $item['type'] == ITEM_TYPE_DEPENDENT) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	$itemTable->addRow([
		new CCheckBox('group_itemid['.$item['itemid'].']', $item['itemid']),
		$wizard,
		($data['hostid'] == 0) ? $item['host'] : null,
		$description,
		$triggerInfo,
		CHtml::encode($item['key_']),
		$item['delay'],
		$item['history'],
		$item['trends'],
		item_type2str($item['type']),
		CHtml::encode($item['applications_list']),
		$status,
		makeInformationList($info_icons)
	]);
}

// append table to form
$itemForm->addItem([
	$itemTable,
	$data['paging'],
	new CActionButtonList('action', 'group_itemid',
		[
			'item.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected items?')],
			'item.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected items?')],
			'item.masscheck_now' => ['name' => _('Check now')],
			'item.massclearhistory' => ['name' => _('Clear history'),
				'confirm' => _('Delete history of selected items?')
			],
			'item.masscopyto' => ['name' => _('Copy')],
			'item.massupdateform' => ['name' => _('Mass update')],
			'item.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected items?')]
		],
		$data['checkbox_hash']
	)
]);

// append form to widget
$widget->addItem($itemForm);

return $widget;
