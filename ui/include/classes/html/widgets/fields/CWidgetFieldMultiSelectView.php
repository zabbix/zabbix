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


use Zabbix\Widgets\Fields\CWidgetFieldMultiSelect;

abstract class CWidgetFieldMultiSelectView extends CWidgetFieldView {

	protected ?CMultiSelect $multiselect = null;

	protected array $data;

	protected bool $custom_select = false;

	protected array $filter_preselect = [];
	protected array $popup_parameters = [];

	public function __construct(CWidgetFieldMultiSelect $field, array $data) {
		$this->field = $field;
		$this->data = $data;
	}

	public function getId(): string {
		return $this->getMultiselect()->getId();
	}

	public function getLabel(): ?CLabel {
		$label = parent::getLabel();

		if ($label !== null) {
			$label->setFor($this->getId().'_ms');
		}

		return $label;
	}

	public function getView(): CMultiSelect {
		return $this->getMultiselect();
	}

	private function getMultiselect(): CMultiSelect {
		if ($this->multiselect === null) {
			$multiselect_name = $this->field->getName().($this->field->isMultiple() ? '[]' : '');

			$options = [
				'name' => $multiselect_name,
				'object_name' => $this->getObjectName(),
				'multiple' => $this->field->isMultiple(),
				'data' => $this->data,
				'add_post_js' => false
			];

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

			$this->multiselect = (new CMultiSelect($options))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired($this->isRequired());
		}

		return $this->multiselect;
	}

	public function getJavaScript(): string {
		return $this->getView()->getPostJS();
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

	protected function getObjectName(): string {
		return '';
	}
}
