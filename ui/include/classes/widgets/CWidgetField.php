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


namespace Zabbix\Widgets;

use CApiInputValidator,
	DB;

abstract class CWidgetField {

	public const DEFAULT_VIEW = null;

	public const FLAG_NOT_EMPTY = 0x02;
	public const FLAG_LABEL_ASTERISK = 0x04;
	public const FLAG_DISABLED = 0x08;

	public const FOREIGN_REFERENCE_KEY = '_reference';
	public const REFERENCE_DASHBOARD = 'DASHBOARD';

	protected string $name;
	protected ?string $label;
	protected ?string $label_prefix = null;

	protected ?int $save_type = null;

	protected $value;
	protected $default;

	protected array $values_captions = [];
	protected string $inaccessible_caption = '';

	protected int $max_length;

	protected ?string $action = null;

	protected int $flags = 0x00;

	private array $validation_rules = [];

	private $templateid = null;

	private bool $default_prevented = false;
	private bool $widget_accepted = false;
	private bool $dashboard_accepted = false;

	private string $in_type = '';

	/**
	 * @param string      $name   Field name in form.
	 * @param string|null $label  Label for the field in form.
	 */
	public function __construct(string $name, ?string $label = null) {
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
	 * Prefix field label to enhance clarity in case of error messages. For example:
	 * Invalid parameter "<LABEL PREFIX>: <LABEL>": too many decimal places.
	 */
	public function prefixLabel(?string $prefix): self {
		$this->label_prefix = $prefix;

		return $this;
	}

	/**
	 * Get fully qualified label for displaying in error messages.
	 *
	 * @return string
	 */
	public function getErrorLabel(): string {
		$label = $this->label ?? $this->name;

		if ($this->label_prefix !== null) {
			$label = $this->label_prefix.': '.$label;
		}

		return $label;
	}

	/**
	 * Get field value. If no value is set, return the default value.
	 *
	 * @return mixed
	 */
	public function getValue() {
		return $this->value ?? $this->getDefault();
	}

	public function setValue($value): self {
		$this->value = $value;

		return $this;
	}

	public function getValuesCaptions(): array {
		return $this->values_captions;
	}

	public function setValuesCaptions(array $captions): self {
		$values = [];
		$this->toApi($values);

		$inaccessible = 0;
		foreach ($values as $value) {
			if (array_key_exists($value['type'], $captions)) {
				$this->values_captions[$value['value']] = array_key_exists($value['value'], $captions[$value['type']])
					? $captions[$value['type']][$value['value']]
					: [
						'id' => $value['value'],
						'name' => $this->inaccessible_caption.(++$inaccessible > 1 ? ' ('.$inaccessible.')' : ''),
						'inaccessible' => true
					];
			}
		}

		return $this;
	}

	public function getDefault() {
		return $this->default;
	}

	public function setDefault($value): self {
		$this->default = $value;

		return $this;
	}

	public function isDefaultPrevented(): bool {
		return $this->default_prevented;
	}

	/**
	 * Disable exact object selection, like item or host.
	 *
	 * @return $this
	 */
	public function preventDefault($default_prevented = true): self {
		$this->default_prevented = $default_prevented;

		return $this;
	}

	public function isWidgetAccepted(): bool {
		return $this->widget_accepted;
	}

	/**
	 * Allow selecting widget as reference.
	 *
	 * @return $this
	 */
	public function acceptWidget($widget_accepted = true): self {
		$this->widget_accepted = $widget_accepted;

		return $this;
	}

	public function isDashboardAccepted(): bool {
		return $this->dashboard_accepted;
	}

	/**
	 * Allow selecting dashboard as reference.
	 *
	 * @return $this
	 */
	public function acceptDashboard($dashboard_accepted = true): self {
		$this->dashboard_accepted = $dashboard_accepted;

		return $this;
	}

	public function setInType(string $in_type): self {
		$this->in_type = $in_type;

		return $this;
	}

	public function getInType(): string {
		return $this->in_type;
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

	public function getMaxLength(): int {
		return $this->max_length;
	}

	public function setMaxLength(int $max_length): self {
		$this->max_length = $max_length;

		$this->validation_rules['length'] = $this->max_length;

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
	 * @return int|string|null
	 */
	public function getTemplateId() {
		return $this->templateid;
	}

	public function setTemplateId($templateid): self {
		$this->templateid = $templateid;

		return $this;
	}

	public function isTemplateDashboard(): bool {
		return $this->templateid !== null;
	}

	/**
	 * @param bool $strict  Widget form submit validation?
	 *
	 * @return array  Errors.
	 */
	public function validate(bool $strict = false): array {
		$errors = [];

		$validation_rules = $this->getValidationRules($strict);
		$value = $this->getValue();
		$label = $this->getErrorLabel();

		if (CApiInputValidator::validate($validation_rules, $value, $label, $error)) {
			$this->setValue($value);
		}
		else {
			$this->setValue($this->getDefault());
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

		if ($value === null) {
			return;
		}

		if (is_array($value)) {
			$value = array_values($value);
			$default = $this->getDefault();

			if (!is_array($default) || $value !== array_values($default)) {
				foreach ($value as $index => $each_value) {
					$widget_fields[] = [
						'type' => $this->save_type,
						'name' => $this->name.'.'.$index,
						'value' => $each_value
					];
				}
			}
		}
		elseif ($value !== $this->getDefault()) {
			$widget_fields[] = [
				'type' => $this->save_type,
				'name' => $this->name,
				'value' => $value
			];
		}
	}

	protected function setSaveType($save_type): self {
		switch ($save_type) {
			case ZBX_WIDGET_FIELD_TYPE_INT32:
				$this->validation_rules = ['type' => API_INT32];
				break;

			case ZBX_WIDGET_FIELD_TYPE_STR:
				$this->max_length = DB::getFieldLength('widget_field', 'value_str');

				$this->validation_rules = [
					'type' => API_STRING_UTF8,
					'length' => $this->max_length
				];
				break;

			case ZBX_WIDGET_FIELD_TYPE_GROUP:
			case ZBX_WIDGET_FIELD_TYPE_HOST:
			case ZBX_WIDGET_FIELD_TYPE_ITEM:
			case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
			case ZBX_WIDGET_FIELD_TYPE_GRAPH:
			case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
			case ZBX_WIDGET_FIELD_TYPE_MAP:
			case ZBX_WIDGET_FIELD_TYPE_SERVICE:
			case ZBX_WIDGET_FIELD_TYPE_SLA:
			case ZBX_WIDGET_FIELD_TYPE_USER:
			case ZBX_WIDGET_FIELD_TYPE_ACTION:
			case ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE:
				$this->validation_rules = ['type' => API_IDS];
				break;

			default:
				exit(_('Internal error.'));
		}

		$this->save_type = $save_type;

		return $this;
	}

	protected function getValidationRules(bool $strict = false): array {
		return $this->validation_rules;
	}

	protected function setValidationRules(array $validation_rules): self {
		$this->validation_rules = $validation_rules;

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

	/**
	 * Parse typed reference (a reference to a foreign data source).
	 *
	 * @param string $typed_reference
	 *
	 * @return array
	 */
	public static function parseTypedReference(string $typed_reference): array {
		$separator_index = strpos($typed_reference, '.');

		if ($separator_index === false) {
			return ['reference' => '', 'type' => ''];
		}

		return [
			'reference' => substr($typed_reference, 0, $separator_index),
			'type' => substr($typed_reference, $separator_index + 1)
		];
	}

	/**
	 * Create a typed reference (a reference to a foreign data source).
	 *
	 * @param string $reference
	 * @param string $type
	 *
	 * @return string
	 */
	public static function createTypedReference(string $reference, string $type = ''): string {
		return $type !== '' ? $reference.'.'.$type : $reference;
	}
}
