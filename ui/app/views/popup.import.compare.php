<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


function drawToc($toc) {
	$list = (new CTag('ul', true));

	foreach ($toc as $change_type => $entity_types) {
		$list->addItem(tocDrawChangeType($change_type, $entity_types));
	}

	$wrapper = (new CDiv())
		->addClass('toc')
		->addItem($list);

	return $wrapper;
}

function tocDrawChangeType($name, $entity_types) {
	$sub_list = (new CTag('ul', true))
		->addClass('sublist');

	foreach ($entity_types as $entity_type => $entities) {
		$sub_list->addItem(drawEntityType($entity_type, $entities));
	}

	$item = (new CTag('li', true))
		->addClass('toc-level1')
		->addItem((new CDiv())
			->addClass('toc-level1-row')
			->addItem((new CTag('button', true))
				->addClass('arrow')
				->addItem((new CSpan())
					->addClass('arrow-down')
				)
			)
			->addItem(new CSpan($name))
		)
		->addItem($sub_list);

	return $item;
}

function drawEntityType($name, $entities) {
	$sub_list = (new CTag('ul', true))
		->addClass('sublist');

	foreach ($entities as $entity) {
		$sub_list->addItem(drawEntity($entity));
	}

	$item = (new CTag('li', true))
		->addClass('toc-level2')
		->addItem((new CDiv())
			->addClass('toc-level2-row')
			->addItem((new CTag('button', true))
				->addClass('arrow')
				->addItem((new CSpan())
					->addClass('arrow-down')
				)
			)
			->addItem(new CSpan($name))
		)
		->addItem($sub_list);

	return $item;
}

function drawEntity($entity) {
	$item = (new CTag('li', true))
		->addClass('toc-level3')
		->addItem((new CDiv())
			->addClass('toc-level3-row')
			->addItem((new CLink($entity['name'], '#importcompare_toc_' . $entity['id']))
				->addClass('item-name')
			)
		);

	return $item;
}

function drawDiff($diff) {
	$wrapper = (new CDiv())
		->addClass('diff');

	$divs = rowsToDivs($diff);
	$pre = new CPre();

	foreach ($divs as $div) {
		$pre->addItem($div);
	}
	$wrapper->addItem($pre);

	return $wrapper;
}

function rowsToDivs($rows) {
	$divs = [];

	$first_characters = [
		CControllerPopupImportCompare::CHANGE_NONE => ' ',
		CControllerPopupImportCompare::CHANGE_ADDED => '+',
		CControllerPopupImportCompare::CHANGE_REMOVED => '-'
	];

	$classes = [
		CControllerPopupImportCompare::CHANGE_ADDED => 'diff-add',
		CControllerPopupImportCompare::CHANGE_REMOVED => 'diff-remove'
	];

	foreach ($rows as $row) {
		$lines = explode("\n", $row['value']);

		foreach ($lines as $index => $line) {
			if ($line === '') {
				continue;
			}

			$text = $first_characters[$row['change_type']] . str_repeat(' ', $row['depth'] * 2 -1) . $line . "\n";
			$div = (new CDiv($text));

			if (array_key_exists('id', $row) && $index === 0) {
				$div->setAttribute('id', 'importcompare_toc_' . $row['id']);
			}

			if (array_key_exists($row['change_type'], $classes)) {
				$div->addClass($classes[$row['change_type']]);
			}

			$divs[] = $div;
		}
	}

	return $divs;
}

if ($data['errors'] !== null) {
	$output = [
		'errors' => $data['errors']
	];
}
else {
	$output = [
		'header' => $data['title'],
		'script_inline' => trim($this->readJsFile('popup.import.compare.js.php')),
		'body' => (!$data['diff'])
			? (new CTableInfo())
					->setNoDataMessage(_('No changes.')) // TODO VM: (?) need a better style
					->toString()
			: (new CForm())
				->addClass('import-compare')
				->addVar('parent_overlayid', $data['parent_overlayid'])
				->addItem(drawToc($data['diff_toc']))
				->addItem(drawDiff($data['diff']))
				->toString(),
		'buttons' => [
			[
				'title' => _('Import'),
				'class' => '',
				'isSubmit' => true,
				'action' => 'return submitImportComparePopup(overlay);'
			],
			[
				'title' => _('Cancel'),
				'cancel' => true,
				'class' => ZBX_STYLE_BTN_ALT,
				'action' => ''
			]
		]
	];
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
