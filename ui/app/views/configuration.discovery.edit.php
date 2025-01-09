<?php declare(strict_types = 0);
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

// Create form.
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('discovery')))->removeId())
	->setId('discoveryForm')
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addStyle('display: none;');

if ($this->data['drule']['druleid'] !== null) {
	$form->addVar('druleid', $this->data['drule']['druleid']);
}

// Create form grid.
$form_grid = (new CFormGrid())
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $this->data['drule']['name']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
				->setAttribute('autofocus', 'autofocus')
		)
	])
	->addItem([
		new CLabel(_('Discovery by'), 'discovery_by'),
		new CFormField(
			(new CRadioButtonList('discovery_by', $data['discovery_by']))
				->addValue(_('Server'), ZBX_DISCOVERY_BY_SERVER)
				->addValue(_('Proxy'), ZBX_DISCOVERY_BY_PROXY)
				->setModern()
		)
	])
	->addItem(
		(new CFormField(
			(new CMultiSelect([
				'name' => 'proxyid',
				'object_name' => 'proxies',
				'multiple' => false,
				'data' => $data['ms_proxy'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'proxies',
						'srcfld1' => 'proxyid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'proxyid'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-field-proxy')
	)
	->addItem([
		(new CLabel(_('IP range'), 'iprange'))->setAsteriskMark(),
		new CFormField(
			(new CTextArea('iprange', $this->data['drule']['iprange'], ['maxlength' => 2048]))
				->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px')
				->setAriaRequired()
				->disableSpellcheck()
		)
	])
	->addItem([
		(new CLabel(_('Update interval'), 'delay'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('delay', $data['drule']['delay']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		new CLabel(_('Maximum concurrent checks per type'), 'concurrency_max_type'),
		(new CFormField([
			(new CDiv(
				(new CRadioButtonList('concurrency_max_type', (int) $data['concurrency_max_type']))
					->addValue(_('One'), ZBX_DISCOVERY_CHECKS_ONE)
					->addValue(_('Unlimited'), ZBX_DISCOVERY_CHECKS_UNLIMITED)
					->addValue(_('Custom'), ZBX_DISCOVERY_CHECKS_CUSTOM)
					->setModern()
			))->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CNumericBox('concurrency_max', $data['drule']['concurrency_max'], 3, false, false, false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->addClass(ZBX_STYLE_DISPLAY_NONE)
				->setAriaRequired()
		]))->addClass(ZBX_STYLE_NOWRAP)
	]);

$form_grid->addItem([
	(new CLabel(_('Checks'), 'dcheckList'))->setAsteriskMark(),
	(new CFormField(
		(new CTable())
			->setAttribute('style', 'width: 100%;')
			->setHeader([_('Type'), _('Actions')])
			->addItem(
				(new CTag('tfoot', true))
					->addItem(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('js-check-add')
						))->setColSpan(2)
					)
			)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('dcheckList')
]);

// Append uniqueness criteria to form list.
$form_grid->addItem([
	new CLabel(_('Device uniqueness criteria')),
	(new CFormField(
		(new CRadioButtonList('uniqueness_criteria', (int) $this->data['drule']['uniqueness_criteria']))
			->setId('device-uniqueness-list')
			->makeVertical()
			->addValue(_('IP address'), -1, zbx_formatDomId('uniqueness_criteria_ip'))
	))
		->setAttribute('style', 'width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
]);

$uniqueness_template = (new CTemplateTag('unique-row-tmpl'))->addItem(
	(new CListItem([
		(new CInput('radio', 'uniqueness_criteria', '#{dcheckid}'))
			->addClass(ZBX_STYLE_CHECKBOX_RADIO)
			->setId('uniqueness_criteria_#{dcheckid}'),
		(new CLabel([new CSpan(), '#{name}'], 'uniqueness_criteria_#{dcheckid}'))->addClass(ZBX_STYLE_WORDWRAP)
	]))->setId('uniqueness_criteria_row_#{dcheckid}')
);

// Append host source to form list.
$form_grid->addItem([
	new CLabel(_('Host name')),
	(new CFormField(
		(new CRadioButtonList('host_source', (int) $this->data['drule']['host_source']))
			->makeVertical()
			->addValue(_('DNS name'), ZBX_DISCOVERY_DNS, 'host_source_chk_dns')
			->addValue(_('IP address'), ZBX_DISCOVERY_IP, 'host_source_chk_ip')
			->setId('host_source')
	))
		->setAttribute('style', 'width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
]);

$host_source_template = (new CTemplateTag('host-source-row-tmpl'))->addItem(
	(new CListItem([
		(new CInput('radio', 'host_source', '_#{dcheckid}'))
			->addClass(ZBX_STYLE_CHECKBOX_RADIO)
			->setAttribute('data-id', '#{dcheckid}')
			->setId('host_source_#{dcheckid}'),
		(new CLabel([new CSpan(), '#{name}'], 'host_source_#{dcheckid}'))->addClass(ZBX_STYLE_WORDWRAP)
	]))->setId('host_source_row_#{dcheckid}')
);

// Append name source to form list.
$form_grid->addItem([
	new CLabel(_('Visible name')),
	(new CFormField(
		(new CRadioButtonList('name_source', (int) $this->data['drule']['name_source']))
			->makeVertical()
			->addValue(_('Host name'), ZBX_DISCOVERY_UNSPEC, 'name_source_chk_host')
			->addValue(_('DNS name'), ZBX_DISCOVERY_DNS, 'name_source_chk_dns')
			->addValue(_('IP address'), ZBX_DISCOVERY_IP, 'name_source_chk_ip')
			->setId('name_source')
	))
		->setAttribute('style', 'width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
]);

$name_source_template = (new CTemplateTag('name-source-row-tmpl'))->addItem(
	(new CListItem([
		(new CInput('radio', 'name_source', '_#{dcheckid}'))
			->addClass(ZBX_STYLE_CHECKBOX_RADIO)
			->setAttribute('data-id', '#{dcheckid}')
			->setId('name_source_#{dcheckid}'),
		(new CLabel([new CSpan(), '#{name}'], 'name_source_#{dcheckid}'))->addClass(ZBX_STYLE_WORDWRAP)
	]))->setId('name_source_row_#{dcheckid}')
);

$form_grid->addItem([
	new CLabel(_('Enabled'), 'status'),
	new CFormField((new CCheckBox('status', DRULE_STATUS_ACTIVE))
		->setUncheckedValue(DRULE_STATUS_DISABLED)
		->setChecked($this->data['drule']['status'] == DRULE_STATUS_ACTIVE)
	)
]);

$check_template_default = (new CTemplateTag('dcheck-row-tmpl'))->addItem(
	(new CRow([
		(new CCol('#{name}'))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle(ZBX_TEXTAREA_BIG_WIDTH)
			->setId('dcheckCell_#{dcheckid}'),
		new CHorList([
			(new CButtonLink(_('Edit')))->addClass('js-edit'),
			[
				(new CButtonLink(_('Remove')))->addClass('js-remove'),
				makeWarningIcon('#{warning}')
			]
		])
	]))
		->setId('dcheckRow_#{dcheckid}')
		->setAttribute('dcheckRow', '#{dcheckid}')
);

$form
	->addItem($form_grid)
	->addItem($check_template_default)
	->addItem($uniqueness_template)
	->addItem($host_source_template)
	->addItem($name_source_template)
	->addItem(
		(new CScriptTag('
			drule_edit_popup.init('.json_encode([
				'druleid' => $data['drule']['druleid'],
				'dchecks' => array_values($data['drule']['dchecks']),
				'drule' => $data['drule']
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

if ($data['drule']['druleid']) {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'drule_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'drule_edit_popup.clone('.json_encode([
					'title' => _('New discovery rule'),
					'buttons' => [
						[
							'title' => _('Add'),
							'class' => 'js-add',
							'keepOpen' => true,
							'isSubmit' => true,
							'action' => 'drule_edit_popup.submit();'
						],
						[
							'title' => _('Cancel'),
							'class' => ZBX_STYLE_BTN_ALT,
							'cancel' => true,
							'action' => ''
						]
					]
				]).');'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete discovery rule?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'drule_edit_popup.delete();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'class' => 'js-add',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'drule_edit_popup.submit();'
		]
	];
}

$output = [
	'header' => $data['drule']['druleid'] ? _('Discovery rule') : _('New discovery rule'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_DISCOVERY_EDIT),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().
		$this->readJsFile('configuration.discovery.edit.js.php'),
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
