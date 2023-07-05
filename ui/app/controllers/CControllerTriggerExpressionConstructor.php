<?php declare(strict_types = 0);
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


class CControllerTriggerExpressionConstructor extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'add_expression' =>						'string',
			'and_expression' =>						'string',
			'expr_target_single' =>					'string',
			'expr_temp' =>							'string',
			'expression' =>							'string',
			'or_expression' =>						'string',
			'readonly' =>							'bool',
			'recovery_expr_target_single' =>		'string',
			'recovery_expression' =>				'string',
			'recovery_expr_temp' =>					'string',
			'remove_expression' =>					'string',
			'replace_expression' =>					'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);
	}

	protected function doAction() {
		if ($this->hasInput('expression')) {
			$data = [
				'expr_target_single' => $this->getInput('expr_target_single', ''),
				'expression' => $this->getInput('expression', ''),
				'expr_temp' => $this->getInput('expr_temp', '')
			];
		}
		else {
			$data = [
				'recovery_expr_target_single' => $this->getInput('recovery_expr_target_single', ''),
				'recovery_expression' => $this->getInput('recovery_expression', ''),
				'recovery_expr_temp' => $this->getInput('recovery_expr_temp', '')
			];
		}

		$expression_action = '';

		if ($this->hasInput('and_expression')) {
			$expression_action = 'and';
		}
		elseif ($this->hasInput('or_expression')) {
			$expression_action = 'or';
		}
		elseif ($this->hasInput('replace_expression')) {
			$expression_action = 'r';
		}
		elseif ($this->hasInput('remove_expression')) {
			$expression_action = 'R';
			if ($this->hasInput('expression')) {
				$data['expr_target_single'] = $this->getInput('remove_expression');
			}
			else {
				$data['recovery_expr_target_single'] = $this->getInput('remove_expression');
			}
		}

		if ($this->hasInput('expression')) {
			$data['expression_action'] = $expression_action;
			$data = $this->getTriggerExpressionConstructor($data);
		}
		else {
			$data['recovery_expression_action'] = $expression_action;
			$data = $this->getTriggerRecoveryExpressionContructor($data);
		}

		$data['readonly'] = $this->getInput('readonly', '');
		$data['expression_type'] = ($this->hasInput('expression')) ? TRIGGER_EXPRESSION : TRIGGER_RECOVERY_EXPRESSION;

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	function getTriggerExpressionConstructor($data): array {
		$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION, $error);

		if ($analyze !== false) {
			list($data['expression_formula'], $data['expression_tree']) = $analyze;

			if ($data['expression_action'] !== '' && $data['expression_tree'] !== null) {
				$new_expr = remakeExpression($data['expression'], $data['expr_target_single'],
					$data['expression_action'], $data['expr_temp'], $error
				);

				if ($new_expr !== false) {
					$data['expression'] = $new_expr;
					$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION, $error);

					if ($analyze !== false) {
						list($data['expression_formula'], $data['expression_tree']) = $analyze;
					}
					else {
						error(_s('Cannot build expression tree: %1$s.', $error));
						show_messages(false, '', _('Expression syntax error.'));
					}

					$data['expr_temp'] = '';
				}
				else {
					error(_s('Cannot build expression tree: %1$s.', $error));
					show_messages(false, '', _('Expression syntax error.'));
				}
			}
		}
		else {
			error(_s('Cannot build expression tree: %1$s.', $error));
			show_messages(false, '', _('Expression syntax error.'));
		}

		return $data;
	}

	function getTriggerRecoveryExpressionContructor($data): array {
		$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION, $error);

		if ($analyze !== false) {
			list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;

			if ($data['recovery_expression_action'] !== '' && $data['recovery_expression_tree'] !== null) {
				$new_expr = remakeExpression($data['recovery_expression'], $data['recovery_expr_target_single'],
					$data['recovery_expression_action'], $data['recovery_expr_temp'], $error
				);

				if ($new_expr !== false) {
					$data['recovery_expression'] = $new_expr;
					$analyze = analyzeExpression($data['recovery_expression'], TRIGGER_RECOVERY_EXPRESSION, $error);

					if ($analyze !== false) {
						list($data['recovery_expression_formula'], $data['recovery_expression_tree']) = $analyze;
					}
					else {
						error(_s('Cannot build expression tree: %1$s.', $error));
						show_messages(false, '', _('Recovery expression syntax error.'));
					}

					$data['recovery_expr_temp'] = '';
				}
				else {
					error(_s('Cannot build expression tree: %1$s.', $error));
					show_messages(false, '', _('Recovery expression syntax error.'));
				}
			}
		}
		else {
			error(_s('Cannot build expression tree: %1$s.', $error));
			show_messages(false, '', _('Recovery expression syntax error.'));
		}

		return $data;
	}
}
