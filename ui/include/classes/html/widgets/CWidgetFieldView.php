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

abstract class CWidgetFieldView {

	protected CWidgetField $field;

	protected string $form_name = '';

	protected array $class = [];
	protected array $label_class = [];

	protected bool $has_label = true;

	protected ?CTag $hint = null;
	protected $help_hint;



	public function setFormName($form_name): self {
		$this->form_name = $form_name;

		return $this;
	}

	public function setHint(CTag $hint): self {
		$this->hint = $hint;

		return $this;
	}

	public function setHelpHint($help_hint): self {
		$this->help_hint = $help_hint;

		return $this;
	}

	public function removeLabel(): self {
		$this->has_label = false;

		return $this;
	}

	public function getLabel(): ?CLabel {
		$label = $this->field->getLabel();

		if ($label === null || !$this->has_label) {
			return null;
		}

		return (new CLabel([$label, $this->hint, $this->help_hint !== null ? makeHelpIcon($this->help_hint) : null]))
			->setFor(zbx_formatDomId($this->field->getName()))
			->addClass($this->label_class ? implode(' ', $this->label_class) : null)
			->setAsteriskMark($this->isRequired());
	}

	/**
	 * @return null|array|CTag
	 */
	public function getView() {
		return null;
	}

	public function getClass(): ?string {
		return $this->class ? implode(' ', $this->class) : null;
	}

	public function addClass(?string $class): self {
		if ($class !== null) {
			$this->class[] = $class;
		}

		return $this;
	}

	public function addLabelClass(?string $label_class): self {
		if ($label_class !== null) {
			$this->label_class[] = $label_class;
		}

		return $this;
	}

	public function addRowClass(?string $row_class): self {
		$this->addLabelClass($row_class);
		$this->addClass($row_class);

		return $this;
	}

	public function getJavaScript(): string {
		return '';
	}

	public function getTemplates(): array {
		return [];
	}

	public function isNotEmpty(): bool {
		return ($this->field->getFlags() & CWidgetField::FLAG_NOT_EMPTY) !== 0;
	}

	public function isRequired(): bool {
		return ($this->field->getFlags() & CWidgetField::FLAG_LABEL_ASTERISK) !== 0;
	}

	public function isDisabled(): bool {
		return ($this->field->getFlags() & CWidgetField::FLAG_DISABLED) !== 0;
	}
}
