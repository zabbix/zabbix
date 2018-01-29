<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

			case WIDGET_FIELD_SELECT_RES_SIMPLE_GRAPH:
			case WIDGET_FIELD_SELECT_RES_ITEM:
				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_ITEM);
				$this->srctbl = 'items';
				$this->srcfld1 = 'itemid';
				$this->srcfld2 = 'name';
				break;

			case WIDGET_FIELD_SELECT_RES_GRAPH:
				$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_GRAPH);
				$this->srctbl = 'graphs';
				$this->srcfld1 = 'graphid';
				$this->srcfld2 = 'name';
				break;
		}

		$this->dstfld1 = $name;
		$this->dstfld2 = $this->name.'_caption';
		$this->setDefault(0);
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

		switch ($this->getResourceType()) {
			case WIDGET_FIELD_SELECT_RES_ITEM:
				$popup_options['real_hosts'] = '1';
				break;

			case WIDGET_FIELD_SELECT_RES_GRAPH:
				$popup_options['real_hosts'] = '1';
				$popup_options['with_graphs'] = '1';
				break;

			case WIDGET_FIELD_SELECT_RES_SIMPLE_GRAPH:
				$popup_options['numeric'] = '1';
				$popup_options['real_hosts'] = '1';
				$popup_options['with_simple_graph_items'] = '1';
				break;
		}

		return $popup_options;
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);

		if (!$errors && $strict && ($this->getFlags() & CWidgetField::FLAG_NOT_EMPTY) && $this->getValue() == 0) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->getLabel(), _('cannot be empty'));
		}

		return $errors;
	}
}
