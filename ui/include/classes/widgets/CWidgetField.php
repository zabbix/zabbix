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


namespace Zabbix\Widgets;

use CApiInputValidator;

abstract class CWidgetField {

	public const FLAG_ACKNOWLEDGES = 0x01;
	public const FLAG_NOT_EMPTY = 0x02;
	public const FLAG_LABEL_ASTERISK = 0x04;
	public const FLAG_DISABLED = 0x08;

	protected string $name;
	protected ?string $label;
	protected ?string $full_name = null;

	protected ?int $save_type = null;

	protected $value;
	protected $default;

	protected ?string $action = null;

	protected int $flags = 0x00;

	protected array $validation_rules = [];
	protected ?array $strict_validation_rules = null;
	protected array $ex_validation_rules = [];

	/**
	 * @param string      $name   Field name in form.
	 * @param string|null $label  Label for the field in form.
	 */
	public function __construct(string $name, string $label = null) {
		$this->name = $name;
		$this->label = $label;
		$this->value = null;
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
	}

	public function getName(): string {
		return $this->name;
	}

	public function getLabel(): ?string {
		return $this->label;
	}

	/**
	 * Set field full name which will appear in case of error messages. For example:
	 * Invalid parameter "<FULL NAME>": too many decimal places.
	 */
	public function setFullName(string $name): self {
		$this->full_name = $name;

		return $this;
	}

	/**
	 * Get field value. If no value is set, will return default value.
	 */
	public function getValue() {
		return $this->value ?? $this->default;
	}

	public function setValue($value): self {
		$this->value = $value;

		return $this;
	}

	public function setDefault($value): self {
		$this->default = $value;

		return $this;
	}

	public function getAction(): ?string {
		return $this->action;
	}

	/**
	 * Set JS code that will be called on field change.
	 *
	 * @param string $action  JS function to call on field change.
	 */
	public function setAction(string $action): self {
		$this->action = $action;

		return $this;
	}

	/**
	 * Get additional flags, which can be used in configuration form.
	 */
	public function getFlags(): int {
		return $this->flags;
	}

	/**
	 * Set additional flags, which can be used in configuration form.
	 */
	public function setFlags(int $flags): self {
		$this->flags = $flags;

		return $this;
	}

	/**
	 * @param bool $strict  Widget form submit validation?
	 *
	 * @return array  Errors.
	 */
	public function validate(bool $strict = false): array {
		$errors = [];

		$validation_rules = ($strict && $this->strict_validation_rules !== null)
			? $this->strict_validation_rules
			: $this->validation_rules;
		$validation_rules += $this->ex_validation_rules;

		$value = $this->value ?? $this->default;

		if ($this->full_name !== null) {
			$label = $this->full_name;
		}
		else {
			$label = $this->label ?? $this->name;
		}

		if (CApiInputValidator::validate($validation_rules, $value, $label, $error)) {
			$this->setValue($value);
		}
		else {
			$this->setValue($this->default);
			$errors[] = $error;
		}

		return $errors;
	}

	/**
	 * Prepares array entry for widget field, ready to be passed to CDashboard API functions.
	 * Reference is needed here to avoid array merging in CWidgetForm::fieldsToApi method. With large number of widget
	 * fields it causes significant performance decrease.
	 *
	 * @param array $widget_fields  reference to Array of widget fields.
	 */
	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		if ($value !== null && $value !== $this->default) {
			$widget_field = [
				'type' => $this->save_type,
				'name' => $this->name
			];

			if (is_array($value)) {
				foreach ($value as $val) {
					$widget_field['value'] = $val;
					$widget_fields[] = $widget_field;
				}
			}
			else {
				$widget_field['value'] = $value;
				$widget_fields[] = $widget_field;
			}
		}
	}

	protected function setSaveType($save_type): self {
		switch ($save_type) {
			case ZBX_WIDGET_FIELD_TYPE_INT32:
				$this->validation_rules = ['type' => API_INT32];
				break;

			case ZBX_WIDGET_FIELD_TYPE_STR:
				$this->validation_rules = ['type' => API_STRING_UTF8, 'length' => 255];
				break;

			case ZBX_WIDGET_FIELD_TYPE_GROUP:
			case ZBX_WIDGET_FIELD_TYPE_HOST:
			case ZBX_WIDGET_FIELD_TYPE_ITEM:
			case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
			case ZBX_WIDGET_FIELD_TYPE_GRAPH:
			case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
			case ZBX_WIDGET_FIELD_TYPE_SERVICE:
			case ZBX_WIDGET_FIELD_TYPE_SLA:
			case ZBX_WIDGET_FIELD_TYPE_USER:
			case ZBX_WIDGET_FIELD_TYPE_ACTION:
			case ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE:
				$this->validation_rules = ['type' => API_IDS];
				break;

			case ZBX_WIDGET_FIELD_TYPE_MAP:
				$this->validation_rules = ['type' => API_ID];
				break;

			default:
				exit(_('Internal error.'));
		}

		$this->save_type = $save_type;

		return $this;
	}

	protected function getValidationRules(): array {
		return $this->validation_rules;
	}

	protected function setValidationRules(array $validation_rules): self {
		$this->validation_rules = $validation_rules;

		return $this;
	}

	/**
	 * Set validation rules for "strict" mode.
	 */
	protected function setStrictValidationRules(array $strict_validation_rules = null): self {
		$this->strict_validation_rules = $strict_validation_rules;

		return $this;
	}

	protected function setExValidationRules(array $ex_validation_rules): self {
		$this->ex_validation_rules = $ex_validation_rules;

		return $this;
	}

	/**
	 * Set additional flags for validation rule array.
	 */
	protected static function setValidationRuleFlag(array &$validation_rule, int $flag): void {
		if (array_key_exists('flags', $validation_rule)) {
			$validation_rule['flags'] |= $flag;
		}
		else {
			$validation_rule['flags'] = $flag;
		}
	}
}
