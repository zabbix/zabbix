<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CWidgetFieldMultiselect extends CWidgetField {

	// CMultiSelect object_name, used for auto-complete functionality.
	protected $object_name;

	// The options for the popup selector window.
	protected $popup_options;

	// The options for the popup selector window.
	protected $inaccessible_caption;

	// Is selecting multiple objects or a single one?
	protected $multiple = true;

	// Additional filter parameters for the popup window, set by the user.
	protected $filter_parameters = [];

	/**
	 * Select resource type widget field. Will create text box field with select button,
	 * that will allow to select specified resource.
	 *
	 * @param string $name  Field name in form.
	 * @param string $label Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setDefault([]);
	}

	/**
	 * Set additional validation flags.
	 *
	 * @param int $flags
	 *
	 * @return CWidgetFieldMultiselect
	 */
	public function setFlags($flags) {
		parent::setFlags($flags);

		if ($flags & self::FLAG_NOT_EMPTY) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules, API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules(null);
		}

		return $this;
	}

	/**
	 * Is selecting multiple values or a single value?
	 *
	 * @return bool
	 */
	public function isMultiple() {
		return $this->multiple;
	}

	/**
	 * Set field to multiple items mode.
	 *
	 * @param bool $multiple
	 *
	 * @return CWidgetFieldMultiselect
	 */
	public function setMultiple($multiple) {
		$this->multiple = $multiple;

		return $this;
	}

	/**
	 * Set an additional filter parameter for the popup window.
	 *
	 * @param string $name
	 * @param mixed $value
	 *
	 * @return CWidgetFieldMultiselect
	 */
	public function setFilterParameter($name, $value) {
		$this->filter_parameters[$name] = $value;

		return $this;
	}

	/**
	 * @return CWidgetFieldMultiselect
	 */
	public function setValue($value) {
		$this->value = (array) $value;

		return $this;
	}

	/**
	 * Add field to the form.
	 *
	 * @param CForm $form
	 * @param CFormList $form_list
	 * @param array $scripts
	 *
	 * @return CWidgetFieldMultiselect
	 */
	public function addToForm($form, $form_list, &$scripts) {
		$multiselect = $this->createMultiselect($form->getName());

		$form_list->addRow(
			(new CLabel($this->getLabel(), $this->getName().($this->multiple ? '[]' : '').'_ms'))
				->setAsteriskMark($this->getFlags() & self::FLAG_LABEL_ASTERISK),
			$multiselect
		);

		$scripts[] = $multiselect->getPostJS();

		return $this;
	}

	/**
	 * @param string $object_name CMultiSelect object_name, used for auto-complete functionality.
	 *
	 * @return CWidgetFieldMultiselect
	 */
	protected function setObjectName($object_name) {
		$this->object_name = $object_name;

		return $this;
	}

	/**
	 * @param array $popup_options The options for the popup selector window.
	 *
	 * @return CWidgetFieldMultiselect
	 */
	protected function setPopupOptions(array $popup_options) {
		$this->popup_options = $popup_options;

		return $this;
	}

	/**
	 * @param string $inaccessible_caption The caption for inaccessible items.
	 *
	 * @return CWidgetFieldMultiselect
	 */
	protected function setInaccessibleCaption($inaccessible_caption) {
		$this->inaccessible_caption = $inaccessible_caption;

		return $this;
	}

	/**
	 * Prepare captions for the current values.
	 *
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	protected function prepareCaptions() {
		$values = $this->getValue();

		$captions = $this->getCaptions($values);

		foreach (array_values(array_diff($values, array_keys($captions))) as $n => $id) {
			$captions[$id] = [
				'id' => $id,
				'name' => $this->inaccessible_caption.(($n > 0) ? ' ('.($n + 1).')' : ''),
				'inaccessible' => true
			];
		}

		return $captions;
	}

	/**
	 * Get the created CMultiSelect field.
	 *
	 * @param string $form_name
	 *
	 * @return CMultiSelect
	 */
	protected function createMultiselect($form_name) {
		$field_name = $this->getName().($this->multiple ? '[]' : '');

		return (new CMultiSelect([
			'name' => $field_name,
			'object_name' => $this->object_name,
			'multiple' => $this->multiple,
			'data' => $this->prepareCaptions(),
			'popup' => [
				'parameters' => [
					'dstfrm' => $form_name,
					'dstfld1' => zbx_formatDomId($field_name)
				] + $this->popup_options + $this->filter_parameters
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired($this->getFlags() & self::FLAG_LABEL_ASTERISK)
		;
	}
}
