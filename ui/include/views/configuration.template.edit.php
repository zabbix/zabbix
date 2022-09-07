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

require_once __DIR__.'/js/common.template.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Templates'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_TEMPLATES_EDIT));

if ($data['form'] !== 'clone' && $data['form'] !== 'full_clone') {
	$widget->setNavigation(getHostNavigation('', $data['templateid']));
}

$tabs = new CTabView();

if (!hasRequest('form_refresh')) {
	$tabs->setSelected(0);
}

$form = (new CForm())
	->setId('templates-form')
	->setName('templatesForm')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form']);

if ($data['templateid'] != 0) {
	$form->addVar('templateid', $data['templateid']);
}

$form->addVar('clear_templates', $data['clear_templates']);

$template_tab = (new CFormList('hostlist'))
	->addRow(
		(new CLabel(_('Template name'), 'template_name'))->setAsteriskMark(),
		(new CTextBox('template_name', $data['template_name'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(
		_('Visible name'),
		(new CTextBox('visiblename', $data['visible_name'], false, 128))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

$templates_field_items = [];

if ($data['linked_templates']) {
	$linked_templates= (new CTable())
		->setHeader([_('Name'), _('Action')])
		->setId('linked-templates')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addStyle('width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

	foreach ($data['linked_templates'] as $template) {
		$linked_templates->addItem(
			(new CVar('templates['.$template['templateid'].']', $template['templateid']))->removeId()
		);

		if (array_key_exists($template['templateid'], $data['writable_templates'])) {
			$template_link = (new CLink(
					$template['name'],
					'templates.php?form=update&templateid='.$template['templateid']
				))
					->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$template_link->addClass(ZBX_STYLE_WORDWRAP);

		$clone_mode = ($data['form'] === 'clone' || $data['form'] === 'full_clone');

		$linked_templates->addRow([
			$template_link,
			(new CCol(
				new CHorList([
					(new CSimpleButton(_('Unlink')))
						->setAttribute('data-templateid', $template['templateid'])
						->onClick('
							submitFormWithParam("'.$form->getName().'", `unlink[${this.dataset.templateid}]`, 1);
						')
						->addClass(ZBX_STYLE_BTN_LINK),
					(array_key_exists($template['templateid'], $data['original_templates']) && !$clone_mode)
						? (new CSimpleButton(_('Unlink and clear')))
							->setAttribute('data-templateid', $template['templateid'])
							->onClick('
								submitFormWithParam("'.$form->getName().'",
									`unlink_and_clear[${this.dataset.templateid}]`, 1
								);
							')
							->addClass(ZBX_STYLE_BTN_LINK)
						: null
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		], null, 'conditions_'.$template['templateid']);
	}

	$templates_field_items[] = $linked_templates;
}

$templates_field_items[] = (new CMultiSelect([
	'name' => 'add_templates[]',
	'object_name' => 'templates',
	'data' => $data['add_templates'],
	'popup' => [
		'parameters' => [
			'srctbl' => 'templates',
			'srcfld1' => 'hostid',
			'srcfld2' => 'host',
			'dstfrm' => $form->getName(),
			'dstfld1' => 'add_templates_',
			'excludeids' => ($data['templateid'] == 0) ? [] : [$data['templateid']],
			'disableids' => array_column($data['linked_templates'], 'templateid')
		]
	]
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$template_tab
	->addRow(
		new CLabel(_('Templates'), 'add_templates__ms'),
		(count($templates_field_items) > 1)
			? (new CDiv($templates_field_items))->addClass('linked-templates')
			: $templates_field_items
	)
	->addRow((new CLabel(_('Template groups'), 'groups__ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'templateGroup',
			'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
			'data' => $data['groups_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'groups_',
					'editable' => true,
					'disableids' => array_column($data['groups_ms'], 'id')
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
	);

$tabs->addTab('tmplTab', _('Templates'), $template_tab, false);

// tags
$tabs->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'template',
		'tags' => $data['tags'],
		'readonly' => $data['readonly'],
		'tabs_id' => 'tabs',
		'tags_tab_id' => 'tags-tab'
	]), TAB_INDICATOR_TAGS
);

// macros
$tmpl = $data['show_inherited_macros'] ? 'hostmacros.inherited.list.html' : 'hostmacros.list.html';
$tabs->addTab('macroTab', _('Macros'),
	(new CFormList('macrosFormList'))
		->addRow(null, (new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
			->addValue(_('Template macros'), 0)
			->addValue(_('Inherited and template macros'), 1)
			->setModern(true)
		)
		->addRow(null, new CPartial($tmpl, [
			'macros' => $data['macros'],
			'readonly' => $data['readonly']
		]), 'macros_container'),
	TAB_INDICATOR_MACROS
);

// Value mapping.
$tabs->addTab('valuemap-tab', _('Value mapping'), (new CFormList('valuemap-formlist'))->addRow(null,
	new CPartial('configuration.valuemap', [
		'source' => 'template',
		'valuemaps' => $data['valuemaps'],
		'readonly' => $data['readonly'],
		'form' => 'template'
	])),
	TAB_INDICATOR_VALUEMAPS
);

// footer
if ($data['templateid'] != 0 && $data['form'] !== 'full_clone') {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete template?'), url_param('form').url_param('templateid')),
			new CButtonQMessage(
				'delete_and_clear',
				_('Delete and clear'),
				_('Delete and clear template? (Warning: all linked hosts will be cleared!)'),
				url_param('form').url_param('templateid')
			),
			new CButtonCancel()
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($tabs);
$widget->addItem($form);

$widget->show();
