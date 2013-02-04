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


class CTokenInput extends CTag {

	/**
	 * @param string $options['name']
	 * @param string $options['objectName']
	 * @param array  $options['data']
	 * @param bool   $options['disabled']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('input', 'no');
		$this->attr('type', 'text');
		$this->attr('name', $options['name']);
		$this->attr('id', zbx_formatDomId($options['name']));
		$this->attr('disabled', $options['disabled'] ? $options['disabled'] : null);

		// url
		$url = new CUrl('jsrpc.php');
		$url->setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);
		$url->setArgument('method', 'tokeninput.get');
		$url->setArgument('objectName', $options['objectName']);

		$data = '';
		if (!empty($options['data'])) {
			foreach ($options['data'] as $id => $name) {
				$data .= '{id: "'.$id.'", name: "'.$name['name'].'"},';
			}
			$data = substr($data, 0, strlen($data) - 1);
		}

		zbx_add_post_js('jQuery("#'.$this->getAttribute('id').'").tokenInput("'.$url->getUrl().'", {
				preventDuplicates: true,
				queryParam: "search",
				jsonContainer: "result",
				hintText: "I can has tv shows?",
				noResultsText: "'._('no results found').'",
				searchingText: "'._('Searching..').'",
				prePopulate: ['.$data.']
			})'
		);
	}
}
