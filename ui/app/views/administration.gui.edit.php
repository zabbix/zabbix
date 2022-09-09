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

$this->includeJsFile('administration.gui.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('GUI'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu())
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_GUI_EDIT));

// Append languages to form list.
$lang_select = (new CSelect('default_lang'))
	->setId('default_lang')
	->setValue($data['default_lang'])
	->setFocusableElementId('label-default-lang')
	->setAttribute('autofocus', 'autofocus');

$all_locales_available = true;

foreach (getLocales() as $localeid => $locale) {
	if (!$locale['display']) {
		continue;
	}

	/*
	 * Checking if this locale exists in the system. The only way of doing it is to try and set one
	 * trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC.
	 */
	$locale_available = ($localeid === ZBX_DEFAULT_LANG || setlocale(LC_MONETARY, zbx_locale_variants($localeid)));

	$lang_select->addOption((new CSelectOption($localeid, $locale['name']))->setDisabled(!$locale_available));

	if (!$locale_available) {
		$all_locales_available = false;
	}
}

// Restoring original locale.
setlocale(LC_MONETARY, zbx_locale_variants($data['default_lang']));

$language_error = '';
if (!function_exists('bindtextdomain')) {
	$language_error = 'Translations are unavailable because the PHP gettext module is missing.';
	$lang_select->setReadonly();
}
elseif (!$all_locales_available) {
	$language_error = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
}

$gui_tab = (new CFormList())
	->addRow(new CLabel(_('Default language'), $lang_select->getFocusableElementId()),
		($language_error !== '')
			? [$lang_select, (makeErrorIcon($language_error))->addStyle('margin-left: 5px;')]
			: $lang_select
	)
	->addRow(new CLabel(_('Default time zone'), 'label-default-timezone'),
		(new CSelect('default_timezone'))
			->addOptions(CSelect::createOptionsFromArray($data['timezones']))
			->setValue($data['default_timezone'])
			->setFocusableElementId('label-default-timezone')
			->setId('default_timezone')
	)
	->addRow(new CLabel(_('Default theme'), 'label-default-theme'),
		(new CSelect('default_theme'))
			->setFocusableElementId('label-default-theme')
			->setValue($data['default_theme'])
			->addOptions(CSelect::createOptionsFromArray(APP::getThemes()))
			->setAttribute('autofocus', 'autofocus')
			->setId('default_theme')
	)
	->addRow((new CLabel(_('Limit for search and filter results'), 'search_limit'))->setAsteriskMark(),
		(new CNumericBox('search_limit', $data['search_limit'], 6))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(
		(new CLabel(_('Max number of columns and rows in overview tables'), 'max_overview_table_size'))
			->setAsteriskMark(),
		(new CNumericBox('max_overview_table_size', $data['max_overview_table_size'], 6))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Max count of elements to show inside table cell'), 'max_in_table'))->setAsteriskMark(),
		(new CNumericBox('max_in_table', $data['max_in_table'], 5))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
	)
	->addRow(_('Show warning if Zabbix server is down'),
		(new CCheckBox('server_check_interval', SERVER_CHECK_INTERVAL))
			->setUncheckedValue('0')
			->setChecked($data['server_check_interval'] == SERVER_CHECK_INTERVAL)
	)
	->addRow((new CLabel(_('Working time'), 'work_period'))->setAsteriskMark(),
		(new CTextBox('work_period', $data['work_period']))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('Show technical errors'),
		(new CCheckBox('show_technical_errors'))
			->setUncheckedValue('0')
			->setChecked($data['show_technical_errors'] == 1)
	)
	->addRow(
		(new CLabel(_('Max history display period'), 'history_period'))->setAsteriskMark(),
		(new CTextBox('history_period', $data['history_period'], false, DB::getFieldLength('config', 'history_period')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Time filter default period'), 'period_default'))->setAsteriskMark(),
		(new CTextBox('period_default', $data['period_default'], false, DB::getFieldLength('config', 'period_default')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Max period for time selector'), 'max_period'))->setAsteriskMark(),
		(new CTextBox('max_period', $data['max_period'], false, DB::getFieldLength('config', 'max_period')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	);

$gui_view = (new CTabView())
	->addTab('gui', _('GUI'), $gui_tab)
	->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[new CButton('resetDefaults', _('Reset defaults'))]
	));

$form = (new CForm())
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'gui.update')
		->getUrl()
	)
	->addItem($gui_view);

$widget
	->addItem($form)
	->show();
