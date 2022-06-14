<?php
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
	uncheckTableRows();
}

$widget = (new CWidget())
	->setTitle(_('Regular expressions'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem(new CRedirectButton(_('New regular expression'),
				(new CUrl('zabbix.php'))->setArgument('action', 'regex.edit')
			))
		))
			->setAttribute('aria-label', _('Content controls'))
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
			new CCol(' &raquo; '),
			new CCol($expression['expression']),
			new CCol(' ['.CRegexHelper::expression_type2str($expression['expression_type']).']')
		]);
	}

	$table->addRow([
		new CCheckBox('regexids['.$regexid.']', $regexid),
		new CLink($regex['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'regex.edit')
				->setArgument('regexid', $regexid)
		),
		$expressions
	]);
}

$form->addItem([
	$table,
	new CActionButtonList('action', 'regexids', [
		'regex.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected regular expressions?')]
	])
]);

$widget->addItem($form)->show();
