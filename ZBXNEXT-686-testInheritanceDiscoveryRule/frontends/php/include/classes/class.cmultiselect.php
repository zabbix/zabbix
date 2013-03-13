<?php
/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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
	 * @param string $options['name']
	 * @param string $options['objectName']
	 * @param array  $options['data']
	 * @param bool   $options['disabled']
	 * @param int    $options['width']
	 * @param int    $options['limit']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', zbx_formatDomId($options['name']));
		$this->addClass('multiselect');
		$this->addStyle('width: '.(isset($options['width']) ? $options['width'] : ZBX_TEXTAREA_STANDARD_WIDTH).'px;');

		// data
		$data = '[]';
		if (!empty($options['data'])) {
			$json = new CJSON();

			$data = $json->encode($options['data']);
		}

		// url
		$url = new Curl('jsrpc.php');
		$url->setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);
		$url->setArgument('method', 'multiselect.get');
		$url->setArgument('objectName', $options['objectName']);

		zbx_add_post_js('jQuery("#'.$this->getAttribute('id').'").multiSelect({
			url: "'.$url->getUrl().'",
			name: "'.$options['name'].'",
			data: '.$data.',
			limit: '.(isset($options['limit']) ? $options['limit'] : '20').',
			labels: {
				emptyResult: "'._('No matches found').'",
				moreMatchesFound: "'._('More matches found...').'"
			},
			disabled: '.($options['disabled'] ? 'true' : 'false').'
		});');
	}
}
