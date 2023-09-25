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


use Zabbix\Widgets\CWidgetField;

use Zabbix\Widgets\Fields\CWidgetFieldTimePeriod;

class CWidgetFieldTimePeriodView extends CWidgetFieldView {

	private string $date_format = '';

	private ?string $from_placeholder = null;
	private ?string $to_placeholder = null;

	public function __construct(CWidgetFieldTimePeriod $field) {
		$this->field = $field;
	}

	public function getLabel(): ?CLabel {
		$label = parent::getLabel();

		return $label !== null ? $label->setAsteriskMark(false) : null;
	}

	public function getViewCollection(): array {
		$view_collection = [];
		$source_selector_values = [];

		$field_name = $this->field->getName();
		$style_class = $this->getClass() !== null ? $this->getClass().' ' : '';

		if ($this->field->isDashboardAccepted()) {
			$source_selector_values[] = [
				'name' => _('Dashboard'),
				'value' => CWidgetFieldTimePeriod::DATA_SOURCE_DASHBOARD
			];
		}

		if ($this->field->isWidgetAccepted()) {
			$source_selector_values[] = [
				'name' => _('Widget'),
				'value' => CWidgetFieldTimePeriod::DATA_SOURCE_WIDGET
			];
		}

		$source_selector_values[] = [
			'name' => _('Custom'),
			'value' => CWidgetFieldTimePeriod::DATA_SOURCE_DEFAULT
		];

		if (count($source_selector_values) > 1) {
			$source_selector = (new CRadioButtonList($field_name.'[data_source]', $this->field->getDataSource()))
				->setValues($source_selector_values)
				->setModern()
				->setEnabled(!$this->isDisabled());

			$view_collection[] = [
				'label' => $this->getLabel(),
				'view' => $source_selector,
				'class' => $style_class
			];
		}

		if ($this->field->isDashboardAccepted()) {
			$view_collection[] = [
				'label' => null,
				'view' => (new CInput('hidden', $field_name.'['.CWidgetField::FOREIGN_REFERENCE_KEY.']',
					CWidgetField::createTypedReference(CWidgetField::REFERENCE_DASHBOARD, $this->field->getInType())
				))
					->setId($field_name.'_reference_dashboard')
					->setEnabled(!$this->isDisabled()),
				'class' => ZBX_STYLE_DISPLAY_NONE
			];
		}

		if ($this->field->isWidgetAccepted()) {
			$view_collection[] = [
				'label' => (new CLabel(_('Widget')))
					->addClass('js-'.$field_name.'-reference')
					->setAsteriskMark($this->isRequired()),
				'view' =>  (new CMultiSelect([
					'name' => $field_name.'['.CWidgetField::FOREIGN_REFERENCE_KEY.']',
					'add_post_js' => false
				]))
					->setId($field_name.'_reference')
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired($this->isRequired()),
				'class' => $style_class.'js-'.$field_name.'-reference'
			];
		}

		$default_period = $this->field->getDefaultPeriod();

		$date_selector_from = (new CDateSelector($field_name.'[from]', $default_period['from']))
			->setMaxLength($this->field->getMaxLength())
			->setPlaceholder($this->from_placeholder)
			->setEnabled(!$this->isDisabled());

		$date_selector_to = (new CDateSelector($field_name.'[to]', $default_period['to']))
			->setMaxLength($this->field->getMaxLength())
			->setPlaceholder($this->to_placeholder)
			->setEnabled(!$this->isDisabled());

		if ($this->date_format !== '') {
			$date_selector_from->setDateFormat($this->date_format);
			$date_selector_to->setDateFormat($this->date_format);
		}

		array_push($view_collection,
			[
				'label' => (new CLabel($this->field->getFromLabel()))
					->addClass('js-'.$field_name.'-from')
					->setAsteriskMark($this->isRequired()),
				'view' => $date_selector_from,
				'class' => $style_class.'js-'.$field_name.'-from'
			],
			[
				'label' => (new CLabel($this->field->getToLabel()))
					->addClass('js-'.$field_name.'-to')
					->setAsteriskMark($this->isRequired()),
				'view' => $date_selector_to,
				'class' => $style_class.'js-'.$field_name.'-to'
			]
		);

		return $view_collection;
	}

	public function getJavaScript(): string {
		return '
			new CWidgetFieldTimePeriod('.json_encode([
				'field_name' => $this->field->getName(),
				'field_value' => $this->field->getValue(),
				'in_type' => $this->field->getInType(),
				'widget_accepted' => $this->field->isWidgetAccepted(),
				'dashboard_accepted' => $this->field->isDashboardAccepted(),
				'data_source' => $this->field->getDataSource()
			]).');
		';
	}

	public function setDateFormat(string $date_format): self {
		$this->date_format = $date_format;

		return $this;
	}

	public function setFromPlaceholder(string $placeholder): self {
		$this->from_placeholder = $placeholder;

		return $this;
	}

	public function setToPlaceholder(string $placeholder): self {
		$this->to_placeholder = $placeholder;

		return $this;
	}
}
