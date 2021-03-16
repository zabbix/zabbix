<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	->addStyle('width: 100%;')
	->setAriaRequired()
	->setHeader([_('Recipient'), _('Generate report by'), _('Status'), _('Action')])
	->setFooter(
		(new CCol([
			(new CSimpleButton(_('Add user')))
				->setAttribute('data-action', 'add_user')
				->addClass(ZBX_STYLE_BTN_LINK)
				->setEnabled($data['allowed_edit']),
			(new CSimpleButton(_('Add user group')))
				->setAttribute('data-action', 'add_usergroup')
				->addClass(ZBX_STYLE_BTN_LINK)
				->setEnabled($data['allowed_edit'])
			])
		)->setColSpan(4)
	);

(new CDiv($table))
	->setId('subscriptions')
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	->show();

$this->includeJsFile('scheduledreport.subscription.js.php', [
	'users' => $data['users'],
	'user_groups' => $data['user_groups']
]);
