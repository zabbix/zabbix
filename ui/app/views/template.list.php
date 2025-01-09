<?php declare(strict_types = 0);
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

$this->addJsFile('class.tagfilteritem.js');
$this->includeJsFile('template.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('templates');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Templates'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_TEMPLATES_LIST))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(
				(new CSimpleButton(_('Create template')))
					->setAttribute('data-groupids', json_encode(array_keys($data['filter']['groups'])))
					->setId('js-create'))
			->addItem((new CSimpleButton(_('Import')))->setId('js-import'))
	))->setAttribute('aria-label', _('Content controls')));

$action_url = (new CUrl('zabbix.php'))->setArgument('action', $data['action']);

$filter_tags_table = CTagFilterFieldHelper::getTagFilterField([
	'evaltype' => $data['filter']['evaltype'],
	'tags' => $data['filter']['tags']
]);

$filter = (new CFilter())
	->setResetUrl($action_url)
	->setProfile($data['profileIdx'])
	->setActiveTab($data['active_tab'])
	->addVar('action', $data['action'], 'filter_action')
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				(new CLabel(_('Template groups'), 'filter_groups__ms')),
				new CFormField(
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
			])
		->addItem([
			(new CLabel(_('Linked templates'), 'filter_templates__ms')),
			new CFormField(
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
		])
		->addItem([
			new CLabel(_('Name'), 'filter_name'),
			new CFormField((new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
		])
		->addItem([
			new CLabel(_('Vendor'), 'filter_vendor_name'),
			new CFormField(
				(new CTextBox('filter_vendor_name', $data['filter']['vendor_name'], false,
					DB::getFieldLength('hosts', 'vendor_name')
				))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Version'), 'filter_vendor_version'),
			new CFormField(
				(new CTextBox('filter_vendor_version', $data['filter']['vendor_version'], false,
					DB::getFieldLength('hosts', 'vendor_version'))
				)->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
		]),

		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([new CLabel(_('Tags')), new CFormField($filter_tags_table)])
	]);

$html_page->addItem($filter);

$form = (new CForm())
	->setName('templates');

// Create table.
$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_templates'))->onClick("checkAll('".$form->getName()."', 'all_templates', 'templates');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort_field'], $data['sort_order'], $action_url->getUrl()),
		_('Hosts'),
		_('Items'),
		_('Triggers'),
		_('Graphs'),
		_('Dashboards'),
		_('Discovery'),
		_('Web'),
		_('Vendor'),
		_('Version'),
		_('Linked templates'),
		_('Linked to templates'),
		_('Tags')
	])
	->setPageNavigation($data['paging']);

foreach ($data['templates'] as $template) {
	$template_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'popup')
		->setArgument('popup', 'template.edit')
		->setArgument('templateid', $template['templateid'])
		->getUrl();

	$name = (new CLink($template['name'], $template_url))
		->setAttribute('data-templateid', $template['templateid'])
		->setAttribute('data-action', 'template.edit');

	$linked_templates_output = [];
	$linked_to_output = [];

	$i = 0;
	foreach ($template['parentTemplates'] as $parent_template) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$linked_templates_output[] = [' ', HELLIP()];

			break;
		}

		if ($linked_templates_output) {
			$linked_templates_output[] = ', ';
		}

		if (array_key_exists($parent_template['templateid'], $data['editable_templates'])) {
			$linked_template_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $parent_template['templateid'])
				->getUrl();

			$linked_templates_output[] = (new CLink($parent_template['name'], $linked_template_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY)
				->setAttribute('data-templateid', $parent_template['templateid'])
				->setAttribute('data-action', 'template.edit');
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
			$linked_to_output[] = [' ', HELLIP()];

			break;
		}

		if ($linked_to_output) {
			$linked_to_output[] = ', ';
		}

		if (array_key_exists($child_template['templateid'], $data['editable_templates'])) {
			$linked_to_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'template.edit')
				->setArgument('templateid', $child_template['templateid'])
				->getUrl();

			$linked_to_output[] = (new CLink($child_template['name'], $linked_to_url))
				->addClass(ZBX_STYLE_LINK_ALT)
				->addClass(ZBX_STYLE_GREY)
				->setAttribute('data-templateid', $child_template['templateid'])
				->setAttribute('data-action', 'template.edit');
		}
		else {
			$linked_to_output[] = (new CSpan($child_template['name']))
				->addClass(ZBX_STYLE_GREY);
		}
	}

	$table->addRow([
		new CCheckBox('templates['.$template['templateid'].']', $template['templateid']),
		(new CCol($name))->addClass(ZBX_STYLE_WORDBREAK),
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
				(new CUrl('zabbix.php'))
					->setArgument('action', 'item.list')
					->setArgument('filter_set', '1')
					->setArgument('filter_hostids', [$template['templateid']])
					->setArgument('context', 'template')
			),
			CViewHelper::showNum($template['items'])
		],
		[
			new CLink(_('Triggers'),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'trigger.list')
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
		(new CCol($template['vendor_name']))->addClass(ZBX_STYLE_WORDBREAK),
		$template['vendor_version'],
		(new CCol($linked_templates_output))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($linked_to_output))->addClass(ZBX_STYLE_WORDBREAK),
		$data['tags'][$template['templateid']]
	]);
}

$form->addItem([
	$table,
	new CActionButtonList('action', 'templates', [
		'template.export' => [
			'content' => new CButtonExport('export.templates',
				(new CUrl('zabbix.php'))
					->setArgument('action', 'templates.list')
					->setArgument('page', ($data['page'] == 1) ? null : $data['page'])
					->getUrl()
			)
		],
		'template.massupdate' => [
			'content' => (new CSimpleButton(_('Mass update')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massupdate')
				->addClass('js-no-chkbxrange')
		],
		'template.massdelete' => [
			'content' => (new CSimpleButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete')
				->addClass('js-no-chkbxrange')
		],
		'template.massdeleteclear' => [
			'content' => (new CSimpleButton(_('Delete and clear')))
				->addClass(ZBX_STYLE_BTN_ALT)
				->addClass('js-massdelete-clear')
				->addClass('js-no-chkbxrange')
		]
	], 'templates')
]);

$html_page
	->addItem($form)
	->show();

(new CScriptTag('view.init();'))
	->setOnDocumentReady()
	->show();
