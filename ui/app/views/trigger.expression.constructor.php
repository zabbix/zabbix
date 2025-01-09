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
	$output['error'] = $data['error'];
	$output['error']['expression'] = $data['expression'];
}
else {
	$expression_table = (new CTable())
		->setHeader([
			$data['readonly'] ? null : _('Target'),
			_('Expression'),
			$data['readonly'] ? null : _('Action'),
			_('Info')
		]);

	$allowed_testing = true;

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
						$errors[] = $expVal.': '.$errTxt;
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

						// Decode HTML entities in trigger expressions.
						foreach ($obj->items as &$obj_item) {
							$obj_item = htmlspecialchars_decode($obj_item, ENT_NOQUOTES);
						}
						unset($obj_item);
					}
					else {
						$obj->addClass(($data['expression_type'] === TRIGGER_EXPRESSION)
							? 'js-expression'
							: 'js-recovery-expression'
						);
					}
				}
			}
			unset($obj);

			$expression_table->addRow(
				new CRow([
					!$data['readonly']
						? (new CCheckBox(($data['expression_type'] === TRIGGER_EXPRESSION)
							? 'expr_target_single' : 'recovery_expr_target_single', $e['id']
						))
							->setChecked($i == 0)
							->addClass(($data['expression_type'] === TRIGGER_EXPRESSION)
								? 'js-check-target'
								: 'js-check-recovery-target'
							)
							->removeId()
						: null,
					(new CDiv($e['list']))->addClass(ZBX_STYLE_WORDBREAK),
					!$data['readonly']
						? (new CCol((new CButtonLink(_('Remove')))
							->addClass(($data['expression_type'] === TRIGGER_EXPRESSION)
								? 'js_remove_expression'
								: 'js_remove_recovery_expression'
							)
							->setAttribute('data-id', $e['id']))
						)->addClass(ZBX_STYLE_NOWRAP)
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

	$testButton = (new CButton('test_expression', _('Test')))
		->setId(($data['expression_type'] === TRIGGER_EXPRESSION) ? 'test-expression' : 'test-recovery-expression')
		->addClass(ZBX_STYLE_BTN_LINK);

	if (!$allowed_testing) {
		$testButton->setEnabled(false);
	}

	if ($data['expression_formula'] === '') {
		$testButton->setEnabled(false);
	}

	$expression_table->addItem(
		(new CTag('tfoot', true))->addItem(
			(new CCol($testButton))->setColSpan(4)
		)
	);

	$output = [
		'body' => implode('', [
			$data['expression_formula'] ? new CDiv([$data['expression_formula']]) : '',
			(new CDiv($expression_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		]),
		'expression' => $data['expression']
	];
}

echo json_encode($output);
