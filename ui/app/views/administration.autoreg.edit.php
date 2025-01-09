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

$this->includeJsFile('administration.autoreg.edit.js.php');

$html_page = (new CHtmlPage())
	->setTitle(_('Autoregistration'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_AUTOREG_EDIT));

$autoreg_form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('autoreg')))->removeId())
	->setId('autoreg-form')
	->setName('autoreg-form')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'autoreg.edit')
		->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('tls_accept', $data['tls_accept']);

$autoreg_tab = (new CFormList())
	->addRow(_('Encryption level'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))
				->setAttribute('autofocus', 'autofocus')
				->setLabel(_('No encryption'))
			)
			->addItem((new CCheckBox('tls_in_psk'))->setLabel(_('PSK')))
	);

if ($data['change_psk']) {
	$autoreg_tab
		->addRow(
			(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
			(new CTextBox('tls_psk_identity', $data['tls_psk_identity'], false,
				DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')
			))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->disableAutocomplete(),
			null,
			'tls_psk'
		)
		->addRow(
			(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
			(new CTextBox('tls_psk', $data['tls_psk'], false, DB::getFieldLength('config_autoreg_tls', 'tls_psk')))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->disableAutocomplete(),
			null,
			'tls_psk'
		);
}
else {
	$autoreg_tab
		->addRow(
			(new CLabel(_('PSK')))->setAsteriskMark(),
			(new CSimpleButton(_('Change PSK')))
				->onClick('javascript: submitFormWithParam("'.$autoreg_form->getName().'", "change_psk", "1");')
				->addClass(ZBX_STYLE_BTN_GREY),
			null,
			'tls_psk'
		);
}

$autoreg_view = (new CTabView())
	->addTab('autoreg', _('Autoregistration'), $autoreg_tab)
	->setFooter(makeFormFooter((new CSubmitButton(_('Update'), 'action', 'autoreg.update'))->setId('update')));

$autoreg_form->addItem($autoreg_view);

$html_page
	->addItem($autoreg_form)
	->show();
