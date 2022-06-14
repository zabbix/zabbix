<?php
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


class CWidgetFieldSelectResource extends CWidgetField {

	protected $srctbl;
	protected $srcfld1;
	protected $srcfld2;
	protected $dstfld1;
	protected $dstfld2;
	protected $resource_type;

	/**
	 * Select resource type widget field. Will create text box field with select button,
	 * that will allow to select specified resource.
	 *
	 * @param string $name           field name in form
	 * @param string $label          label for the field in form
	 * @param int    $resource_type  WIDGET_FIELD_SELECT_RES_ constant.
	 */
	public function __construct($name, $label, $resource_type) {
		parent::__construct($name, $label);

		$this->resource_type = $resource_type;

		switch ($resource_type) {
			case WIDGET_FIELD_SELECT_RES_SYSMAP:
				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_MAP);
				$this->srctbl = 'sysmaps';
				$this->srcfld1 = 'sysmapid';
				$this->srcfld2 = 'name';
				break;
		}

		$this->dstfld1 = $name;
		$this->dstfld2 = $this->name.'_caption';
		$this->setDefault('0');
	}

	/**
	 * Set additional flags, which can be used in configuration form.
	 *
	 * @param int $flags
	 *
	 * @return $this
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

	public function getResourceType() {
		return $this->resource_type;
	}

	public function getPopupOptions($dstfrm) {
		$popup_options = [
			'srctbl' => $this->srctbl,
			'srcfld1' => $this->srcfld1,
			'srcfld2' => $this->srcfld2,
			'dstfld1' => $this->dstfld1,
			'dstfld2' => $this->dstfld2,
			'dstfrm' => $dstfrm
		];

		return $popup_options;
	}
}
