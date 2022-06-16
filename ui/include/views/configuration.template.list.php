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

require_once dirname(__FILE__).'/js/configuration.template.list.js.php';

$filter_tags = $data['filter']['tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$filter_tags_table = CTagFilterFieldHelper::getTagFilterField([
	'evaltype' => $data['filter']['evaltype'],
	'tags' => $filter_tags
]);

$filter = (new CFilter())
	->setResetUrl(new CUrl('templates.php'))
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(
				(new CLabel(_('Template groups'), 'filter_groups__ms')),
				(new CMultiSelect([
					'name' => 'filter_groups[]',
					'object_name' => 'templateGroup',
					'data' => $data['filter']['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'template_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_groups_',
							'with_templates' => true,
							'editable' => true,
							'enrich_parent_groups' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(
				(new CLabel(_('Linked templates'), 'filter_templates__ms')),
				(new CMultiSelect([
					'name' => 'filter_templates[]',
					'object_name' => 'templates',
					'data' => $data['filter']['templates'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'templates',
							'srcfld1' => 'hostid',
							'srcfld2' => 'host',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_templates_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
			->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			),
		(new CFormList())->addRow(_('Tags'), $filter_tags_table)
	]);

$widget = (new CWidget())
	->setTitle(_('Templates'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::CONFIGURATION_TEMPLATES_LIST))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create template'),
				(new CUrl('templates.php'))
					->setArgument('groupids', array_keys($data['filter']['groups']))
					->setArgument('form', 'create')
				)
			)
			->addItem(
				(new CButton('form', _('Import')))
					->onClick('return PopUp("popup.import", {rules_preset: "template"},
						{dialogue_class: "modal-popup-generic"}
					);')
					->removeId()
			)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter);

$form = (new CForm())->setName('templates');

// create table
$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_templates'))->onClick("checkAll('".$form->getName()."', 'all_templates', 'templates');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder'],
			(new CUrl('templates.php'))->getUrl()
		),
		_('Hosts'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Dashboards'),
		_('Discovery'),
		_('Web'),
		_('Linked templates'),
		_('Linked to templates'),
		_('Tags')
	]);

foreach ($data['templates'] as $template) {
	$name = new CLink($template['name'], 'templates.php?form=update&templateid='.$template['templateid']);

	$linked_templates_output = [];
	$linked_to_output = [];

	$i = 0;
	foreach ($template['parentTemplates'] as $parent_template) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$linked_templates_output[] = ' &hellip;';

			break;
		}

		if ($linked_templates_output) {
			$linked_templates_output[] = ', ';
		}

		$url = (new CUrl('templates.php'))
			->setArgument('form', 'update')
			->setArgument('templateid', $parent_template['templateid']);

		if (array_key_exists($parent_template['templateid'], $data['editable_templates'])) {
			$linked_templates_output[] = (new CLink($parent_template['name'], $url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$linked_templates_output[] = (new CSpan($parent_template['name']))
				->addClass(ZBX_STYLE_GREY);
		}
	}

	$i = 0;
	foreach ($template['templates'] as $child_template) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$linked_to_output[] = ' &hellip;';

			break;
		}

		if ($linked_to_output) {
			$linked_to_output[] = ', ';
		}

		if (array_key_exists($child_template['templateid'], $data['editable_templates'])) {
			$url = (new CUrl('templates.php'))
				->setArgument('form', 'update')
				->setArgument('templateid', $child_template['templateid']);
			$linked_to_output[] = (new CLink($child_template['name'], $url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$linked_to_output[] = (new CSpan($child_template['name']))
				->addClass(ZBX_STYLE_GREY);
		}
	}

	$table->addRow([
		new CCheckBox('templates['.$template['templateid'].']', $template['templateid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			$data['allowed_ui_conf_hosts']
				? new CLink(_('Hosts'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'host.list')
						->setArgument('filter_set', '1')
						->setArgument('filter_templates', [$template['templateid']])
				)
				: _('Hosts'),
			CViewHelper::showNum(count(array_intersect_key($template['hosts'], $data['editable_hosts'])))
		],
		[
			new CLink(_('Items'),
				(new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['items'])
		],
		[
			new CLink(_('Triggers'),
				(new CUrl('triggers.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['triggers'])
		],
		[
			new CLink(_('Graphs'),
				(new CUrl('graphs.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['graphs'])
		],
		[
			new CLink(_('Dashboards'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'template.dashboard.list')
					->setArgument('templateid', $template['templateid'])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['dashboards'])
		],
		[
			new CLink(_('Discovery'),
				(new CUrl('host_discovery.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['discoveries'])
		],
		[
			new CLink(_('Web'),
				(new CUrl('httpconf.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['httpTests'])
		],
		$linked_templates_output,
		$linked_to_output,
		$data['tags'][$template['templateid']]
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'templates',
		[
			'template.export' => [
				'content' => new CButtonExport('export.templates',
					(new CUrl('templates.php'))
						->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
						->getUrl()
				)
			],
			'popup.massupdate.template' => [
				'content' => (new CButton('', _('Mass update')))
					->onClick(
						"openMassupdatePopup('popup.massupdate.template', {}, {
							dialogue_class: 'modal-popup-static',
							trigger_element: this
						});"
					)
					->addClass(ZBX_STYLE_BTN_ALT)
					->removeAttribute('id')
			],
			'template.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected templates?')],
			'template.massdeleteclear' => ['name' => _('Delete and clear'),
				'confirm' => _('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
			]
		]
	)
]);

$widget->addItem($form);

$widget->show();
