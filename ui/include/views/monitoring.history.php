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
$this->includeJsFile('monitoring.history.js.php');

$web_layout_mode = CViewHelper::loadLayoutMode();

$historyWidget = (new CWidget())->setWebLayoutMode($web_layout_mode);

$header = [
	'left' => _n('%1$s item', '%1$s items', count($data['items'])),
	'right' => (new CForm('get'))
		->cleanItems()
		->setName('filter_view_as')
		->addVar('itemids', $data['itemids'])
];
$header_row = [];

$same_host = true;
$items_numeric = true;
$host_name = '';

if ($data['items']) {
	$first_item = reset($data['items']);
	$host_name = $first_item['hosts'][0]['name'];

	foreach ($data['items'] as $item) {
		$same_host = ($same_host && $host_name === $item['hosts'][0]['name']);
		$items_numeric = ($items_numeric && array_key_exists($item['value_type'], $data['iv_numeric']));
	}
}

if ((count($data['items']) == 1 || $same_host) && $data['itemids']) {
	$header['left'] = [
		$host_name,
		NAME_DELIMITER,
		count($data['items']) == 1 ? $item['name'] : $header['left']
	];
	$header_row[] = implode('', $header['left']);
}
else {
	$header_row[] = $header['left'];
}

if (hasRequest('filter_task')) {
	$header['right']->addVar('filter_task', $data['filter_task']);
}
if (hasRequest('filter')) {
	$header['right']->addVar('filter', $data['filter']);
}
if (hasRequest('mark_color')) {
	$header['right']->addVar('mark_color', $data['mark_color']);
}

$actions = [
	HISTORY_GRAPH => _('Graph'),
	HISTORY_VALUES => _('Values'),
	HISTORY_LATEST => _('500 latest values')
];

if (!$items_numeric) {
	unset($actions[HISTORY_GRAPH]);
}
elseif (count($data['items']) > 1) {
	unset($actions[HISTORY_LATEST]);
}

$action_list = (new CList())
	->addItem([
		new CLabel(new CLabel(_('View as'), 'label-view-as')),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSelect('action'))
			->setId('filter-view-as')
			->setFocusableElementId('label-view-as')
			->setValue($data['action'])
			->addOptions(CSelect::createOptionsFromArray($actions))
			->setDisabled(!$data['items'])
	]);

if ($data['action'] !== HISTORY_GRAPH && $data['action'] !== HISTORY_BATCH_GRAPH) {
	$action_list->addItem((new CSubmit('plaintext', _('As plain text')))->setEnabled((bool) $data['items']));
}

if ($data['action'] == HISTORY_GRAPH && count($data['items']) == 1) {
	$action_list->addItem(get_icon('favourite', [
		'fav' => 'web.favorite.graphids',
		'elid' => $item['itemid'],
		'elname' => 'itemid'
	]));
}

$action_list->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]));

$header['right']->addItem($action_list);

// create filter
$filter_form = new CFilter();
$filter_tab = [];

if ($data['action'] == HISTORY_LATEST || $data['action'] == HISTORY_VALUES) {
	if (array_key_exists($data['value_type'], $data['iv_string']) || !$data['itemids']) {
		$filter_form->addVar('action', $data['action']);

		$items_data = [];
		if ($data['items']) {
			foreach ($data['items'] as $itemid => $item) {
				if (!array_key_exists($item['value_type'], $data['iv_string'])) {
					unset($data['items'][$itemid]);
					continue;
				}

				$items_data[] = [
					'id' => $itemid,
					'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER,
					'name' => $item['name']
				];
			}
			CArrayHelper::sort($items_data, ['prefix', 'name']);
		}

		if ($data['value_type'] == ITEM_VALUE_TYPE_LOG || $data['value_type'] == ITEM_VALUE_TYPE_TEXT
				|| !$data['itemids']) {
			$filterColumn1 = (new CFormList())
				->addRow((new CLabel(_('Items list'), 'itemids__ms')),
					(new CMultiSelect([
						'name' => 'itemids[]',
						'object_name' => 'items',
						'data' => $items_data,
						'popup' => [
							'parameters' => [
								'srctbl' => 'items',
								'srcfld1' => 'itemid',
								'dstfld1' => 'itemids_',
								'real_hosts' => true,
								'value_types' => [ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]
							]
						]
					]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
				->addRow(_('Value'),
					(new CTextBox('filter', $data['filter']))
						->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
						->removeId()
				);

			$tasks = [];
			$tasks[] = (new CSelect('filter_task'))
				->setValue($data['filter_task'])
				->setId('filter-task')
				->setFocusableElementId('label-selected')
				->addOptions(CSelect::createOptionsFromArray([
					FILTER_TASK_SHOW => _('Show selected'),
					FILTER_TASK_HIDE => _('Hide selected'),
					FILTER_TASK_MARK => _('Mark selected'),
					FILTER_TASK_INVERT_MARK => _('Mark others')
				]));

			if (str_in_array($data['filter_task'], [FILTER_TASK_MARK, FILTER_TASK_INVERT_MARK])) {
				$tasks[] = ' ';
				$tasks[] = (new CSelect('mark_color'))
					->setValue(getRequest('mark_color', 0))
					->addOptions(CSelect::createOptionsFromArray([
						MARK_COLOR_RED => _('as Red'),
						MARK_COLOR_GREEN => _('as Green'),
						MARK_COLOR_BLUE => _('as Blue')
					]));
			}

			$filterColumn1->addRow(new CLabel(_('Selected'), $tasks[0]->getFocusableElementId()), $tasks);
			$filter_tab[] = $filterColumn1;
		}
	}
}

// create history screen
if ($data['itemids']) {
	$screen = CScreenBuilder::getScreen([
		'resourcetype' => SCREEN_RESOURCE_HISTORY,
		'action' => $data['action'],
		'itemids' => $data['itemids'],
		'pageFile' => (new CUrl('history.php'))
			->setArgument('action', $data['action'])
			->setArgument('itemids', $data['itemids'])
			->setArgument('filter', $data['filter'])
			->setArgument('filter_task', $data['filter_task'])
			->setArgument('mark_color', $data['mark_color'])
			->getUrl(),
		'profileIdx' => $data['profileIdx'],
		'profileIdx2' => $data['profileIdx2'],
		'from' => $data['from'],
		'to' => $data['to'],
		'page' => $data['page'],
		'filter' => $data['filter'],
		'filter_task' => $data['filter_task'],
		'mark_color' => $data['mark_color'],
		'plaintext' => $data['plaintext'],
		'graphtype' => $data['graphtype']
	]);
}

// append plaintext to widget
if ($data['plaintext']) {
	foreach ($header_row as $text) {
		$historyWidget->addItem([new CSpan($text), BR()]);
	}

	if ($data['itemids']) {
		$screen = $screen->get();
		$pre = new CPre();
		foreach ($screen as $text) {
			$pre->addItem([$text, BR()]);
		}
		$historyWidget->addItem($pre);
	}
}
else {
	$historyWidget
		->setTitle($header['left'])
		->setDocUrl(CDocHelper::getUrl(CDocHelper::MONITORING_HISTORY))
		->setControls((new CTag('nav', true, $header['right']))->setAttribute('aria-label', _('Content controls')));

	if ($data['itemids'] && $data['action'] !== HISTORY_LATEST) {
		$filter_form->addTimeSelector($screen->timeline['from'], $screen->timeline['to'],
			$web_layout_mode != ZBX_LAYOUT_KIOSKMODE);
	}

	if ($data['action'] == HISTORY_BATCH_GRAPH) {
		$filter_form
			->hideFilterButtons()
			->addVar('action', $data['action'])
			->addVar('itemids', $data['itemids']);

		$filter_tab = [
			(new CFormList())->addRow(_('Graph type'),
				(new CRadioButtonList('graphtype', (int) $data['graphtype']))
					->addValue(_('Normal'), GRAPH_TYPE_NORMAL)
					->addValue(_('Stacked'), GRAPH_TYPE_STACKED)
					->setModern(true)
					->onChange('jQuery(this).closest("form").submit();')
			)
		];
	}

	$filter_form
		->setProfile($data['profileIdx'], $data['profileIdx2'])
		->setActiveTab($data['active_tab']);

	if ($filter_tab) {
		$filter_form->addFilterTab(_('Filter'), $filter_tab);
	}

	if ($data['itemids']) {
		if ($data['action'] !== HISTORY_LATEST) {
			$historyWidget->addItem($filter_form);
		}

		$historyWidget->addItem($screen->get());

		if ($data['action'] !== HISTORY_LATEST) {
			CScreenBuilder::insertScreenStandardJs($screen->timeline);
		}
	}
	else {
		if ($filter_tab) {
			$historyWidget->addItem($filter_form);
		}

		$historyWidget->addItem(
			(new CTableInfo())
				->setHeader([
					(new CColHeader(_('Timestamp')))->addClass(ZBX_STYLE_CELL_WIDTH),
					(new CColHeader(_('Local time')))->addClass(ZBX_STYLE_CELL_WIDTH),
					_('Value')
				])
				->setNoDataMessage(_('Specify some filter condition to see the values.'))
		);
	}
}

$historyWidget->show();
