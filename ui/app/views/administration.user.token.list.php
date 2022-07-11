<?php declare(strict_types = 0);
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
	uncheckTableRows('user.token');
}

$this->includeJsFile('administration.user.token.list.js.php');

$filter = (new CFilter())
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'user.token.list'))
	->addVar('action', 'user.token.list')
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
			->addRow(_('Expires in less than'), [
				(new CCheckBox('filter_expires_state'))
					->setChecked($data['filter']['expires_state'])
					->setId('filter-expires-state'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CNumericBox('filter_expires_days', $data['filter']['expires_days'], 3, false, false, false))
					->setId('filter-expires-days')
					->setEnabled($data['filter']['expires_state'])
					->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				_('days')
			]),
		(new CFormList())
			->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
					->addValue(_('Any'), -1)
					->addValue(_('Enabled'), ZBX_AUTH_TOKEN_ENABLED)
					->addValue(_('Disabled'), ZBX_AUTH_TOKEN_DISABLED)
					->setModern(true)
			)
	]);

$widget = (new CWidget())
	->setTitle(_('API tokens'))
	->setTitleSubmenu(getUserSettingsSubmenu())
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(new CRedirectButton(_('Create API token'),
				(new CUrl('zabbix.php'))->setArgument('action', 'user.token.edit'))
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter);

$token_form = (new CForm('get'))
	->addVar('action_src', 'user.token.list')
	->setName('token_form');

$token_table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_tokens'))
				->onClick("checkAll('".$token_form->getName()."', 'all_tokens', 'tokenids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.token.list')
				->getUrl()
		),
		make_sorting_header(_('Expires at'), 'expires_at', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.token.list')
				->getUrl()
		),
		_('Created at'),
		make_sorting_header(_('Last accessed at'), 'lastaccess', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.token.list')
				->getUrl()
		),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'user.token.list')
				->getUrl()
		)
	]);

foreach ($data['tokens'] as $token) {
	$name = new CLink($token['name'], (new CUrl('zabbix.php'))
		->setArgument('action', 'user.token.edit')
		->setArgument('tokenid', $token['tokenid'])
	);

	$token_table->addRow([
		new CCheckBox('tokenids['.$token['tokenid'].']', $token['tokenid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		(new CSpan(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $token['expires_at'])))->addClass(
			$token['is_expired'] ? ZBX_STYLE_RED : ZBX_STYLE_GREEN
		),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $token['created_at']),
		zbx_date2str(DATE_TIME_FORMAT_SECONDS, $token['lastaccess']),
		($token['status'] == ZBX_AUTH_TOKEN_ENABLED)
			? (new CLink(_('Enabled'), (new CUrl('zabbix.php'))
					->setArgument('action_src', 'user.token.list')
					->setArgument('action', 'token.disable')
					->setArgument('tokenids', (array) $token['tokenid'])
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->addSID()
			: (new CLink(_('Disabled'), (new CUrl('zabbix.php'))
					->setArgument('action_src', 'user.token.list')
					->setArgument('action', 'token.enable')
					->setArgument('tokenids', (array) $token['tokenid'])
					->getUrl()
			))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->addSID()
	]);
}

$token_form->addItem([
	$token_table,
	$data['paging'],
	new CActionButtonList('action', 'tokenids', [
		'token.enable' => ['name' => _('Enable'), 'confirm' => _('Enable selected API tokens?')],
		'token.disable' => ['name' => _('Disable'), 'confirm' => _('Disable selected API tokens?')],
		'token.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected API tokens?')]
	], 'user.token')
]);

$widget
	->addItem($token_form)
	->show();
