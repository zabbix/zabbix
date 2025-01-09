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

$this->includeJsFile('module.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('modules');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Modules'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_MODULE_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CForm())
				->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('module')))->removeId())
				->addVar('action', 'module.scan')
				->addItem((new CList())
					->addItem(new CSubmit('form', _('Scan directory')))
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem(
		(new CFilter())
			->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'module.list'))
			->addVar('action', 'module.list')
			->setProfile($data['filter_profile'])
			->setActiveTab($data['filter_active_tab'])
			->addFilterTab(_('Filter'), [
				(new CFormList())->addRow(_('Name'),
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				),
				(new CFormList())->addRow(_('Status'),
					(new CRadioButtonList('filter_status', (int) $data['filter']['status']))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), MODULE_STATUS_ENABLED)
						->addValue(_('Disabled'), MODULE_STATUS_DISABLED)
						->setModern(true)
				)
			])
	);

// create form
$form = (new CForm())->setName('module-form');

// create table
$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_modules'))
				->onClick("checkAll('".$form->getName()."', 'all_modules', 'moduleids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'module.list')
				->getUrl()
		),
		_('Version'),
		_('Author'),
		_('Description'),
		_('Status')
	])
	->setPageNavigation($data['paging']);

foreach ($data['modules'] as $moduleid => $module) {
	$module_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'module.edit')
		->setArgument('moduleid', $moduleid)
		->getUrl();

	$name = (new CLink($module['name'], $module_url))
		->setAttribute('data-moduleid', $moduleid)
		->setAttribute('data-action', 'module.edit');

	if ($module['status'] == MODULE_STATUS_ENABLED) {
		$status = (new CLink(_('Enabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_GREEN)
			->addClass('js-disable-module')
			->setAttribute('data-moduleid', $moduleid);
	}
	else {
		$status = (new CLink(_('Disabled')))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addClass('js-enable-module')
			->setAttribute('data-moduleid', $moduleid);
	}

	// append table row
	$table->addRow([
		new CCheckBox('moduleids['.$moduleid.']', $moduleid),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		$module['version'],
		$module['author'],
		$module['description'],
		$status
	]);
}

// append table to form
$form->addItem([
	$table,
	new CActionButtonList('action', 'moduleids', [
		'module.massenable' => [
			'content' => (new CSimpleButton(_('Enable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massenable-module')
				->addClass('js-no-chkbxrange')
		],
		'module.massdisable' => [
			'content' => (new CSimpleButton(_('Disable')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdisable-module')
				->addClass('js-no-chkbxrange')
		]
	], 'modules')
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
