<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	->setTitle(_('Event correlation'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem(new CSubmit('form', _('Create correlation')))
		)
	)
	->addItem((new CFilter('web.correlation.filter.state'))
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

$form = (new CForm())->setName('correlation_form');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))
				->onClick("checkAll('".$form->getName()."', 'all_items', 'g_correlationid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder']),
		_('Conditions'),
		_('Operations'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'])
	]);

if ($data['correlations']) {
	/*
	 * If condition type is "New event host group", then to avoid performance drops, call this function first in order
	 * to read all host groups before each iteration.
	 */
	$correlation_condition_string_values = corrConditionValueToString($data['correlations']);

	foreach ($data['correlations'] as $i => $correlation) {
		$conditions = [];
		$operations = [];

		order_result($correlation['filter']['conditions'], 'type', ZBX_SORT_DOWN);

		foreach ($correlation['filter']['conditions'] as $j => $condition) {
			if (!array_key_exists('operator', $condition)) {
				$condition['operator'] = CONDITION_OPERATOR_EQUAL;
			}

			$conditions[] = getCorrConditionDescription($condition, $correlation_condition_string_values[$i][$j]);
			$conditions[] = BR();
		}

		CArrayHelper::sort($correlation['operations'], ['type']);

		foreach ($correlation['operations'] as $operation) {
			$operations[] = getCorrOperationDescription($operation);
			$operations[] = BR();
		}

		if ($correlation['status'] == ZBX_CORRELATION_DISABLED) {
			$status = (new CLink(_('Disabled'),
				'correlation.php?action=correlation.massenable&g_correlationid[]='.$correlation['correlationid'])
			)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID();
		}
		else {
			$status = (new CLink(_('Enabled'),
				'correlation.php?action=correlation.massdisable&g_correlationid[]='.$correlation['correlationid'])
			)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID();
		}

		$table->addRow([
			new CCheckBox('g_correlationid['.$correlation['correlationid'].']', $correlation['correlationid']),
			new CLink($correlation['name'], 'correlation.php?form=update&correlationid='.$correlation['correlationid']),
			$conditions,
			$operations,
			$status
		]);
	}
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'g_correlationid', [
		'correlation.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected correlations?')],
		'correlation.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected correlations?')],
		'correlation.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected correlations?')]
	])
]);

$widget->addItem($form);

return $widget;
