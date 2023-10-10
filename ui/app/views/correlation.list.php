<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

$this->includeJsFile('correlation.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Event correlation'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CORRELATION_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Create event correlation')))->setId('js-create')
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'correlation.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Name'), 'filter_name'),
					new CFormField(
						(new CTextBox('filter_name', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAttribute('autofocus', 'autofocus')
					)
				]),
			(new CFormGrid())
				->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
				->addItem([
					new CLabel(_('Status')),
					new CFormField(
						(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
							->addValue(_('Any'), -1)
							->addValue(_('Enabled'), ACTION_STATUS_ENABLED)
							->addValue(_('Disabled'), ACTION_STATUS_DISABLED)
							->setModern()
					)
				])
		])
		->addVar('action', 'correlation.list')
	);

$form = (new CForm())->setName('correlations-form');

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'correlation.list')
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_items'))
				->onClick("checkAll('".$form->getName()."', 'all_items', 'correlationids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Conditions'),
		_('Operations'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url)
	]);


foreach ($data['correlations'] as $correlation) {
	$conditions = [];
	$operations = [];

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

	$status = ($correlation['status'] == ZBX_CORRELATION_ENABLED)
		? (new CLink(_('Enabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addClass('js-disable')
			->setAttribute('data-correlationid', (int) $correlation['correlationid'])
		: (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable')
			->setAttribute('data-correlationid', (int) $correlation['correlationid']);

	$table->addRow([
		new CCheckBox('correlationids['.$correlation['correlationid'].']', $correlation['correlationid']),
		(new CLink($correlation['name']))
			->addClass('js-edit')
			->setAttribute('data-correlationid', $correlation['correlationid']),
		$conditions,
		$operations,
		$status
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'correlationids', [
		'correlation.enable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massenable')
				->addClass('no-chkbxrange')
		],
		'correlation.disable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdisable')
				->addClass('no-chkbxrange')
		],
		'correlation.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdelete')
				->addClass('no-chkbxrange')
		]
	], 'correlation')
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
