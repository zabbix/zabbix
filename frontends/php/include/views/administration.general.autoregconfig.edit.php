<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/js/administration.general.autoregconfig.js.php';

$widget = (new CWidget())
	->setTitle(_('Auto registration'))
	->setControls((new CTag('nav', true,
		(new CForm())
			->cleanItems()
			->addItem((new CList())
				->addItem(makeAdministrationGeneralMenu('adm.autoregconfig.php'))
			)
		))
			->setAttribute('aria-label', _('Content controls'))
	);

$autoregTab = (new CFormList())
	->addRow(_('Encryption level'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))
				->setLabel(_('No encryption'))
			)
			->addItem((new CCheckBox('tls_in_psk'))
				->setLabel(_('PSK'))
			)
	)
	->addRow(
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
		(new CTextBox('tls_psk_identity', $data['tls_psk_identity'], false, 128))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired(),
		null,
		'tls_psk'
	)
	->addRow(
		(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
		(new CTextBox('tls_psk', $data['tls_psk'], false, 512))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAriaRequired(),
		null,
		'tls_psk'
	);

$autoregView = (new CTabView())
	->addTab('autoreg', _('Auto registration'), $autoregTab)
	->setFooter(makeFormFooter(new CSubmit('update', _('Update'))));

$autoregForm = (new CForm())
	->setAttribute('id', 'autoregconfigForm')
	->addVar('tls_accept', $data['tls_accept'])
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addItem($autoregView);

$widget->addItem($autoregForm);

return $widget;
