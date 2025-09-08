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

$graph_form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('graph')))->removeId())
	->setId('graph-form')
	->setName('graph_edit_form')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('hostid', $data['hostid'])
	->addVar('context', $data['context'])
	->addVar('graphid', $data['graphid'])
	->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN))
	->addStyle('display: none;');

$is_templated = (bool) $data['templates'];
$readonly = $is_templated || $data['discovered'];

// Preview tab.
$preview_table = (new CTable())
	->addStyle('width: 100%;')
	->addRow(
		(new CRow(
			(new CDiv())->setId('preview-chart')
		))->addClass(ZBX_STYLE_CENTER)
	);

// Append buttons to form.
if ($data['graphid'] != 0) {
	$buttons = [
		[
			'title' => _('Update'),
			'keepOpen' => true,
			'isSubmit' => true,
			'enabled' => !$readonly,
			'action' => 'graph_edit_popup.submit();'
		],
		[
			'title' => _('Clone'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'action' => 'graph_edit_popup.clone();'
		],
		[
			'title' => _('Delete'),
			'confirmation' => _('Delete graph?'),
			'class' => ZBX_STYLE_BTN_ALT,
			'keepOpen' => true,
			'isSubmit' => false,
			'enabled' => !$is_templated,
			'action' => 'graph_edit_popup.delete();'
		]
	];
}
else {
	$buttons = [
		[
			'title' => _('Add'),
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'graph_edit_popup.submit();'
		]
	];
}

$return_url = (new CUrl('zabbix.php'))
	->setArgument('action', 'graph.list')
	->setArgument('context', $data['context'])
	->getUrl();

// Append tabs to form.
$graph_form
	->addItem(
		(new CTabView())
			->addTab('graph-tab',_('Graph'),
				new CPartial('graph.edit.graph.tab', array_merge($data, [
					'readonly' => $readonly,
					'is_templated' => $is_templated,
					'form_name' => $graph_form->getName()
				]))
			)
			->addTab('graph-preview-tab', _('Preview'), $preview_table)
			->setSelected(0)
	)
	->addItem(
		(new CScriptTag('
			graph_edit_popup.init('.json_encode([
				'form_name' => $graph_form->getName(),
				'action' => 'graph.edit',
				'theme_colors' => explode(',', getUserGraphTheme()['colorpalette']),
				'graphs' => [
					'graphid' => $data['graphid'],
					'graphtype' => $data['graphtype'],
					'hostid' => $data['hostid'],
					'is_template' => $data['is_template'],
					'parent_discoveryid' => null
				],
				'readonly' => $readonly,
				'items' => $data['items'],
				'context' => $data['context'],
				'hostid' => $data['hostid'],
				'overlayid' => 'graph.edit',
				'return_url' => $return_url
			]).');
		'))->setOnDocumentReady()
	);

$output = [
	'header' => $data['graphid'] == 0 ? _('New graph') : _('Graph'),
	'doc_url' => CDocHelper::getUrl(CDocHelper::DATA_COLLECTION_GRAPH_EDIT),
	'body' => $graph_form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('graph.edit.js.php'),
	'dialogue_class' => 'modal-popup-large'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
