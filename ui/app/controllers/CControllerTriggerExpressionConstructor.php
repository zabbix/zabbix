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


class CControllerTriggerExpressionConstructor extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'add_expression' =>					'string',
			'and_expression' =>					'string',
			'expr_target_single' =>				'string',
			'expr_temp' =>						'string',
			'expression' =>						'string',
			'or_expression' =>					'string',
			'readonly' =>						'bool',
			'recovery_expr_target_single' =>	'string',
			'recovery_expression' =>			'string',
			'recovery_expr_temp' =>				'string',
			'remove_expression' =>				'string',
			'replace_expression' =>				'string'
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
		return true;
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
				'expr_target_single' => $this->getInput('recovery_expr_target_single', ''),
				'expression' => $this->getInput('recovery_expression', ''),
				'expr_temp' => $this->getInput('recovery_expr_temp', '')
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
			$data['expr_target_single'] = $this->getInput('remove_expression');
		}

		$data['expression_action'] = $expression_action;
		$expression_type = ($this->hasInput('expression')) ? TRIGGER_EXPRESSION : TRIGGER_RECOVERY_EXPRESSION;
		$data = $this->getTriggerExpressionConstructor($data, $expression_type);

		$data['readonly'] = $this->getInput('readonly', '');
		$data['expression_type'] = ($this->hasInput('expression')) ? TRIGGER_EXPRESSION : TRIGGER_RECOVERY_EXPRESSION;

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}

	function getTriggerExpressionConstructor(array $data, int $expression_type): array {
		$show_message_text = ($expression_type === TRIGGER_EXPRESSION)
			? 'Expression syntax error.'
			: 'Recovery expression syntax error.';

		$analyze = analyzeExpression($data['expression'], $expression_type, $error);

		if ($analyze !== false) {
			[$data['expression_formula'], $data['expression_tree']] = $analyze;

			if ($data['expression_action'] !== '' && $data['expression_tree'] !== null) {
				$new_expr = remakeExpression($data['expression'], $data['expr_target_single'],
					$data['expression_action'], $data['expr_temp'], $error
				);

				if ($new_expr !== false) {
					$data['expression'] = $new_expr;
					$analyze = analyzeExpression($data['expression'], TRIGGER_EXPRESSION, $error);

					if ($analyze !== false) {
						[$data['expression_formula'], $data['expression_tree']] = $analyze;
					}
					else {
						$data['error'] = [
							'title' => $show_message_text,
							'messages' => [_s('Cannot build expression tree: %1$s.', $error)]
						];
					}

					$data['expr_temp'] = '';
				}
				else {
					$data['error'] = [
						'title' => $show_message_text,
						'messages' => [_s('Cannot build expression tree: %1$s.', $error)]
					];
				}
			}
		}
		else {
			$data['error'] = [
				'title' => $show_message_text,
				'messages' => [_s('Cannot build expression tree: %1$s.', $error)]
			];
		}

		return $data;
	}
}
