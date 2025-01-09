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

if ($data['uncheck']) {
	uncheckTableRows('token');
}

$this->includeJsFile('administration.token.list.js.php');
$this->addJsFile('class.calendar.js');

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'token.list'))
	->addVar('action', 'token.list')
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
			->addRow(new CLabel(_('Users'), 'filter_userids__ms'), [
				(new CMultiSelect([
					'name' => 'filter_userids[]',
					'object_name' => 'users',
					'data' => $data['ms_users'],
					'placeholder' => '',
					'popup' => [
						'parameters' => [
							'srctbl' => 'users',
							'srcfld1' => 'userid',
							'srcfld2' => 'fullname',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_userids_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			])
			->addRow(_('Expires in less than'), [
				(new CCheckBox('filter_expires_state'))
					->setChecked($data['filter']['expires_state'])
					->setId('filter-expires-state')
					->onClick('view.expiresDaysHandler()'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CNumericBox('filter_expires_days', $data['filter']['expires_days'], 3, false, false, false))
					->setId('filter-expires-days')
					->setEnabled($data['filter']['expires_state'])
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				_('days')
			]),
		(new CFormList())
			->addRow(new CLabel(_('Created by users'), 'filter_creator_userids__ms'), [
				(new CMultiSelect([
					'name' => 'filter_creator_userids[]',
					'object_name' => 'users',
					'data' => $data['ms_creators'],
					'placeholder' => '',
					'popup' => [
						'parameters' => [
							'srctbl' => 'users',
							'srcfld1' => 'userid',
							'srcfld2' => 'fullname',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_creator_userids_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			])
			->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), ZBX_AUTH_TOKEN_ENABLED)
					->addValue(_('Disabled'), ZBX_AUTH_TOKEN_DISABLED)
					->setModern(true)
			)
	]);

$html_page = (new CHtmlPage())
	->setTitle(_('API tokens'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_TOKEN_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				(new CSimpleButton(_('Create API token')))->addClass('js-create-token')
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter);

$token_form = (new CForm())
	->addVar('action_src', 'token.list')
	->setName('token_form');

$token_table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_tokens'))
				->onClick("checkAll('".$token_form->getName()."', 'all_tokens', 'tokenids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'token.list')
				->getUrl()
		),
		make_sorting_header(_('User'), 'user', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'token.list')
				->getUrl()
		),
		make_sorting_header(_('Expires at'), 'expires_at', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'token.list')
				->getUrl()
		),
		_('Created at'),
		make_sorting_header(_('Created by user'), 'creator', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'token.list')
				->getUrl()
		),
		make_sorting_header(_('Last accessed at'), 'lastaccess', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'token.list')
				->getUrl()
		),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'token.list')
				->getUrl()
		)
	])
	->setPageNavigation($data['paging']);

$csrf_token = CCsrfTokenHelper::get('token');

foreach ($data['tokens'] as $token) {
	$token_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'token.edit')
		->setArgument('tokenid', $token['tokenid'])
		->setArgument('admin_mode', 1)
		->getUrl();

	$name = (new CLink($token['name'], $token_url))
		->setAttribute('data-tokenid', $token['tokenid'])
		->setAttribute('data-action', 'token.edit')
		->setAttribute('data-admin_mode', '1');

	$token_table->addRow([
		new CCheckBox('tokenids['.$token['tokenid'].']', $token['tokenid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		(new CCol($token['user']))->addClass(ZBX_STYLE_WORDBREAK),
		(new CSpan(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $token['expires_at'])))->addClass(
			$token['is_expired'] ? ZBX_STYLE_RED : ZBX_STYLE_GREEN
		),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $token['created_at']),
		($token['creator'] === null)
			? italic(_('Unknown'))
			: (new CCol($token['creator']))->addClass(ZBX_STYLE_WORDBREAK),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $token['lastaccess']),
		($token['status'] == ZBX_AUTH_TOKEN_ENABLED)
			? (new CLink(_('Enabled'), (new CUrl('zabbix.php'))
					->setArgument('action_src', 'token.list')
					->setArgument('action', 'token.disable')
					->setArgument('tokenids', (array) $token['tokenid'])
					->getUrl()
			))
				->addCsrfToken($csrf_token)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
			: (new CLink(_('Disabled'), (new CUrl('zabbix.php'))
					->setArgument('action_src', 'token.list')
					->setArgument('action', 'token.enable')
					->setArgument('tokenids', (array) $token['tokenid'])
					->getUrl()
			))
				->addCsrfToken($csrf_token)
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
	]);
}

$token_form->addItem([
	$token_table,
	new CActionButtonList('action', 'tokenids', [
		'token.enable' => [
			'name' => _('Enable'),
			'confirm_singular' => _('Enable selected API token?'),
			'confirm_plural' => _('Enable selected API tokens?'),
			'csrf_token' => $csrf_token
		],
		'token.disable' => [
			'name' => _('Disable'),
			'confirm_singular' => _('Disable selected API token?'),
			'confirm_plural' => _('Disable selected API tokens?'),
			'csrf_token' => $csrf_token
		],
		'token.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-token')
				->addClass('js-no-chkbxrange')
				->removeId()
		]
	], 'token')
]);

$html_page
	->addItem($token_form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
