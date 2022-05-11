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

if ($data['uncheck']) {
	uncheckTableRows('correlation');
}

$widget = (new CWidget())
	->setTitle(_('Event correlation'))
	->setControls((new CTag('nav', true,
		(new CForm('get'))
			->cleanItems()
			->addItem((new CList())
				->addItem(new CRedirectButton(_('Create correlation'), (new CUrl('zabbix.php'))
					->setArgument('action', 'correlation.edit'))
				)
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'correlation.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), ACTION_STATUS_ENABLED)
					->addValue(_('Disabled'), ACTION_STATUS_DISABLED)
					->setModern(true)
			)
		])
		->addVar('action', 'correlation.list')
	);

$form = (new CForm())->setName('correlation_form');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))
				->onClick("checkAll('".$form->getName()."', 'all_items', 'correlationids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], (new CUrl('zabbix.php'))
			->setArgument('action', 'correlation.list')
			->getUrl()
		),
		_('Conditions'),
		_('Operations'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], (new CUrl('zabbix.php'))
			->setArgument('action', 'correlation.list')
			->getUrl()
		)
	]);

if ($data['correlations']) {
	foreach ($data['correlations'] as $correlation) {
		$conditions = [];
		$operations = [];

		order_result($correlation['filter']['conditions'], 'type', ZBX_SORT_DOWN);

		foreach ($correlation['filter']['conditions'] as $condition) {
			if (!array_key_exists('operator', $condition)) {
				$condition['operator'] = CONDITION_OPERATOR_EQUAL;
			}

			$conditions[] = CCorrelationHelper::getConditionDescription($condition, $data['group_names']);
			$conditions[] = BR();
		}

		CArrayHelper::sort($correlation['operations'], ['type']);

		foreach ($correlation['operations'] as $operation) {
			$operations[] = CCorrelationHelper::getOperationTypes()[$operation['type']];
			$operations[] = BR();
		}

		if ($correlation['status'] == ZBX_CORRELATION_DISABLED) {
			$status = (new CLink(_('Disabled'), (new CUrl('zabbix.php'))
				->setArgument('correlationids', (array) $correlation['correlationid'])
				->setArgument('action', 'correlation.enable')
				->setArgumentSID()
				->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID();
		}
		else {
			$status = (new CLink(_('Enabled'), (new CUrl('zabbix.php'))
				->setArgument('correlationids', (array) $correlation['correlationid'])
				->setArgument('action', 'correlation.disable')
				->setArgumentSID()
				->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID();
		}

		$table->addRow([
			new CCheckBox('correlationids['.$correlation['correlationid'].']', $correlation['correlationid']),
			new CLink($correlation['name'], (new CUrl('zabbix.php'))
				->setArgument('correlationid', $correlation['correlationid'])
				->setArgument('action', 'correlation.edit')
			),
			$conditions,
			$operations,
			$status
		]);
	}
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'correlationids', [
		'correlation.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected correlations?')],
		'correlation.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected correlations?')],
		'correlation.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected correlations?')]
	], 'correlation')
]);

$widget->addItem($form);

$widget->show();
