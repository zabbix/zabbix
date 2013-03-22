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
	 * @param array  $options['objectOptions']
	 * @param array  $options['data']
	 * @param bool   $options['disabled']
	 * @param bool   $options['displaySingle']
	 * @param bool   $options['single']
	 * @param int    $options['width']
	 * @param int    $options['limit']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', zbx_formatDomId($options['name']));
		$this->addStyle('width: '.(isset($options['width']) ? $options['width'] : ZBX_MULTISELECT_STANDARD_WIDTH).'px;');
		$this->addClass('multiselect');

		if (!empty($options['displaySingle'])) {
			$this->addClass('multiselect_single');
		}

		// data
		$data = '[]';
		if (!empty($options['data'])) {
			$options['data'] = zbx_toArray($options['data']);

			$json = new CJSON();

			$data = $json->encode($options['data']);
		}

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

		zbx_add_post_js('jQuery("#'.$this->getAttribute('id').'").multiSelect({
			url: "'.$url->getUrl().'",
			name: "'.$options['name'].'",
			labels: {
				emptyResult: "'._('No matches found').'",
				moreMatchesFound: "'._('More matches found...').'"
			},
			data: '.$data.',
			disabled: '.(!empty($options['disabled']) ? 'true' : 'false').',
			displaySingle: '.(!empty($options['displaySingle']) ? 'true' : 'false').',
			single: '.(!empty($options['single']) ? 'true' : 'false').',
			limit: '.(isset($options['limit']) ? $options['limit'] : '20').'
		});');
	}
}
