<?php
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

$this->includeJsFile('administration.regex.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('regexp');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Regular expressions'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_REGEX_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem((new CSimpleButton(_('Create regular expression')))->addClass('js-create-regexp'))
				->addItem(
					(new CSimpleButton(_('Import')))->setId('js-import'))
		))->setAttribute('aria-label', _('Content controls'))
	);

$filter = (new CFilter())
	->addVar('action', 'regex.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'regex.list'))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				)
			])
			->addItem([
				new CLabel(_('Description'), 'filter_description'),
				new CFormField(
					(new CTextBox('filter_description', $data['filter']['description']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
			])
	]);

$form = (new CForm())->setName('regularExpressionsForm');

$view_url = (new CUrl('zabbix.php'))->setArgument('action', 'regex.list')->getUrl();

$table = (new CTableInfo())
	->addClass(ZBX_STYLE_ROUNDED_SURFACE)
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all-regexes'))->onClick("checkAll('".$form->getName()."', 'all-regexes', 'regexpids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $view_url),
		_('Expressions'),
		_('Description')
	])
	->setPageNavigation($data['paging']);

foreach ($data['regexps'] as $regexpid => $regexp) {
	$numb = 1;
	$expressions = [];

	foreach ($regexp['expressions'] as $expression) {
		$expressions[] = (new CTable())->addRow([
			new CCol($numb++),
			new CCol([' ', RARR(), ' ']),
			(new CCol($expression['expression']))->addClass(ZBX_STYLE_WORDWRAP),
			new CCol('['.CRegexHelper::expression_type2str($expression['expression_type']).']')
		]);
	}

	$regexp_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'regex.edit')
		->setArgument('regexpid', $regexpid)
		->getUrl();

	$table->addRow([
		new CCheckBox('regexpids['.$regexpid.']', $regexpid),
		(new CCol(
			new CLink($regexp['name'], $regexp_url)
		))->addClass(ZBX_STYLE_WORDBREAK),
		$expressions,
		(new CCol($regexp['description']))->addClass(ZBX_STYLE_WORDBREAK)
	]);
}

$form->addItem([
	$table,
	new CActionButtonList('action', 'regexpids', [
		'regex.export' => [
			'content' => new CButtonExport('export.regexes',
				(new CUrl('zabbix.php'))
					->setArgument('action', 'regex.list')
					->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
					->getUrl()
			)
		],
		'regex.delete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->setId('js-massdelete')
				->addClass('js-no-chkbxrange')
		]
	], 'regexp')
]);

$html_page
	->addItem($filter)
	->addItem($form)
	->addItem((new CScriptTag('view.init();'))->setOnDocumentReady())
	->show();
