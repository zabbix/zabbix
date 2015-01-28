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


class CFormList extends CDiv {

	protected $formTable = null;
	protected $editable = true;
	protected $formInputs = array('ctextbox', 'cnumericbox', 'ctextarea', 'ccombobox', 'ccheckbox', 'cpassbox', 'cipbox');

	public function __construct($id = null, $class = null, $editable = true) {
		$this->editable = $editable;
		$this->formTable = new CTable(null, ZBX_STYLE_TABLE_FORMS);

		parent::__construct();

		if ($id) {
			$this->attr('id', zbx_formatDomId($id));
		}

		$this->attr('class', $class);
	}

	public function addRow($term, $description = null, $hidden = false, $id = null, $class = null) {
		$label = $term;

		if (is_object($description)) {
			$inputClass = strtolower(get_class($description));

			if (in_array($inputClass, $this->formInputs)) {
				$label = new CLabel($term, $description->getAttribute('id'));
			}
		}

		$defaultClass = $hidden ? ZBX_STYLE_HIDDEN : '';

		if ($class === null) {
			$class = $defaultClass;
		}
		else {
			$class .= ' '.$defaultClass;
		}

		if ($description === null) {
			$this->formTable->addRow(array(
				new CCol(SPACE, ZBX_STYLE_TABLE_FORMS_TD_LEFT),
				new CCol($label, ZBX_STYLE_TABLE_FORMS_TD_RIGHT)),
				$class, $id
			);
		}
		else {
			$this->formTable->addRow(array(
				new CCol($label, ZBX_STYLE_TABLE_FORMS_TD_LEFT),
				new CCol($description, ZBX_STYLE_TABLE_FORMS_TD_RIGHT)),
				$class, $id
			);
		}
	}

	public function addInfo($text, $label = null) {
		$this->formTable->addRow(
			array(
				new CDiv($label ? $label : _('Info'), 'dt right listInfoLabel'),
				new CDiv($text, 'objectgroup inlineblock border_dotted ui-corner-all listInfoText')
			),
			'formrow listInfo'
		);
	}

	public function toString($destroy = true) {
		$this->addItem($this->formTable);

		return parent::toString($destroy);
	}

	public function addVar($name, $value, $id = null) {
		if ($value !== null) {
			return $this->addItem(new CVar($name, $value, $id));
		}
	}
}
