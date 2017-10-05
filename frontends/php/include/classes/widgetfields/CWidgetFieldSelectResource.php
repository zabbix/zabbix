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

	public function getPopupUrl() {
		$url = (new CUrl('popup.php'))
			->setArgument('srctbl', $this->srctbl)
			->setArgument('srcfld1', $this->srcfld1)
			->setArgument('srcfld2', $this->srcfld2)
			->setArgument('dstfld1', $this->dstfld1)
			->setArgument('dstfld2', $this->dstfld2);

		switch ($this->getResourceType()) {
			case WIDGET_FIELD_SELECT_RES_ITEM:
				$url->setArgument('real_hosts', '1');
				break;

			case WIDGET_FIELD_SELECT_RES_GRAPH:
				$url->setArgument('real_hosts', '1');
				$url->setArgument('with_graphs', '1');
				break;

			case WIDGET_FIELD_SELECT_RES_SIMPLE_GRAPH:
				$url->setArgument('numeric', '1');
				$url->setArgument('real_hosts', '1');
				$url->setArgument('with_simple_graph_items', 1);
				break;
		}

		return $url->getUrl();
	}

	public function validate($strict = false) {
		$errors = parent::validate($strict);

		if (!$errors && $strict && ($this->getFlags() & CWidgetField::FLAG_NOT_EMPTY) && $this->getValue() == 0) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->getLabel(), _('cannot be empty'));
		}

		return $errors;
	}
}
