<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CWidgetFieldSelectResource extends CWidgetField
{
	protected $popup_url_srctbl;
	protected $popup_url_srcfld1;
	protected $popup_url_srcfld2;
	protected $popup_url_dstfld1;
	protected $popup_url_dstfld2;
	protected $caption_name;
	protected $resource_type;
	public $caption;

	public function __construct($name, $label, $resource_type, $value, $caption) {
		parent::__construct($name, $label, null, null);
		$this->resource_type = $resource_type;
		$this->caption = $caption;
		$this->setValue($value);

		switch ($resource_type) {
			case WIDGET_FIELD_SELECT_RES_SYSMAP:
				$this->caption_name = 'sysmap_caption';
				$this->popup_url_srctbl = 'sysmaps';
				$this->popup_url_srcfld1 = 'sysmapid';
				$this->popup_url_srcfld2 = 'name';
				$this->popup_url_dstfld1 = $name;
				$this->popup_url_dstfld2 = $this->caption_name;

				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_MAP);
				break;
			default:
				break;
		}
	}

	public function getPopupUrl() {
		return sprintf('popup.php?srctbl=%s&srcfld1=%s&srcfld2=%s&dstfld1=%s&dstfld2=%s', $this->popup_url_srctbl,
				$this->popup_url_srcfld1, $this->popup_url_srcfld2, $this->popup_url_dstfld1, $this->popup_url_dstfld2);
	}

	public function getCaptionName() {
		return $this->caption_name;
	}

	public function validate() {
		if (is_array($this->value)) {
			$this->value = reset($this->value);
		}

		$errors = [];
		if ($this->required === true && !$this->value) {
			$errors[] = _s('Field \'%s\' is required', $this->label);
		}
		else {
			switch ($this->resource_type) {
				case WIDGET_FIELD_SELECT_RES_SYSMAP:
					$maps = API::Map()->get([
						'sysmapids' => $this->value,
						'output' => ['sysmapid'],
						'preservekeys' => true
					]);

					if (!array_key_exists($this->value, $maps)) {
						$errors[] = _(
								'No permissions to referred object specified in field \'%s\' or it does not exist!', $this->label);
					}
					break;
			}
		}

		return $errors;
	}
}
