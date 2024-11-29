<?php
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

$this->includeJsFile('configuration.httpconf.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Web monitoring'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_HTTPCONF_EDIT));

// Append host summary to widget header.
if ($data['hostid'] != 0) {
	$html_page->setNavigation(getHostNavigation('web', $data['hostid']));
}

$url = (new CUrl('httpconf.php'))
	->setArgument('context', $data['context'])
	->getUrl();

$form = (new CForm('post', $url))
	->addItem((new CVar('form_refresh', $data['form_refresh'] + 1))->removeId())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('httpconf.php')))->removeId())
	->setId('webscenario-form')
	->setName('webscenario_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid'])
	->addVar('templated', $data['templated']);

if ($data['httptestid'] != 0) {
	$form->addVar('httptestid', $data['httptestid']);
}

// Scenario tab.
$scenario_tab = new CFormGrid();

if ($data['templates']) {
	$scenario_tab->addItem([
		new CLabel(_('Parent web scenarios')),
		new CFormField($data['templates'])
	]);
}

$name_text_box = (new CTextBox('name', $data['name'], $data['templated'], DB::getFieldLength('httptest', 'name')))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired()
	->setAttribute('autofocus', 'autofocus');

$scenario_tab
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField($name_text_box)
	])
	->addItem([
		(new CLabel(_('Update interval'), 'delay'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('delay', $data['delay'], false, DB::getFieldLength('httptest', 'delay')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Attempts'), 'retries'))->setAsteriskMark(),
		new CFormField(
			(new CNumericBox('retries', $data['retries'], 2))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
				->setAriaRequired()
		)
	]);

$agent_select = (new CSelect('agent'))
	->setId('agent')
	->setFocusableElementId('agent-focusable')
	->setValue($data['agent']);

$user_agents_all = userAgents();
$user_agents_all[_('Others')][ZBX_AGENT_OTHER] = _('other').' ...';

foreach ($user_agents_all as $user_agent_group => $user_agents) {
	$agent_select->addOptionGroup((new CSelectOptionGroup($user_agent_group))
		->addOptions(CSelect::createOptionsFromArray($user_agents))
	);
}

$scenario_tab
	->addItem([
		new CLabel(_('Agent'), $agent_select->getFocusableElementId()),
		new CFormField($agent_select)
	])
	->addItem([
		(new CLabel(_('User agent string'), 'agent_other'))->addClass('js-field-agent-other'),
		(new CFormField(
			(new CTextBox('agent_other', $data['agent_other'], false, DB::getFieldLength('httptest', 'agent')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		))->addClass('js-field-agent-other')
	])
	->addItem([
		new CLabel(_('HTTP proxy'), 'http_proxy'),
		new CFormField(
			(new CTextBox('http_proxy', $data['http_proxy'], false, DB::getFieldLength('httptest', 'http_proxy')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('[protocol://][user[:password]@]proxy.example.com[:port]'))
				->disableAutocomplete()
		)
	])
	->addItem([
		new CLabel(_('Variables')),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('variables')
					->setHeader([(new CColHeader())->setWidth(12), _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(5)
					),
				(new CTemplateTag('variable-row-tmpl'))->addItem(
					(new CRow([
						'',
						(new CTextAreaFlexible('variables[#{rowNum}][name]', '#{name}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
							->setAttribute('placeholder', _('name'))
							->disableSpellcheck(),
						RARR(),
						(new CTextAreaFlexible('variables[#{rowNum}][value]', '#{value}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
							->setMaxlength(DB::getFieldLength('httpstep_field', 'value'))
							->setAttribute('placeholder', _('value'))
							->disableSpellcheck(),
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	])
	->addItem([
		new CLabel(_('Headers')),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('headers')
					->setHeader([(new CColHeader())->setWidth(12), _('Name'), '', _('Value'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(5)
					),
				(new CTemplateTag('header-row-tmpl'))->addItem(
					(new CRow([
						(new CCol(
							(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
						))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CTextAreaFlexible('headers[#{rowNum}][name]', '#{name}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_NAME_WIDTH)
							->setAttribute('placeholder', _('name'))
							->disableSpellcheck(),
						RARR(),
						(new CTextAreaFlexible('headers[#{rowNum}][value]', '#{value}', ['add_post_js' => false]))
							->removeId()
							->setWidth(ZBX_TEXTAREA_HTTP_PAIR_VALUE_WIDTH)
							->setMaxlength(DB::getFieldLength('httpstep_field', 'value'))
							->setAttribute('placeholder', _('value'))
							->disableSpellcheck(),
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('element-table-remove')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->addClass('form_row')
				)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	])
	->addItem([
		new CLabel(_('Enabled'), 'status'),
		new CFormField(
			(new CCheckBox('status', HTTPTEST_STATUS_ACTIVE))->setChecked($data['status'] == HTTPTEST_STATUS_ACTIVE)
		)
	]);

// Steps tab.
$steps_tab = (new CFormGrid())->addItem([
	(new CLabel(_('Steps')))->setAsteriskMark(),
	(new CFormField(
		(new CDiv([
			(new CTable())
				->setId('steps')
				->addClass(ZBX_STYLE_LIST_NUMBERED)
				->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
				->setHeader([
					(new CColHeader())->setWidth('12'),
					(new CColHeader())->setWidth('15'),
					(new CColHeader(_('Name')))->setWidth('150'),
					(new CColHeader(_('Timeout')))->setWidth('50'),
					(new CColHeader(_('URL')))->setWidth('200'),
					(new CColHeader(_('Required')))->setWidth('75'),
					(new CColHeader(_('Status codes')))
						->addClass(ZBX_STYLE_NOWRAP)
						->setWidth('90'),
					(new CColHeader(_('Action')))->setWidth('50')
				])
				->addItem(
					(new CTag('tfoot', true))->addItem(
						(new CCol(!$data['templated']
							? (new CButtonLink(_('Add')))->addClass('js-add-step')
							: null
						))->setColSpan(8)
					)
				),
			$data['templated']
				? (new CTemplateTag('step-row-templated-tmpl'))->addItem(
					(new CRow([
						new CCol([
							(new CInput('hidden', 'steps[#{row_index}][httpstepid]', '#{httpstepid}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][name]', '#{name}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][url]', '#{url}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][timeout]', '#{timeout}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][posts]', '#{posts}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][required]', '#{required}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][status_codes]', '#{status_codes}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][follow_redirects]', '#{follow_redirects}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][retrieve_mode]', '#{retrieve_mode}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][post_type]', '#{post_type}'))->removeId()
						]),
						(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM),
						(new CCol(
							(new CLink('#{name}', 'javascript:void(0);'))->addClass('js-edit-step')
						))->addClass(ZBX_STYLE_WORDBREAK),
						'#{timeout}',
						'#{url}',
						'#{required}',
						'#{status_codes}',
						''
					]))->setAttribute('data-row_index', '#{row_index}')
				)
				: (new CTemplateTag('step-row-tmpl'))->addItem(
					(new CRow([
						(new CCol([
							(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON),
							(new CInput('hidden', 'steps[#{row_index}][httpstepid]', '#{httpstepid}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][name]', '#{name}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][url]', '#{url}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][timeout]', '#{timeout}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][posts]', '#{posts}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][required]', '#{required}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][status_codes]', '#{status_codes}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][follow_redirects]', '#{follow_redirects}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][retrieve_mode]', '#{retrieve_mode}'))->removeId(),
							(new CInput('hidden', 'steps[#{row_index}][post_type]', '#{post_type}'))->removeId()
						]))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CSpan(':'))->addClass(ZBX_STYLE_LIST_NUMBERED_ITEM),
						(new CCol(
							(new CLink('#{name}', 'javascript:void(0);'))->addClass('js-edit-step')
						))->addClass(ZBX_STYLE_WORDBREAK),
						'#{timeout}',
						'#{url}',
						'#{required}',
						'#{status_codes}',
						(new CCol(
							(new CButtonLink(_('Remove')))->addClass('js-remove-step')
						))->addClass(ZBX_STYLE_NOWRAP)
					]))->setAttribute('data-row_index', '#{row_index}')
				)
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setWidth('689')
	))->setAriaRequired()
]);

// Authentication tab.
$authentication_tab = (new CFormGrid())
	->addItem([
		new CLabel(_('HTTP authentication'), 'authentication-focusable'),
		new CFormField(
			(new CSelect('authentication'))
				->setId('authentication')
				->setFocusableElementId('authentication-focusable')
				->setValue($data['authentication'])
				->addOptions(CSelect::createOptionsFromArray(httptest_authentications()))
		)
	])
	->addItem([
		(new CLabel(_('User'), 'http_user'))->addClass('js-field-http-user'),
		(new CFormField(
			(new CTextBox('http_user', $data['http_user'], false, DB::getFieldLength('httptest', 'http_user')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->addClass('js-field-http-user')
	])
	->addItem([
		(new CLabel(_('Password'), 'http_password'))->addClass('js-field-http-password'),
		(new CFormField(
			(new CTextBox('http_password', $data['http_password'], false,
				DB::getFieldLength('httptest', 'http_password')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		))->addClass('js-field-http-password')
	])
	->addItem([
		new CLabel(_('SSL verify peer'), 'verify_peer'),
		new CFormField(
			(new CCheckBox('verify_peer'))->setChecked($data['verify_peer'] == ZBX_HTTP_VERIFY_PEER_ON)
		)
	])
	->addItem([
		new CLabel(_('SSL verify host'), 'verify_host'),
		new CFormField(
			(new CCheckBox('verify_host'))->setChecked($data['verify_host'] == ZBX_HTTP_VERIFY_HOST_ON)
		)
	])
	->addItem([
		new CLabel(_('SSL certificate file'), 'ssl_cert_file'),
		new CFormField(
			(new CTextBox('ssl_cert_file', $data['ssl_cert_file'], false,
				DB::getFieldLength('httptest', 'ssl_cert_file')
			))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('SSL key file'), 'ssl_key_file'),
		new CFormField(
			(new CTextBox('ssl_key_file', $data['ssl_key_file'], false, DB::getFieldLength('httptest', 'ssl_key_file')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('SSL key password'), 'ssl_key_password'),
		new CFormField(
			(new CTextBox('ssl_key_password', $data['ssl_key_password'], false,
				DB::getFieldLength('httptest', 'ssl_key_password')
			))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->disableAutocomplete()
		)
	]);

$webscenario_tabs = (new CTabView())
	->addTab('scenario-tab', _('Scenario'), $scenario_tab)
	->addTab('steps-tab', _('Steps'), $steps_tab, TAB_INDICATOR_STEPS)
	->addTab('tags-tab', _('Tags'),
		new CPartial('configuration.tags.tab', [
			'source' => 'httptest',
			'tags' => $data['tags'],
			'show_inherited_tags' => $data['show_inherited_tags'],
			'tabs_id' => 'tabs',
			'tags_tab_id' => 'tags-tab',
			'field_label' => _('Tags')
		]),
		TAB_INDICATOR_TAGS
	)
	->addTab('authentication-tab', _('Authentication'), $authentication_tab, TAB_INDICATOR_HTTP_AUTH);

if ($data['form_refresh'] == 0) {
	$webscenario_tabs->setSelected(0);
}

// Append buttons to form.
if ($data['httptestid'] != 0) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($data['host']['status'] == HOST_STATUS_MONITORED || $data['host']['status'] == HOST_STATUS_NOT_MONITORED) {
		$buttons[] = new CButtonQMessage(
			'del_history',
			_('Clear history and trends'),
			_('History clearing can take a long time. Continue?')
		);
	}

	$buttons[] = (new CButtonDelete(_('Delete web scenario?'), url_params(['form', 'httptestid', 'hostid', 'context']).
		'&'.CSRF_TOKEN_NAME.'='.CCsrfTokenHelper::get('httpconf.php'),
		'context'
	))->setEnabled(!$data['templated']);
	$buttons[] = new CButtonCancel(url_param('context'));

	$webscenario_tabs->setFooter(
		(new CFormGrid(
			new CFormActions(new CSubmit('update', _('Update')), $buttons)
		))->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_ACTIONS)
	);
}
else {
	$webscenario_tabs->setFooter(
		(new CFormGrid(
			new CFormActions(new CSubmit('add', _('Add')), [new CButtonCancel(url_param('context'))])
		))->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_ACTIONS)
	);
}

$form->addItem($webscenario_tabs);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'is_templated' => (int) $data['templated'],
		'variables' => $data['variables'],
		'headers' => $data['headers'],
		'steps' => $data['steps'],
		'context' => $data['context']
	]).');
'))
	->setOnDocumentReady()
	->show();
