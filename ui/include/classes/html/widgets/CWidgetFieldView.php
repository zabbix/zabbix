<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


abstract class CWidgetFieldView {

	protected CWidgetField $field;
	protected string $form_name = '';

	public function setFormName($form_name): self {
		$this->form_name = $form_name;

		return $this;
	}

	public function getLabel(): ?CLabel {
		$label = $this->field->getLabel();

		return $label !== null ? new CLabel($label) : null;
	}

	/**
	 * @return null|array|CTag
	 */
	public function getView() {
		return null;
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
