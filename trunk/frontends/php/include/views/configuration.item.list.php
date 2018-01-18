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

require_once dirname(__FILE__).'/js/configuration.item.list.js.php';

if (empty($this->data['hostid'])) {
	$create_button = (new CSubmit('form', _('Create item (select host first)')))->setEnabled(false);
}
else {
	$create_button = new CSubmit('form', _('Create item'));
}

$widget = (new CWidget())
	->setTitle(_('Items'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addVar('hostid', $this->data['hostid'])
		->addItem((new CList())->addItem($create_button))
	);

if (!empty($this->data['hostid'])) {
	$widget->addItem(get_header_host_table('items', $this->data['hostid']));
}
$widget->addItem($this->data['flicker']);

// create form
$itemForm = (new CForm())->setName('items');
if (!empty($this->data['hostid'])) {
	$itemForm->addVar('hostid', $this->data['hostid']);
}

// create table
$itemTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))->onClick("checkAll('".$itemForm->getName()."', 'all_items', 'group_itemid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Wizard'),
		empty($this->data['filter_hostid']) ? _('Host') : null,
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Triggers'),
		make_sorting_header(_('Key'), 'key_', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Interval'), 'delay', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('History'), 'history', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Trends'), 'trends', $this->data['sort'], $this->data['sortorder']),
		make_sorting_header(_('Type'), 'type', $this->data['sort'], $this->data['sortorder']),
		_('Applications'),
		make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
		$data['showInfoColumn'] ? _('Info') : null
	]);

if (!$this->data['filterSet']) {
	$itemTable->setNoDataMessage(_('Specify some filter condition to see the items.'));
}

$current_time = time();

$this->data['itemTriggers'] = CMacrosResolverHelper::resolveTriggerExpressions($this->data['itemTriggers'], [
	'html' => true,
	'sources' => ['expression', 'recovery_expression']
]);

$update_interval_parser = new CUpdateIntervalParser(['usermacros' => true]);

foreach ($this->data['items'] as $item) {
	// description
	$description = [];
	if (!empty($item['template_host'])) {
		if (array_key_exists($item['template_host']['hostid'], $data['writable_templates'])) {
			$description[] = (new CLink(CHtml::encode($item['template_host']['name']),
				'?hostid='.$item['template_host']['hostid'].'&filter_set=1'
			))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$description[] = (new CSpan(CHtml::encode($item['template_host']['name'])))->addClass(ZBX_STYLE_GREY);
		}

		$description[] = NAME_DELIMITER;
	}

	if (!empty($item['discoveryRule'])) {
		$description[] = (new CLink(CHtml::encode($item['discoveryRule']['name']),
			'disc_prototypes.php?parent_discoveryid='.$item['discoveryRule']['itemid']
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER;
	}

	if ($item['type'] == ITEM_TYPE_DEPENDENT) {
		$description[] = (new CLink(CHtml::encode($item['master_item']['name_expanded']),
			'?form=update&hostid='.$item['hostid'].'&itemid='.$item['master_item']['itemid']
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_TEAL);
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
	if ($data['showInfoColumn']) {
		$info_icons = [];

		if ($item['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($item['error'])) {
			$info_icons[] = makeErrorIcon($item['error']);
		}

		// discovered item lifetime indicator
		if ($item['flags'] == ZBX_FLAG_DISCOVERY_CREATED && $item['itemDiscovery']['ts_delete'] != 0) {
			$info_icons[] = getItemLifetimeIndicator($current_time, $item['itemDiscovery']['ts_delete']);
		}
	}

	// triggers info
	$triggerHintTable = (new CTableInfo())
		->setHeader([
			_('Severity'),
			_('Name'),
			_('Expression'),
			_('Status')
		]);

	foreach ($item['triggers'] as $num => &$trigger) {
		$trigger = $this->data['itemTriggers'][$trigger['triggerid']];
		$trigger_description = [];

		if ($trigger['templateid'] > 0) {
			if (!isset($this->data['triggerRealHosts'][$trigger['triggerid']])) {
				$trigger_description[] = (new CSpan('HOST'))->addClass(ZBX_STYLE_GREY);
				$trigger_description[] = ':';
			}
			else {
				$realHost = reset($this->data['triggerRealHosts'][$trigger['triggerid']]);

				if (array_key_exists($realHost['hostid'], $data['writable_templates'])) {
					$trigger_description[] = (new CLink(CHtml::encode($realHost['name']),
						'triggers.php?hostid='.$realHost['hostid']
					))->addClass(ZBX_STYLE_GREY);
				}
				else {
					$trigger_description[] = (new CSpan(CHtml::encode($realHost['name'])))->addClass(ZBX_STYLE_GREY);
				}

				$trigger_description[] = ':';
			}
		}

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

		$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

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
			getSeverityCell($trigger['priority'], $this->data['config']),
			$trigger_description,
			$expression,
			(new CSpan(triggerIndicator($trigger['status'], $trigger['state'])))
				->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		]);

		$item['triggers'][$num] = $trigger;
	}
	unset($trigger);

	if (!empty($item['triggers'])) {
		$triggerInfo = (new CSpan(_('Triggers')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->setHint($triggerHintTable);
		$triggerInfo = [$triggerInfo];
		$triggerInfo[] = CViewHelper::showNum(count($item['triggers']));

		$triggerHintTable = [];
	}
	else {
		$triggerInfo = SPACE;
	}

	$item_menu = CMenuPopupHelper::getDependentItem($item['itemid'], $item['hostid'], $item['name']);

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT])) {
		$triggers = [];

		foreach ($item['triggers'] as $trigger) {
			if ($trigger['recovery_mode'] == ZBX_RECOVERY_MODE_RECOVERY_EXPRESSION) {
				continue;
			}

			foreach ($trigger['functions'] as $function) {
				if (!str_in_array($function['function'], ['regexp', 'iregexp'])) {
					continue 2;
				}
			}

			$triggers[] = [
				'id' => $trigger['triggerid'],
				'name' => $trigger['description']
			];
		}

		$trigger_menu = CMenuPopupHelper::getTriggerLog($item['itemid'], $item['name'], $triggers);
		$trigger_menu['dependent_items'] = $item_menu;
		$item_menu = $trigger_menu;
	}

	if (in_array($item['value_type'], [ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT])) {
		$item['trends'] = '';
	}

	// Hide zeroes for trapper, SNMP trap and dependent items.
	if ($item['type'] == ITEM_TYPE_TRAPPER || $item['type'] == ITEM_TYPE_SNMPTRAP
			|| $item['type'] == ITEM_TYPE_DEPENDENT) {
		$item['delay'] = '';
	}
	elseif ($update_interval_parser->parse($item['delay']) == CParser::PARSE_SUCCESS) {
		$item['delay'] = $update_interval_parser->getDelay();
	}

	$wizard = (new CSpan(
		(new CButton(null))->addClass(ZBX_STYLE_ICON_WZRD_ACTION)->setMenuPopup($item_menu)
	))->addClass(ZBX_STYLE_REL_CONTAINER);

	$itemTable->addRow([
		new CCheckBox('group_itemid['.$item['itemid'].']', $item['itemid']),
		$wizard,
		empty($this->data['filter_hostid']) ? $item['host'] : null,
		$description,
		$triggerInfo,
		CHtml::encode($item['key_']),
		$item['delay'],
		$item['history'],
		$item['trends'],
		item_type2str($item['type']),
		CHtml::encode($item['applications_list']),
		$status,
		$data['showInfoColumn'] ? makeInformationList($info_icons) : null
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$itemForm->addItem([
	$itemTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_itemid',
		[
			'item.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected items?')],
			'item.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected items?')],
			'item.massclearhistory' => ['name' => _('Clear history'),
				'confirm' => _('Delete history of selected items?')
			],
			'item.masscopyto' => ['name' => _('Copy')],
			'item.massupdateform' => ['name' => _('Mass update')],
			'item.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected items?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($itemForm);

return $widget;
