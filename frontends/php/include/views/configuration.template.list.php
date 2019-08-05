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


require_once dirname(__FILE__).'/js/configuration.template.list.js.php';

$filter_tags = $data['filter']['tags'];
if (!$filter_tags) {
	$filter_tags = [['tag' => '', 'value' => '', 'operator' => TAG_OPERATOR_LIKE]];
}

$filter_tags_table = (new CTable())
	->setId('filter-tags')
	->addRow((new CCol(
		(new CRadioButtonList('filter_evaltype', (int) $data['filter']['evaltype']))
			->addValue(_('And/Or'), TAG_EVAL_TYPE_AND_OR)
			->addValue(_('Or'), TAG_EVAL_TYPE_OR)
			->setModern(true)
		))->setColSpan(4)
	);

$i = 0;
foreach ($filter_tags as $tag) {
	$filter_tags_table->addRow([
		(new CTextBox('filter_tags['.$i.'][tag]', $tag['tag']))
			->setAttribute('placeholder', _('tag'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CRadioButtonList('filter_tags['.$i.'][operator]', (int) $tag['operator']))
			->addValue(_('Contains'), TAG_OPERATOR_LIKE)
			->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
			->setModern(true),
		(new CTextBox('filter_tags['.$i.'][value]', $tag['value']))
			->setAttribute('placeholder', _('value'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
		(new CCol(
			(new CButton('filter_tags['.$i.'][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		))->addClass(ZBX_STYLE_NOWRAP)
	], 'form_row');

	$i++;
}
$filter_tags_table->addRow(
	(new CCol(
		(new CButton('filter_tags_add', _('Add')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-add')
	))->setColSpan(3)
);

$filter = new CFilter(new CUrl('templates.php'));
$filter
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormList())
			->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
					->setAttribute('autofocus', 'autofocus')
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
							'dstfrm' => $filter->getName(),
							'dstfld1' => 'filter_templates_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			),
		(new CFormList())->addRow(_('Tags'), $filter_tags_table)
	]);

$widget = (new CWidget())
	->setTitle(_('Templates'))
	->setControls(new CList([
		(new CForm('get'))
			->cleanItems()
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getGroupsCB()
				])
			),
		(new CTag('nav', true,
			(new CList())
				->addItem(new CRedirectButton(_('Create template'),
				(new CUrl('templates.php'))
					->setArgument('groupid', $data['pageFilter']->groupid)
					->setArgument('form', 'create')
					->getUrl()
				))
				->addItem(
					(new CButton('form', _('Import')))->onClick('redirect("conf.import.php?rules_preset=template")')
				)
		))->setAttribute('aria-label', _('Content controls'))
	]))
	->addItem($filter);

$form = (new CForm())->setName('templates');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_templates'))->onClick("checkAll('".$form->getName()."', 'all_templates', 'templates');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sortField'], $data['sortOrder']),
		_('Applications'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Screens'),
		_('Discovery'),
		_('Web'),
		_('Linked templates'),
		_('Linked to'),
		_('Tags')
	]);

foreach ($data['templates'] as $template) {
	$name = new CLink($template['name'],
		'templates.php?form=update&templateid='.$template['templateid'].url_param('groupid')
	);

	$linkedTemplatesOutput = [];
	$linkedToOutput = [];

	$i = 0;

	order_result($template['parentTemplates'], 'name');

	foreach ($template['parentTemplates'] as $parentTemplate) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$linkedTemplatesOutput[] = ' &hellip;';

			break;
		}

		if ($linkedTemplatesOutput) {
			$linkedTemplatesOutput[] = ', ';
		}

		$url = 'templates.php?form=update&templateid='.$parentTemplate['templateid'].url_param('groupid');

		if (array_key_exists($parentTemplate['templateid'], $data['writable_templates'])) {
			$linkedTemplatesOutput[] = (new CLink($parentTemplate['name'], $url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY);
		}
		else {
			$linkedTemplatesOutput[] = (new CSpan($parentTemplate['name']))
				->addClass(ZBX_STYLE_GREY);
		}
	}

	$i = 0;

	$linkedToObjects = array_merge($template['hosts'], $template['templates']);
	order_result($linkedToObjects, 'name');

	foreach ($linkedToObjects as $linkedToObject) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$linkedToOutput[] = ' &hellip;';

			break;
		}

		if ($linkedToOutput) {
			$linkedToOutput[] = ', ';
		}

		if ($linkedToObject['status'] == HOST_STATUS_TEMPLATE) {
			if (array_key_exists($linkedToObject['templateid'], $data['writable_templates'])) {
				$url = 'templates.php?form=update&templateid='.$linkedToObject['templateid'].url_param('groupid');
				$link = (new CLink($linkedToObject['name'], $url))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY);
			}
			else {
				$link = (new CSpan($linkedToObject['name']))
					->addClass(ZBX_STYLE_GREY);
			}
		}
		else {
			if (array_key_exists($linkedToObject['hostid'], $data['writable_hosts'])) {
				$url = 'hosts.php?form=update&hostid='.$linkedToObject['hostid'].url_param('groupid');
				$link = (new CLink($linkedToObject['name'], $url))->addClass(ZBX_STYLE_LINK_ALT);
			}
			else {
				$link = (new CSpan($linkedToObject['name']));
			}

			$link->addClass($linkedToObject['status'] == HOST_STATUS_MONITORED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);
		}

		$linkedToOutput[] = $link;
	}

	$table->addRow([
		new CCheckBox('templates['.$template['templateid'].']', $template['templateid']),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		[
			new CLink(_('Applications'), 'applications.php?hostid='.$template['templateid'].url_param('groupid')),
			CViewHelper::showNum($template['applications'])
		],
		[
			new CLink(_('Items'),
				(new CUrl('items.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
			),
			CViewHelper::showNum($template['items'])
		],
		[
			new CLink(_('Triggers'),
				(new CUrl('triggers.php'))
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
			),
			CViewHelper::showNum($template['triggers'])
		],
		[
			new CLink(_('Graphs'), 'graphs.php?hostid='.$template['templateid'].url_param('groupid')),
			CViewHelper::showNum($template['graphs'])
		],
		[
			new CLink(_('Screens'), 'screenconf.php?templateid='.$template['templateid']),
			CViewHelper::showNum($template['screens'])
		],
		[
			new CLink(_('Discovery'), 'host_discovery.php?hostid='.$template['templateid']),
			CViewHelper::showNum($template['discoveries'])
		],
		[
			new CLink(_('Web'), 'httpconf.php?hostid='.$template['templateid'].url_param('groupid')),
			CViewHelper::showNum($template['httpTests'])
		],
		$linkedTemplatesOutput,
		$linkedToOutput,
		$data['tags'][$template['templateid']]
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'templates',
		[
			'template.export' => ['name' => _('Export'), 'redirect' =>
				(new CUrl('zabbix.php'))
					->setArgument('action', 'export.templates.xml')
					->setArgument('backurl', (new CUrl('templates.php'))
						->setArgument('groupid', $data['pageFilter']->groupid)
						->setArgument('page', getPageNumber())
						->getUrl())
					->getUrl()
			],
			'template.massupdateform' => ['name' => _('Mass update')],
			'template.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected templates?')],
			'template.massdeleteclear' => ['name' => _('Delete and clear'),
				'confirm' => _('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
			]
		]
	)
]);

$widget->addItem($form);

return $widget;
