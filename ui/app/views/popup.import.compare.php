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
			'class' => 'js-import',
			'keepOpen' => true,
			'isSubmit' => true,
			'focused' => true,
			'action' => 'popup_import_compare.submitImportComparePopup('.(bool) $data['with_removed_entities'].');'
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
				->addItem(drawToc($data['diff_toc']))
				->addItem(drawDiff($data['diff']))
				->addItem(
					(new CScriptTag('popup_import_compare.init();'))->setOnDocumentReady()
				)
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

function drawToc(array $toc): CDiv {
	$change_types_list = (new CList())->addClass(ZBX_STYLE_TOC_LIST);

	foreach ($toc as $change_type => $entity_types) {
		$change_types_list->addItem(drawChangeType($change_type, $entity_types));
	}

	return (new CDiv())
		->addClass(ZBX_STYLE_TOC)
		->addItem($change_types_list);
}

function drawChangeType(string $name, array $entity_types): CTag {
	$entity_types_list = (new CList())->addClass(ZBX_STYLE_TOC_SUBLIST);

	foreach ($entity_types as $entity_type => $entities) {
		$entity_types_list->addItem(drawEntityType($entity_type, $entities));
	}

	return new CListItem([
		(new CDiv())
			->addClass(ZBX_STYLE_TOC_ROW)
			->addItem(
				(new CButtonLink([(new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN), $name]))
					->addClass(ZBX_STYLE_TOC_ITEM)
					->addClass(ZBX_STYLE_TOC_ARROW)
			),
		$entity_types_list
	]);
}

function drawEntityType(string $name, array $entities): CTag {
	$entities_list = (new CList())->addClass(ZBX_STYLE_TOC_SUBLIST);

	foreach ($entities as $entity) {
		$entities_list->addItem(drawEntity($entity));
	}

	return new CListItem([
		(new CDiv())
			->addClass(ZBX_STYLE_TOC_ROW)
			->addItem(
				(new CButtonLink([(new CSpan())->addClass(ZBX_STYLE_ARROW_DOWN), $name]))
					->addClass(ZBX_STYLE_TOC_ITEM)
					->addClass(ZBX_STYLE_TOC_ARROW)
			),
		$entities_list
	]);
}

function drawEntity(array $entity): CTag {
	return new CListItem(
		(new CDiv())
			->addClass(ZBX_STYLE_TOC_ROW)
			->addItem(
				(new CLink($entity['name'], '#importcompare_toc_'.$entity['id']))->addClass(ZBX_STYLE_TOC_ITEM)
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
