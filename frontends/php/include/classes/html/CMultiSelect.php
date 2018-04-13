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


class CMultiSelect extends CTag {

	/**
	 * Javascript event name that will be triggered after multiselect initialization
	 *
	 * @var string
	 */
	private $js_event_name = '';

	/**
	 * @param array $options['objectOptions']  an array of parameters to be added to the request URL
	 * @param bool  $options['add_post_js']
	 *
	 * @see jQuery.multiSelect()
	 */
	public function __construct(array $options = []) {
		parent::__construct('div', true);

		$this
			->addClass('multiselect')
			->setId(zbx_formatDomId($options['name']))
			->addItem((new CDiv())
				->setAttribute('aria-live', 'assertive')
				->setAttribute('aria-atomic', 'true')
			)
			->js_event_name = sprintf('multiselect_%s_init', $this->getId());

		// url
		$url = (new CUrl('jsrpc.php'))
			->setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON)
			->setArgument('method', 'multiselect.get')
			->setArgument('objectName', $options['objectName']);

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

		foreach (['ignored', 'defaultValue', 'disabled', 'selectedLimit', 'addNew', 'styles'] as $option) {
			if (array_key_exists($option, $options)) {
				$params[$option] = $options[$option];
			}
		}

		if (array_key_exists('popup', $options)) {
			if (array_key_exists('parameters', $options['popup'])) {
				$params['popup']['parameters'] = $options['popup']['parameters'];
			}
		}
		if (array_key_exists('callPostEvent', $options) && $options['callPostEvent']) {
			$params['postInitEvent'] = $this->getJsEventName();
		}

		$this->params = $params;

		if (!array_key_exists('add_post_js', $options) || $options['add_post_js']) {
			zbx_add_post_js($this->getPostJS());
		}
	}

	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');
		return $this;
	}

	/**
	 * Get js event name
	 *
	 * @return string
	 */
	public function getJsEventName() {
		return $this->js_event_name;
	}

	public function getPostJS() {
		return 'jQuery("#'.$this->getAttribute('id').'").multiSelect('.CJs::encodeJson($this->params).');';
	}
}
