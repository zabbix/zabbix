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
					(new CButtonLink(_('Add user')))
						->addClass('js-add-user')
						->setEnabled($data['allowed_edit']),
					(new CButtonLink(_('Add user group')))
						->addClass('js-add-user-group')
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
