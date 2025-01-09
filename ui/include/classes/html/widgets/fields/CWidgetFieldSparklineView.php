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


use Zabbix\Widgets\Fields\CWidgetFieldSparkline;

class CWidgetFieldSparklineView extends CWidgetFieldView {

	public const ZBX_STYLE_CLASS = 'widget-field-sparkline';

	protected array $fields_view = [];

	public function __construct(CWidgetFieldSparkline $field) {
		$this->field = $field;
		$group_name = $field->getName();

		foreach ($this->field->getFields() as $group_field) {
			$view_class = $group_field::DEFAULT_VIEW;
			$view = new $view_class($group_field);
			$view->setFormName($this->form_name);

			$index = substr($group_field->getName(), strlen($group_name) + 1, -1);
			$this->fields_view[$index] = $view;
		}

		$this->fields_view['time_period']
			->setDateFormat(ZBX_FULL_DATE_TIME)
			->setFromPlaceholder(_('YYYY-MM-DD hh:mm:ss'))
			->setToPlaceholder(_('YYYY-MM-DD hh:mm:ss'));
	}

	public function setFormName($form_name): self {
		parent::setFormName($form_name);

		foreach ($this->fields_view as $group_field_view) {
			$group_field_view->setFormName($form_name);
		}

		return $this;
	}

	public function getView(): CWidgetFieldsGroupView {
		$group_view = (new CWidgetFieldsGroupView($this->field->getLabel(), [
			$this->fields_view['width'],
			$this->fields_view['color'],
			$this->fields_view['fill']
		]))->addClass(self::ZBX_STYLE_CLASS);

		foreach ($this->fields_view['time_period']->getViewCollection() as $field_view) {
			if ($field_view['label'] !== null) {
				$field_view['label']->addClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1);
			}

			if (is_a($field_view['view'], CWidgetFieldView::class)) {
				$field_view['view'] = $field_view['view']->getView();
			}

			if (is_a($field_view['view'], CMultiSelect::class)) {
				$field_view['view']
					->removeAttribute('style')
					->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
			}

			$group_view->addItem([
				$field_view['label'],
				(new CFormField($field_view['view']))
					->addClass($field_view['class'])
					->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
			]);
		}

		$group_view->addField(
			$this->fields_view['history']->addLabelClass(CFormField::ZBX_STYLE_FORM_FIELD_OFFSET_1)
		);

		return $group_view;
	}

	public function getJavaScript(): string {
		$js = [];

		foreach ($this->fields_view as $group_fields_view) {
			$field_js = trim($group_fields_view->getJavascript());

			if ($field_js !== '') {
				$js[] = $field_js;
			}
		}

		/** @var Zabbix\Widgets\Fields\CWidgetFieldColor $color */
		$color_field = $this->field->getFields()['color'];
		/** @var CWidgetFieldColorView $color_view */
		$color_view = $this->fields_view['color'];

		$js[] = 'jQuery("[name=\"'.$color_view->getName().'\"]").colorpicker({'.
			'appendTo: jQuery("[name=\"'.$color_view->getName().'\"]").closest(".overlay-dialogue-body"),'.
			'use_default: '.json_encode($color_field->hasAllowInherited()).
		'})';

		return implode(';', $js);
	}
}
