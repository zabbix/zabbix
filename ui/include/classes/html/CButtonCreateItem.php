<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CButtonCreateItem extends CButton {

	/**
	 * Create CButtonCreateItem instance.
	 *
	 * @param string $caption  Label for the button
	 * @param array  $dropdown Items for dropdown
	 * @param array  $dropdown['hosts'] List of hosts
	 * @param string $dropdown['hosts'][]['hostid'] Host ID
	 * @param bool   $dropdown['hosts'][]['disabled'] True if item is inactive
	 * @param array  $dropdown['templates'] List of templates
	 * @param string $dropdown['templates'][]['templateid'] Template ID
	 * @param bool   $dropdown['templates'][]['disabled'] True if item is inactive
	 *
	 * @return CButtonCreateItem
	 */
	public function __construct(string $caption, array $dropdown) {
		$items = [];

		parent::__construct('create-item', $caption . '&#8203;');

		foreach($dropdown['hosts'] as $host) {
			$items[] = [
				'label' => $host['name'],
				'url' => 'javascript: item_create('.json_encode(['hostid' => $host['hostid']]).')',
				'disabled' => $host['disabled']
			];
		}

		foreach($dropdown['templates'] as $template) {
			$items[] = [
				'label' => $template['name'],
				'url' => 'javascript: item_create('.json_encode(['templateid' => $template['templateid']]).')',
				'disabled' => $template['disabled']
			];
		}

		$this
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass(ZBX_STYLE_BTN_TOGGLE_CHEVRON)
			->addClass(ZBX_STYLE_BTN_TOGGLE_CHEVRON_CAPTIONED)
			->setMenuPopup([
				'type' => 'dropdown',
				'data' => [
					'submit_form' => false,
					'items' => $items
				]
			])
		;

		$this->addClass(ZBX_STYLE_BTN_SPLIT);
	}
}
