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

$this->includeJsFile('administration.autoreg.edit.js.php');

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('autoreg')))->removeId())
	->addItem(new CVar('psk_required', (int) !$data['tls_in_psk']))
	->setId('autoreg-form')
	->setName('autoreg-form')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'autoreg.update')
		->getUrl()
	)
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Encryption level')),
		new CFormField([
			(new CList([
				(new CCheckBox('tls_in_none'))
					->setChecked($data['tls_in_none'])
					->setUncheckedValue(0)
					->setLabel(_('No encryption'))
					->setAttribute('autofocus', 'autofocus')
					->setErrorContainer('encryption_error_container'),
				(new CCheckBox('tls_in_psk'))
					->setChecked($data['tls_in_psk'])
					->setUncheckedValue(0)
					->setLabel(_('PSK'))
					->setErrorContainer('encryption_error_container')
			]))->addClass(ZBX_STYLE_LIST_CHECK_RADIO),
			(new CDiv())
				->setId('encryption_error_container')
				->addClass(ZBX_STYLE_ERROR_CONTAINER)
		])
	])
	->addItem([
		(new CLabel(_('PSK'), 'change_psk'))
			->setAsteriskMark()
			->addClass($data['tls_in_psk'] ? null : ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CSimpleButton(_('Change PSK')))
				->setId('change_psk')
				->addClass(ZBX_STYLE_BTN_GREY)
		))->addClass($data['tls_in_psk'] ? null : ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('PSK identity'), 'tls_psk_identity'))
			->setAsteriskMark()
			->addClass(ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('tls_psk_identity', '', false, DB::getFieldLength('config_autoreg_tls', 'tls_psk_identity')))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->setEnabled(false)
		))->addClass(ZBX_STYLE_DISPLAY_NONE)
	])
	->addItem([
		(new CLabel(_('PSK'), 'tls_psk'))
			->setAsteriskMark()
			->addClass(ZBX_STYLE_DISPLAY_NONE),
		(new CFormField(
			(new CTextBox('tls_psk', '', false, DB::getFieldLength('config_autoreg_tls', 'tls_psk')))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->disableAutocomplete()
				->setEnabled(false)
		))->addClass(ZBX_STYLE_DISPLAY_NONE)
	]);

$autoreg_view = (new CTabView())
	->addTab('autoreg', _('Autoregistration'), $form_grid)
	->setFooter(makeFormFooter((new CSubmitButton(_('Update'), 'action', 'autoreg.update'))->setId('update')));

$form->addItem($autoreg_view);

(new CHtmlPage())
	->setTitle(_('Autoregistration'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_AUTOREG_EDIT))
	->addItem($form)
	->show();

(new CScriptTag(
	'autoreg_edit.init('.json_encode([
		'rules' => $data['js_validation_rules']
	]).');'
))
	->setOnDocumentReady()
	->show();
