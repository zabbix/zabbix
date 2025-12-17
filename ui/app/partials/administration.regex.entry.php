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
 * @var array    $data
 */

(new CRow())
	->setAttribute('data-index', $data['index'])
	->addItem((new CSelect('expressions['.$data['index'].'][expression_type]'))
		->setId('expressions_'.$data['index'].'_expression_type')
		->addClass('js-expression-type-select')
		->addOptions($data['options_expression_type'])
		->setValue($data['type'])
		->setErrorContainer('expressions-'.$data['index'].'-error-container')
		->setErrorLabel(_('Expression type'))
	)
	->addItem((new CTextBox('expressions['.$data['index'].'][expression]', $data['expression'], false,
		DB::getFieldLength('expressions', 'expression'))
	)
		->setAttribute('data-notrim', '')
		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
		->setAriaRequired()
		->setErrorContainer('expressions-'.$data['index'].'-error-container')
		->setErrorLabel(_('Expression'))
	)
	->addItem((new CSelect('expressions['.$data['index'].'][exp_delimiter]'))
		->setValue($data['delimiter'])
		->setId('expressions_'.$data['index'].'_exp_delimiter')
		->addClass('js-expression-delimiter-select')
		->addOptions($data['options_delimiter'])
		->addClass($data['type'] != EXPRESSION_TYPE_ANY_INCLUDED ? ZBX_STYLE_DISPLAY_NONE : null)
		->setDisabled($data['type'] != EXPRESSION_TYPE_ANY_INCLUDED)
		->setErrorContainer('expressions-'.$data['index'].'-error-container')
		->setErrorLabel(_('Delimiter'))
	)
	->addItem((new CCheckBox('expressions['.$data['index'].'][case_sensitive]'))
		->setChecked($data['case_sensitive'] == 1)
		->setUncheckedValue('0')
		->setErrorContainer('expressions-'.$data['index'].'-error-container')
		->setErrorLabel(_('Case sensitive'))
	)
	->addItem((new CCol())
		->addClass(ZBX_STYLE_NOWRAP)
		->addItem((new CButton('remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->removeId()
		)
	)
	->show();

(new CRow())->addItem((new CCol())
	->setId('expressions-'.$data['index'].'-error-container')
	->addClass(ZBX_STYLE_ERROR_CONTAINER)
	->setAttribute('colspan', 5))
	->show();
