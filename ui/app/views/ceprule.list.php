<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$this->includeJsFile('ceprule.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Event processing'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CEPRULE_LIST))
	->setControls(
		(new CTag('nav', true, (new CList())
			->addItem((new CSimpleButton(_('Create complex event processing')))->setId('js-create-cep'))
			->addItem((new CSimpleButton(_('Create event correlation')))->setId('js-create'))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'ceprule.list'))
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
							->addValue(_('All'), -1)
							->addValue(_('Enabled'), ACTION_STATUS_ENABLED)
							->addValue(_('Disabled'), ACTION_STATUS_DISABLED)
							->setModern()
					)
				])
				->addItem([
					new CLabel(_('Type')),
					new CFormField(
						(new CRadioButtonList('filter_type', (int) $data['filter']['type']))
							->addValue(_('All'), CCepRuleHelper::FILTER_SHOW_ALL)
							->addValue(_('Complex event processing'), CCepRuleHelper::FILTER_SHOW_CEP)
							->addValue(_('Event correlation'), CCepRuleHelper::FILTER_SHOW_LEGACY)
							->setModern()
					)
				])
		])
		->addVar('action', 'ceprule.list')
	);

$form = (new CForm())->setName('ceprules-form');

$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'ceprule.list')
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader((new CCheckBox('all_items'))->onClick(
			sprintf('checkAll("%s", "all_items", "cepruleids")', $form->getName())
		)))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Type'),
		_('Conditions'),
		_('Time window processing'),
		_('Operations'),
		_('Stop after this rule'),
		make_sorting_header(_('Sort order'), 'sortorder', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		_('Info')
	])
	->setPageNavigation($data['paging']);

foreach ($data['ceprules'] as $ceprule) {
	$is_legacy = array_key_exists('correlationid', $ceprule);
	$conditions = [];
	$operations = [];

	foreach ($ceprule['filter']['conditions'] as $condition) {
		if ($is_legacy) {
			if (!array_key_exists('operator', $condition)) {
				$condition['operator'] = CONDITION_OPERATOR_EQUAL;
			}

			$conditions[] = CCorrelationHelper::getConditionDescription($condition, $data['group_names']);
			$conditions[] = BR();
		}
		else {
			$conditions[] = CCepRuleHelper::getConditionDescription($condition);
			$conditions[] = BR();
		}
	}

	foreach ($ceprule['operations'] as $operation) {
		if ($is_legacy) {
			$operations[] = CCorrelationHelper::getOperationTypes()[$operation['type']];
			$operations[] = BR();
		}
		else {
			$operations[] = CCepRuleHelper::getOperationDescription($operation);
			$operations[] = BR();
		}
	}

	if ($is_legacy) {
		$table->addRow([
			new CCheckBox('cepruleids[legacy-'.$ceprule['correlationid'].']', $ceprule['correlationid']),
			new CLink($ceprule['name'], (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'correlation.edit')
					->setArgument('correlationid', $ceprule['correlationid'])
					->getUrl()
				),
			_('Event correlation'),
			(new CCol($conditions))->addClass(ZBX_STYLE_WORDBREAK),
			'',
			(new CCol($operations))->addClass(ZBX_STYLE_WORDBREAK),
			'',
			'',
			(new CLink($ceprule['status'] == ZBX_CORRELATION_ENABLED ? _('Enabled') : _('Disabled')))
				->addClass($ceprule['status'] == ZBX_CORRELATION_ENABLED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass('js-toggle-disabled')
				->setAttribute('data-action', $ceprule['status'] == ZBX_CORRELATION_ENABLED
					? 'ceprule.disable'
					: 'ceprule.enable'
				)
				->setAttribute('data-id', 'legacy-'.$ceprule['correlationid']),
			$ceprule['error'] ? makeErrorIcon($ceprule['error']) : ''
		]);
	}
	else {
		$table->addRow([
			new CCheckBox('cepruleids['.$ceprule['cepruleid'].']', $ceprule['cepruleid']),
			new CLink($ceprule['name'], (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'ceprule.edit')
				->setArgument('cepruleid', $ceprule['cepruleid'])
				->getUrl()),
			_('Complex event processing'),
			(new CCol($conditions))->addClass(ZBX_STYLE_WORDBREAK),
			CCepRuleHelper::getWindowLabelString($ceprule),
			(new CCol($operations))->addClass(ZBX_STYLE_WORDBREAK),
			$ceprule['stop'] == CCepRuleHelper::EXECUTION_STOP
				? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
				: new CObject(),
			$ceprule['sortorder'],
			(new CLink($ceprule['status'] == CCepRuleHelper::STATUS_ENABLED ? _('Enabled') : _('Disabled')))
				->addClass($ceprule['status'] == CCepRuleHelper::STATUS_ENABLED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass('js-toggle-disabled')
				->setAttribute('data-action', $ceprule['status'] == CCepRuleHelper::STATUS_ENABLED
					? 'ceprule.disable'
					: 'ceprule.enable'
				)
				->setAttribute('data-id', $ceprule['cepruleid']),
			$ceprule['error'] ? makeErrorIcon($ceprule['error']) : ''
		]);
	}
}

$form->addItem([
	$table,
	new CActionButtonList('action', 'cepruleids', [
		[
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massenable')
		],
		[
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdisable')
		],
		[
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdelete')
		]
	], 'ceprules')
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
