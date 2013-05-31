<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
	 * @see jQuery.multiSelect()
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', zbx_formatDomId($options['name']));
		$this->addClass('multiselect');

		// url
		$url = new Curl('jsrpc.php');
		$url->setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);
		$url->setArgument('method', 'multiselect.get');
		$url->setArgument('objectName', $options['objectName']);

		if (!empty($options['objectOptions'])) {
			foreach ($options['objectOptions'] as $optionName => $optionvalue) {
				$url->setArgument($optionName, $optionvalue);
			}
		}

		$params = array(
			'id' => $this->getAttribute('id'),
			'url' => $url->getUrl(),
			'name' => $options['name'],
			'labels' => array(
				'No matches found' => _('No matches found'),
				'More matches found...' => _('More matches found...'),
				'type here to search' => _('type here to search'),
				'new' => _('new')
			),
			'data' => empty($options['data']) ? array() : zbx_cleanHashes($options['data']),
			'ignored' => isset($options['ignored']) ? $options['ignored'] : null,
			'defaultValue' => isset($options['defaultValue']) ? $options['defaultValue'] : null,
			'disabled' => isset($options['disabled']) ? $options['disabled'] : false,
			'selectedLimit' => isset($options['selectedLimit']) ? $options['selectedLimit'] : null,
			'addNew' => isset($options['addNew']) ? $options['addNew'] : false
		);

		zbx_add_post_js('jQuery("#'.$this->getAttribute('id').'").multiSelect('.CJs::encodeJson($params).')');
	}
}
