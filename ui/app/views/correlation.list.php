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

$this->includeJsFile('correlation.list.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Event processing'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_CORRELATION_LIST))
	->setControls(
		(new CTag('nav', true, (new CList())
			->addItem((new CSimpleButton(_('Create complex event processing')))->setId('js-create-cep'))
			->addItem((new CSimpleButton(_('Create event correlation')))->setId('js-create'))
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
							->addValue(_('All'), ZBX_CEP_FILTER_SHOW_ALL)
							->addValue(_('Complex event processing'), ZBX_CEP_FILTER_SHOW_CEP)
							->addValue(_('Event correlation'), ZBX_CEP_FILTER_SHOW_LEGACY)
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
		_('Type'),
		_('Conditions'),
		_('Time window processing'),
		_('Operations'),
		_('Stop after this rule'),
		make_sorting_header(_('Sort order'), 'sortorder', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url)
	])
	->setPageNavigation($data['paging']);

foreach ($data['correlations'] as $correlation) {
	$is_legacy = array_key_exists('correlationid', $correlation);
	$conditions = [];
	$operations = [];

	foreach ($correlation['filter']['conditions'] as $condition) {
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

	CArrayHelper::sort($correlation['operations'], ['type']);

	foreach ($correlation['operations'] as $operation) {
		if ($is_legacy) {
			$operations[] = CCorrelationHelper::getOperationTypes()[$operation['type']];
			$operations[] = BR();
			continue;
		}
		else {
			$operations[] = CCepRuleHelper::getOperationDescription($operation);
			$operations[] = BR();
		}
	}

	$make_stop_toggle_button = function (array $correlation): CLink {
		if ($correlation['stop'] == ZBX_CEP_EXECUTION_CONTINUE) {
			return (new CLink(_('Enabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addClass('js-disable-stop')
				->setAttribute('data-cepruleid', (int) $correlation['cep_ruleid']);
		}

		return (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable-stop')
			->setAttribute('data-cepruleid', (int) $correlation['cep_ruleid']);
	};

	$make_status_toggle_button = function (array $ceprule, bool $is_legacy): CLink {
		if ($is_legacy) {
			return ($ceprule['status'] == ZBX_CORRELATION_ENABLED)
				? (new CLink(_('Enabled')))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->addClass(ZBX_STYLE_GREEN)
					->addClass('js-disable')
					->setAttribute('data-correlationid', (int) $ceprule['correlationid'])
				: (new CLink(_('Disabled')))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->addClass(ZBX_STYLE_RED)
					->addClass('js-enable')
					->setAttribute('data-correlationid', (int) $ceprule['correlationid']);
		}

		if ($ceprule['status'] == ZBX_CEP_STATUS_ENABLED) {
			return (new CLink(_('Enabled')))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addClass('js-disable')
				->setAttribute('data-cepruleid', (int) $ceprule['cep_ruleid']);
		}

		return (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable')
			->setAttribute('data-cepruleid', (int) $ceprule['cep_ruleid']);
	};

	$table->addRow([
		$is_legacy
			? new CCheckBox('correlationids['.$correlation['correlationid'].']', $correlation['correlationid'])
			: new CCheckBox('cepruleids['.$correlation['cep_ruleid'].']', $correlation['cep_ruleid']),
		$is_legacy
			? new CLink($correlation['name'], (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'correlation.edit')
				->setArgument('correlationid', $correlation['correlationid'])
				->getUrl()
			)
			: new CLink($correlation['name'], (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'ceprule.edit')
				->setArgument('cep_ruleid', $correlation['cep_ruleid'])
				->getUrl()),
		$is_legacy ? _('Event correlation') : _('Complex event processing'),
		$conditions,
		$is_legacy ? '' : CCepRuleHelper::getWindowLabelString($correlation),
		$operations,
		$is_legacy ? '' : $make_stop_toggle_button($correlation),
		$is_legacy ? '' : $correlation['sortorder'],
		$make_status_toggle_button($correlation, $is_legacy)
	]);
}

$form->addItem([
	$table,
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
