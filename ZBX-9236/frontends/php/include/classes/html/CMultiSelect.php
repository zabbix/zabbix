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


class CMultiSelect extends CTag {

	/**
	 * @param array $options['objectOptions'] 	an array of parameters to be added to the request URL
	 *
	 * @see jQuery.multiSelect()
	 */
	public function __construct(array $options = []) {
		parent::__construct('div', 'yes');
		$this->addClass('multiselect');
		$this->setAttribute('id', zbx_formatDomId($options['name']));

		// url
		$url = new CUrl('jsrpc.php');
		$url->setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);
		$url->setArgument('method', 'multiselect.get');
		$url->setArgument('objectName', $options['objectName']);

		if (!empty($options['objectOptions'])) {
			foreach ($options['objectOptions'] as $optionName => $optionvalue) {
				$url->setArgument($optionName, $optionvalue);
			}
		}

		$params = [
			'url' => $url->getUrl(),
			'name' => $options['name'],
			'labels' => [
				'No matches found' => _('No matches found'),
				'More matches found...' => _('More matches found...'),
				'type here to search' => _('type here to search'),
				'new' => _('new'),
				'Select' => _('Select')
			]
		];

		if (array_key_exists('data', $options)) {
			$params['data'] = zbx_cleanHashes($options['data']);
		}

		foreach (['ignored', 'defaultValue', 'disabled', 'selectedLimit', 'addNew'] as $option) {
			if (array_key_exists($option, $options)) {
				$params[$option] = $options[$option];
			}
		}

		if (array_key_exists('popup', $options)) {
			foreach (['parameters', 'width', 'height'] as $option) {
				if (array_key_exists($option, $options['popup'])) {
					$params['popup'][$option] = $options['popup'][$option];
				}
			}
		}

		zbx_add_post_js('jQuery("#'.$this->getAttribute('id').'").multiSelect('.CJs::encodeJson($params).');');
	}
}
