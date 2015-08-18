<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CFormList extends CList {

	protected $editable = true;
	protected $formInputs = ['ctextbox', 'cnumericbox', 'ctextarea', 'ccombobox', 'ccheckbox', 'cpassbox', 'cipbox'];

	public function __construct($id = null) {
		parent::__construct();

		$this->addClass('table-forms');

		if ($id) {
			$this->setId(zbx_formatDomId($id));
		}
	}

	public function addRow($term, $description = null, $id = null, $class = null) {
		$input_id = null;

		$input = $description;
		if (is_array($input)) {
			$input = reset($input);
		}

		if (is_object($input)) {
			$input_class = strtolower(get_class($input));

			if (in_array($input_class, $this->formInputs)) {
				$input_id = $input->getAttribute('id');
			}
		}

		$label = is_object($term) ? $term : new CLabel($term, $input_id);

		if ($description === null) {
			$this->addItem([
				(new CDiv(SPACE))->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
				(new CDiv($label))->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)],
				$class, $id);
		}
		else {
			$this->addItem([
				(new CDiv($label))->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
				(new CDiv($description))->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)],
				$class, $id);
		}

		return $this;
	}

	public function addInfo($text) {
		$this->addItem(
			[
				(new CDiv(_('Info')))->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT),
				(new CDiv(
					(new CDiv($text))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				))->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
			]
		);
		return $this;
	}

	public function toString($destroy = true) {
		return parent::toString($destroy);
	}

	public function addVar($name, $value, $id = null) {
		if ($value !== null) {
			$this->addItem(new CVar($name, $value, $id));
		}
		return $this;
	}
}
