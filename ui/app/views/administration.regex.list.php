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
 */

if ($data['uncheck']) {
	uncheckTableRows('regex');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Regular expressions'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_REGEX_LIST))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(new CRedirectButton(_('New regular expression'),
					(new CUrl('zabbix.php'))->setArgument('action', 'regex.edit')
				))
		))->setAttribute('aria-label', _('Content controls'))
	);

$form = (new CForm())->setName('regularExpressionsForm');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all-regexes'))->onClick("checkAll('".$form->getName()."', 'all-regexes', 'regexids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		_('Name'),
		_('Expressions')
	]);

foreach($data['regexs'] as $regexid => $regex) {
	$numb = 1;
	$expressions = [];

	foreach($regex['expressions'] as $expression) {
		$expressions[] = (new CTable())->addRow([
			new CCol($numb++),
			new CCol([' ', RARR(), ' ']),
			(new CCol($expression['expression']))->addClass(ZBX_STYLE_WORDWRAP),
			new CCol(' ['.CRegexHelper::expression_type2str($expression['expression_type']).']')
		]);
	}

	$table->addRow([
		new CCheckBox('regexids['.$regexid.']', $regexid),
		(new CCol(
			new CLink($regex['name'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'regex.edit')
					->setArgument('regexid', $regexid)
			),
		))->addClass(ZBX_STYLE_WORDBREAK),
		$expressions
	]);
}

$form->addItem([
	$table,
	new CActionButtonList('action', 'regexids', [
		'regex.delete' => [
			'name' => _('Delete'),
			'confirm_singular' => _('Delete selected regular expression?'),
			'confirm_plural' => _('Delete selected regular expressions?'),
			'csrf_token' => CCsrfTokenHelper::get('regex')
		]
	], 'regex')
]);

$html_page->addItem($form)->show();
