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
 * @var CPartial $this
 */

$table = (new CTable())
	->setId('subscriptions-table')
	->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS)
	->addClass('subscriptions-table')
	->addStyle('width: 100%;')
	->setAriaRequired()
	->setHeader([
		(new CColHeader(_('Recipient')))->setWidth('38%'),
		(new CColHeader(_('Generate report by')))->setWidth('30%'),
		_('Status'),
		_('Action')
	])
	->addItem(
		(new CTag('tfoot', true))->addItem(
			(new CCol(
				new CHorList([
					(new CSimpleButton(_('Add user')))
						->addClass('js-add-user')
						->addClass(ZBX_STYLE_BTN_LINK)
						->setEnabled($data['allowed_edit']),
					(new CSimpleButton(_('Add user group')))
						->addClass('js-add-user-group')
						->addClass(ZBX_STYLE_BTN_LINK)
						->setEnabled($data['allowed_edit'])
				])
			))->setColSpan(4)
		)
	);

(new CDiv($table))
	->setId('subscriptions')
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->show();

$this->includeJsFile('scheduledreport.subscription.js.php', [
	'allowed_edit' => $data['allowed_edit'],
	'subscriptions' => $data['subscriptions']
]);
