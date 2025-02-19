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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelect;

abstract class CWidgetFieldMultiSelectView extends CWidgetFieldView {

	protected ?CMultiSelect $multiselect = null;

	protected bool $custom_select = false;

	protected array $filter_preselect = [];
	protected array $popup_parameters = [];

	protected ?int $input_width = ZBX_TEXTAREA_STANDARD_WIDTH;

	public function __construct(CWidgetFieldMultiSelect $field) {
		$this->field = $field;
	}

	public function setWidth(?int $input_width): self {
		$this->input_width = $input_width;

		return $this;
	}

	abstract protected function getObjectName(): string;

	abstract protected function getObjectLabels(): array;

	public function getId(): string {
		return $this->getMultiSelect()->getId();
	}

	public function getFocusableElementId(): string {
		return $this->getId().'_ms';
	}

	public function getView(): CMultiSelect {
		return $this->getMultiSelect();
	}

	private function getMultiSelect(): CMultiSelect {
		if ($this->multiselect === null) {
			$multiselect_name = $this->field->getName().($this->field->isMultiple() ? '[]' : '');

			$options = [
				'name' => $multiselect_name,
				'object_name' => $this->getObjectName(),
				'multiple' => $this->field->isMultiple(),
				'data' => $this->field->getValuesCaptions(),
				'add_post_js' => false
			];

			if (!$this->field->isDefaultPrevented()) {
				if ($this->custom_select) {
					$options['custom_select'] = true;
				}
				else {
					$options['popup'] = [
						'parameters' => [
							'dstfrm' => $this->form_name,
							'dstfld1' => zbx_formatDomId($multiselect_name)
						] + $this->getPopupParameters()
					];

					if ($this->filter_preselect) {
						$options['popup']['filter_preselect'] = $this->filter_preselect;
					}
				}
			}
			elseif ($this->field->isWidgetAccepted()) {
				$options['custom_select'] = true;
			}

			$this->multiselect = (new CMultiSelect($options))->setAriaRequired($this->isRequired());

			if ($this->input_width !== null) {
				$this->multiselect->setWidth($this->input_width);
			}
		}

		return $this->multiselect;
	}

	public function getJavaScript(): string {
		return '
			document.forms["'.$this->form_name.'"].fields["'.$this->field->getName().'"] =
				new CWidgetFieldMultiselect('.json_encode([
					'multiselect_id' => $this->getMultiSelect()->getId(),
					'field_name' => $this->field->getName(),
					'field_value' => $this->field->getValue(),
					'in_type' => $this->field->getInType(),
					'default_prevented' => $this->field->isDefaultPrevented(),
					'widget_accepted' => $this->field->isWidgetAccepted(),
					'dashboard_accepted' => $this->field->isDashboardAccepted(),
					'object_labels' => $this->getObjectLabels(),
					'params' => $this->getView()->getParams()
				]).');
		';
	}

	public function setFilterPreselect(array $filter_preselect): self {
		$this->filter_preselect = $filter_preselect;

		return $this;
	}

	public function setPopupParameter(string $name, $value): self {
		$this->popup_parameters[$name] = $value;

		return $this;
	}

	protected function getPopupParameters(): array {
		return $this->popup_parameters;
	}
}
