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


function drawToc(array $toc): CDiv {
	$change_types_list = (new CTag('ul', true))
		->addClass(ZBX_STYLE_TOC_LIST);

	foreach ($toc as $change_type => $entity_types) {
		$change_types_list->addItem(drawChangeType($change_type, $entity_types));
	}

	return (new CDiv())
		->addClass(ZBX_STYLE_TOC)
		->addItem($change_types_list);
}

function drawChangeType(string $name, array $entity_types): CTag {
	$entity_types_list = (new CTag('ul', true))
		->addClass(ZBX_STYLE_TOC_SUBLIST);

	foreach ($entity_types as $entity_type => $entities) {
		$entity_types_list->addItem(drawEntityType($entity_type, $entities));
	}

	return (new CTag('li', true))
		->addItem((new CDiv())
			->addClass(ZBX_STYLE_TOC_ROW)
			->addItem((new CTag('button', true))
				->addClass(ZBX_STYLE_TOC_ITEM)
				->addClass(ZBX_STYLE_TOC_ARROW)
				->addItem((new CSpan())
					->addClass(ZBX_STYLE_ARROW_DOWN)
				)
				->addItem($name)
			)
		)
		->addItem($entity_types_list);
}

function drawEntityType(string $name, array $entities): CTag {
	$entities_list = (new CTag('ul', true))
		->addClass(ZBX_STYLE_TOC_SUBLIST);

	foreach ($entities as $entity) {
		$entities_list->addItem(drawEntity($entity));
	}

	return (new CTag('li', true))
		->addItem((new CDiv())
			->addClass(ZBX_STYLE_TOC_ROW)
			->addItem((new CTag('button', true))
				->addClass(ZBX_STYLE_TOC_ITEM)
				->addClass(ZBX_STYLE_TOC_ARROW)
				->addItem((new CSpan())
					->addClass(ZBX_STYLE_ARROW_DOWN)
				)
				->addItem($name)
			)
		)
		->addItem($entities_list);
}

function drawEntity(array $entity): CTag {
	return (new CTag('li', true))
		->addItem((new CDiv())
			->addClass(ZBX_STYLE_TOC_ROW)
			->addItem((new CLink($entity['name'], '#importcompare_toc_'.$entity['id']))
				->addClass(ZBX_STYLE_TOC_ITEM)
			)
		);
}

function drawDiff(array $diff): CDiv {
	return (new CDiv())
		->addClass(ZBX_STYLE_DIFF)
		->addItem(new CPre(rowsToDivs($diff)));
}

function rowsToDivs(array $rows): array {
	$divs = [];

	$first_characters = [
		CControllerPopupImportCompare::CHANGE_NONE => ' ',
		CControllerPopupImportCompare::CHANGE_ADDED => '+',
		CControllerPopupImportCompare::CHANGE_REMOVED => '-'
	];

	$classes = [
		CControllerPopupImportCompare::CHANGE_ADDED => ZBX_STYLE_DIFF_ADDED,
		CControllerPopupImportCompare::CHANGE_REMOVED => ZBX_STYLE_DIFF_REMOVED
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

if (array_key_exists('error', $data)) {
	$output = [
		'error' => $data['error']
	];
}
else {
	$buttons = [];

	if ($data['diff']) {
		$buttons[] = [
			'title' => _('Import'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'focused' => true,
			'action' => 'submitImportComparePopup(overlay);'
		];
	}

	$buttons[] = [
		'title' => $data['diff'] ? _('Cancel') : _('Close'),
		'cancel' => true,
		'class' => ZBX_STYLE_BTN_ALT,
		'action' => ''
	];

	$output = [
		'header' => $data['title'],
		'script_inline' => trim($this->readJsFile('popup.import.compare.js.php')),
		'body' => !$data['diff']
			? (new CTableInfo())
				->setNoDataMessage(_('No changes.'))
				->toString()
			: (new CForm())
				->addClass('import-compare')
				->addVar('import_overlayid', $data['import_overlayid'])
				->addItem(drawToc($data['diff_toc']))
				->addItem(drawDiff($data['diff']))
				->toString(),
		'buttons' => $buttons,
		'no_changes' => !$data['diff']
	];
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
