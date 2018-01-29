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


$widget = (new CWidget())
	->setTitle(_('Actions'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem([
				new CLabel(_('Event source'), 'eventsource'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				new CComboBox('eventsource', $data['eventsource'], 'submit()', [
					EVENT_SOURCE_TRIGGERS => _('Triggers'),
					EVENT_SOURCE_DISCOVERY => _('Discovery'),
					EVENT_SOURCE_AUTO_REGISTRATION => _('Auto registration'),
					EVENT_SOURCE_INTERNAL => _x('Internal', 'event source')
				])
			])
			->addItem(new CSubmit('form', _('Create action')))
		)
	)
	->addItem((new CFilter('web.actionconf.filter.state'))
		->addColumn((new CFormList())->addRow(_('Name'),
			(new CTextBox('filter_name', $data['filter']['name']))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		))
		->addColumn((new CFormList())->addRow(_('Status'),
			(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
				->addValue(_('Any'), -1)
				->addValue(_('Enabled'), ACTION_STATUS_ENABLED)
				->addValue(_('Disabled'), ACTION_STATUS_DISABLED)
				->setModern(true)
		))
	);

// create form
$actionForm = (new CForm())->setName('actionForm');

// create table
$actionTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))
				->onClick("checkAll('".$actionForm->getName()."', 'all_items', 'g_actionid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
		_('Conditions'),
		_('Operations'),
		make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
	]);

if ($this->data['actions']) {
	$actionConditionStringValues = actionConditionValueToString($this->data['actions'], $this->data['config']);
	$actionOperationDescriptions = getActionOperationDescriptions($this->data['actions'], ACTION_OPERATION);

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
				'actionconf.php?action=action.massenable&g_actionid[]='.$action['actionid'].url_param('eventsource'))
			)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID();
		}
		else {
			$status = (new CLink(_('Enabled'),
				'actionconf.php?action=action.massdisable&g_actionid[]='.$action['actionid'].url_param('eventsource'))
			)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID();
		}

		$actionTable->addRow([
			new CCheckBox('g_actionid['.$action['actionid'].']', $action['actionid']),
			new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']),
			$conditions,
			$operations,
			$status
		]);
	}
}

// append table to form
$actionForm->addItem([
	$actionTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_actionid', [
		'action.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected actions?')],
		'action.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected actions?')],
		'action.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected actions?')]
	])
]);

// append form to widget
$widget->addItem($actionForm);

return $widget;
