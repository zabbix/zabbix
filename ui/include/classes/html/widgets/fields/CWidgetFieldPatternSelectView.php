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


use Zabbix\Widgets\Fields\CWidgetFieldPatternSelect;

abstract class CWidgetFieldPatternSelectView extends CWidgetFieldView {

	protected ?CPatternSelect $patternselect = null;

	protected array $filter_preselect = [];

	protected array $popup_parameters = [];

	protected string $placeholder = '';

	public function __construct(CWidgetFieldPatternSelect $field) {
		$this->field = $field;
		$this->placeholder = _('patterns');
	}

	abstract protected function getObjectName(): string;

	public function getId(): string {
		return $this->getPatternSelect()->getId();
	}

	public function getFocusableElementId(): string {
		return $this->getId().'_ms';
	}

	public function getView(): CMultiSelect {
		return $this->getPatternSelect();
	}

	public function getPatternSelect(): CPatternSelect {
		if ($this->patternselect === null) {
			$patternselect_name = $this->getName().'[]';

			$options = [
				'name' => $patternselect_name,
				'object_name' => $this->getObjectName(),
				'data' => $this->field->getValue(),
				'disabled' => $this->isDisabled(),
				'placeholder' => $this->placeholder,
				'wildcard_allowed' => true,
				'popup' => [
					'parameters' => [
							'dstfrm' => $this->form_name,
							'dstfld1' => zbx_formatDomId($patternselect_name)
						] + $this->getPopupParameters()
				],
				'add_post_js' => false
			];

			if ($this->filter_preselect) {
				$options['popup']['filter_preselect'] = $this->filter_preselect;
			}

			$this->patternselect = (new CPatternSelect($options))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired($this->isRequired());
		}

		return $this->patternselect;
	}

	public function getJavaScript(): string {
		return 'jQuery("#'.$this->getId().'").multiSelect();';
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

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}
}
