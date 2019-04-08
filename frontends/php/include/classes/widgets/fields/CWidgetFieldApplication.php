<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


/**
 * Class to build UI control to select application in widget configuration window.
 */
class CWidgetFieldApplication extends CWidgetField {

	private $filter_parameters = [
		'srctbl' => 'applications',
		'srcfld1' => 'name',
		'with_applications' => '1',
		'real_hosts' => '1'
	];

	/**
	 * Create widget field for Application selection.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->filter_parameters['dstfld1'] = $name;
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setDefault('');
	}

	/**
	 * Returns parameters specified for popup opened when user clicks on Select button.
	 *
	 * @return array
	 */
	public function getFilterParameters() {
		return $this->filter_parameters;
	}
}
