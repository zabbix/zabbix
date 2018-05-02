<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


$widget = (new CWidget())
	->setTitle(_('Templates'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem(
			(new CList())
				->addItem([
					new CLabel(_('Group'), 'groupid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$data['pageFilter']->getGroupsCB()
				])
				->addItem(new CSubmit('form', _('Create template')))
				->addItem(
					(new CButton('form', _('Import')))
						->onClick('redirect("conf.import.php?rules_preset=template")')
				)
		)
	)
	->addItem((new CFilter())
		->setProfile('web.templates.filter', 0)
		->addFilterTab(_('Filter'), [(new CFormList())->addRow(_('Name'),
			(new CTextBox('filter_name', $data['filter']['name']))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		)])
	);

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
		_('Linked to')
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
			new CLink(_('Items'), 'items.php?filter_set=1&hostid='.$template['templateid'].url_param('groupid')),
			CViewHelper::showNum($template['items'])
		],
		[
			new CLink(_('Triggers'), 'triggers.php?hostid='.$template['templateid'].url_param('groupid')),
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
		$linkedToOutput
	]);
}

$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'templates',
		[
			'template.export' => ['name' => _('Export')],
			'template.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected templates?')],
			'template.massdeleteclear' => ['name' => _('Delete and clear'),
				'confirm' => _('Delete and clear selected templates? (Warning: all linked hosts will be cleared!)')
			]
		]
	)
]);

$widget->addItem($form);

return $widget;
