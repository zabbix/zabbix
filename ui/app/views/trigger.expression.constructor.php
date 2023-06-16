<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @var array $data
 */

$expression_table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([
		$data['readonly'] ? null : _('Target'),
		_('Expression'),
		$data['readonly'] ? null : _('Action'),
		_('Info')
	]);

$allowed_testing = true;

if ($data['expression_type'] === 'expression') {
	if ($data['expression_tree']) {
		foreach ($data['expression_tree'] as $i => $e) {
			$info_icons = [];
			if (isset($e['expression']['levelErrors'])) {
				$allowed_testing = false;
				$errors = [];

				if (is_array($e['expression']['levelErrors'])) {
					foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
						if ($errors) {
							$errors[] = BR();
						}
						$errors[] = $expVal.':'.$errTxt;
					}
				}

				$info_icons[] = makeErrorIcon($errors);
			}

			foreach ($e['list'] as &$obj) {
				if ($obj instanceof CLinkAction && $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
					// Templated or discovered trigger.
					if ($data['readonly']) {
						// Make all links inside inactive.
						$obj = new CSpan($obj->items);
					}
					else {
						$obj->addClass('js-expression');
					}
				}
			}
			unset($obj);

			$expression_table->addRow(
				new CRow([
					!$data['readonly']
						? (new CCheckBox('expr_target_single', $e['id']))
						->setChecked($i == 0)
						->removeId()
						: null,
					(new CDiv($e['list']))->addClass(ZBX_STYLE_WORDWRAP),
					!$data['readonly']
						? (new CCol(
						(new CSimpleButton(_('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('js_remove_expression')
							->setAttribute('data-id', $e['id'])
					))->addClass(ZBX_STYLE_NOWRAP)
						: null,
					makeInformationList($info_icons)
				])
			);
		}
	}
	else {
		$allowed_testing = false;
		$data['expression_formula'] = '';
	}
}

if ($data['expression_type'] === 'recovery_expression') {
	if ($data['recovery_expression_tree']) {
		foreach ($data['recovery_expression_tree'] as $i => $e) {
			$info_icons = [];
			if (isset($e['expression']['levelErrors'])) {
				$allowed_testing = false;
				$errors = [];

				if (is_array($e['expression']['levelErrors'])) {
					foreach ($e['expression']['levelErrors'] as $expVal => $errTxt) {
						if ($errors) {
							$errors[] = BR();
						}
						$errors[] = $expVal.':'.$errTxt;
					}
				}

				$info_icons[] = makeErrorIcon($errors);
			}

			foreach ($e['list'] as &$obj) {
				if ($obj instanceof CLinkAction && $obj->getAttribute('class') == ZBX_STYLE_LINK_ACTION) {
					// Templated or discovered trigger.
					if ($data['readonly']) {
						// Make all links inside inactive.
						$obj = new CSpan($obj->items);
					}
					else {
						$obj->addClass('js-recovery-expression');
					}
				}
			}
			unset($obj);

			$expression_table->addRow(
				new CRow([
					!$data['readonly']
						? (new CCheckBox('recovery_expr_target_single', $e['id']))
						->setChecked($i == 0)
						->onClick('check_target(this, '.TRIGGER_RECOVERY_EXPRESSION.');')
						->removeId()
						: null,
					(new CDiv($e['list']))->addClass(ZBX_STYLE_WORDWRAP),
					!$data['readonly']
						? (new CCol(
						(new CSimpleButton(_('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->setAttribute('data-id', $e['id'])
							->addClass('js_remove_recovery_expression')
					))->addClass(ZBX_STYLE_NOWRAP)
						: null,
					makeInformationList($info_icons)
				])
			);
		}
	}
	else {
		$allowed_testing = false;
		$data['recovery_expression_formula'] = '';
	}
}

$testButton = (new CButton('test_expression', _('Test')))
	->setId(($data['expression_type'] === 'expression') ? 'test-expression' : 'test-recovery-expression')
	->addClass(ZBX_STYLE_BTN_LINK);

if (!$allowed_testing) {
	$testButton->setEnabled(false);
}

if ($data['expression_formula'] === '' || $data['recovery_expression_formula'] === '') {
	$testButton->setEnabled(false);
}

$expression_table->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol($testButton))->setColSpan(4)
		)
);

$wrapOutline = new CSpan([($data['expression_type'] === 'expression')
	? $data['expression_formula']
	: $data['recovery_expression_formula']
]);

$table = new CDiv([
	$wrapOutline,
	BR(),
	(new CDiv($expression_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
]);

$output = ['body' => $table->toString()];

if ($data['expression_type'] === 'expression') {
	$output['expression'] = $data['expression'];
}
else {
	$output['recovery_expression'] = $data['recovery_expression'];
}

echo json_encode($output);
