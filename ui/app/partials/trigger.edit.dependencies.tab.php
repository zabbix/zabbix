<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @var array $data
 */

$discovered_trigger = array_key_exists('discovered_trigger', $data) ? $data['discovered_trigger'] : false;
$dependency_link = (new CLink(['#{name}'], '#{trigger_url}'))
	->addClass('js-related-trigger-edit')
	->addClass(ZBX_STYLE_WORDWRAP)
	->setAttribute('data-triggerid', '#{triggerid}')
	->setAttribute('data-hostid', $data['hostid'])
	->setAttribute('data-context', $data['context'])
	->setAttribute('data-action', '#{action}');

if (array_key_exists('parent_discoveryid', $data)) {
	$dependency_link
		->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
		->setAttribute('data-prototype', '#{prototype}');
}

$dependency_template_default = (new CTemplateTag('dependency-row-tmpl'))->addItem(
	(new CRow([
		$dependency_link,
		$discovered_trigger
			? null
			: (new CButtonLink(_('Remove')))
			->addClass('js-remove-dependency')
			->setAttribute('data-triggerid', '#{triggerid}'),
		(new CInput('hidden', 'dependencies[]', '#{triggerid}'))
			->setId('dependencies_'.'#{triggerid}')
	]))->setId('dependency_'.'#{triggerid}')
);

$buttons = null;

if (!$discovered_trigger && !array_key_exists('parent_discoveryid', $data)) {
	$buttons = $data['context'] === 'host'
		? (new CButton('add_dep_trigger', _('Add')))
			->setAttribute('data-hostid', $data['hostid'])
			->setId('add-dep-trigger')
			->addClass(ZBX_STYLE_BTN_LINK)
		: new CHorList([
			(new CButton('add_dep_trigger', _('Add')))
				->setAttribute('data-templateid', $data['hostid'])
				->setId('add-dep-template-trigger')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton('add_dep_host_trigger', _('Add host trigger')))
				->setId('add-dep-host-trigger')
				->addClass(ZBX_STYLE_BTN_LINK)
		]);
}
elseif (array_key_exists('parent_discoveryid', $data)) {
	$buttons = $data['context'] === 'host'
		? new CHorList([
			(new CButton('add_dep_trigger', _('Add')))
				->setAttribute('data-hostid', $data['hostid'])
				->setId('add-dep-trigger')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton('add_dep_trigger_prototype', _('Add prototype')))
				->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
				->setId('add-dep-trigger-prototype')
				->addClass(ZBX_STYLE_BTN_LINK)
		])
		: new CHorList([
			(new CButton('add_dep_trigger', _('Add')))
				->setAttribute('data-templateid', $data['hostid'])
				->setId('add-dep-template-trigger')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton('add_dep_trigger_prototype', _('Add prototype')))
				->setAttribute('data-parent_discoveryid', $data['parent_discoveryid'])
				->setId('add-dep-trigger-prototype')
				->addClass(ZBX_STYLE_BTN_LINK),
			(new CButton('add_dep_host_trigger', _('Add host trigger')))
				->setId('add-dep-host-trigger')
				->addClass(ZBX_STYLE_BTN_LINK)
		]);
}

$dependencies_table = (new CTable())
	->setId('dependency-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Name'), $discovered_trigger ? null : _('Action')])
	->addItem((new CTag('tfoot', true))->addItem((new CCol($buttons))->setColSpan(4)))
	->addItem($dependency_template_default);

(new CFormGrid())
	->addItem([new CLabel(_('Dependencies')),
		new CFormField((new CDiv($dependencies_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
	])
	->show();
