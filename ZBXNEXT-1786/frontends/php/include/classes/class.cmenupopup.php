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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


class CMenuPopup extends CTag {

	/**
	 * @param string $options['id']
	 * @param array  $options['hostids']
	 * @param bool   $options['scripts']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', isset($options['id']) ? zbx_formatDomId($options['id']) : uniqid());
		$this->addClass('menuPopup');

		$menu = new CList(null, 'menu');

		// scripts
		if (!empty($options['scripts'])) {
			if (!is_array($options['scripts'] && isset($options['hostids']))) {
				$options['scripts'] = array();

				foreach (API::Script()->getScriptsByHosts($options['hostids']) as $hostScripts) {
					$options['scripts'] = array_merge($options['scripts'], $hostScripts);
				}
			}

			if ($options['scripts']) {
				CArrayHelper::sort($options['scripts'], array('field' => 'name'));

				$menuItems = array();

				foreach ($options['scripts'] as $script) {
					if (preg_match_all('/\//', $script['name'], $matches, PREG_OFFSET_CAPTURE)) {
						$matches = reset($matches);

						for ($i = 0, $size = count($matches); $i <= $size; $i++) {
							if ($i == 0) {
								$items = array(removeBackslash(substr($script['name'], 0, $matches[$i][1])));
							}
							elseif ($i == $size) {
								$name = removeBackslash(substr($script['name'], $matches[$i - 1][1] + 1));
							}
							else {
								$prev = $matches[$i - 1][1] + 1;
								$len = $matches[$i][1] - $prev;

								$items[] = removeBackslash(substr($script['name'], $prev, $len));
							}
						}

						$this->appendMenuItem($menuItems, $items, array(
							'name' => $name,
							'scriptid' => $script['scriptid'],
							'confirmation' => $script['confirmation']
						));
					}
					else {
						$this->appendMenuItem($menuItems, array(), array(
							'name' => $script['name'],
							'scriptid' => $script['scriptid'],
							'confirmation' => $script['confirmation']
						));
					}
				}

				$this->addMenu($menu, $menuItems['items']);
			}
		}

		$this->addItem($menu);

		zbx_add_post_js('jQuery("#'.$this->getAttribute('id').' .menu").menu();');
	}

	/**
	 * Build menu using structure:
	 *
	 * array(
	 * 	'a' => array(
	 * 		'params' => array(),
	 * 		'items' => array(
	 * 			'a' => array(
	 * 				'params' => array(),
	 * 				'items' => array(
	 * 					'a' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					),
	 * 					'b' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					)
	 * 				)
	 * 			),
	 * 			'b' => array(
	 * 				'params' => array(),
	 * 				'items' => array(
	 * 					'a' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					),
	 * 					'b' => array(
	 * 						'params' => array(),
	 * 						'items' => array()
	 * 					)
	 * 				)
	 * 			)
	 * 		),
	 * 	'b' => array(
	 * 		'params' => array(),
	 * 		'items' => array()
	 * 	)
	 * )
	 *
	 * @param object $menu
	 * @param array  $items
	 */
	private function addMenu(&$menu, &$items) {
		foreach ($items as $name => $data) {
			$subMenu = null;

			if ($data['items']) {
				$subMenu = new CList();

				$this->addMenu($subMenu, $data['items']);
			}

			$menu->addItem(array(
				new CLink($name, 'asdfasdf'),
				$subMenu
			));
		}
	}

	private function appendMenuItem(&$menu, array $items, array $params) {
		if ($items) {
			$item = current($items);

			array_shift($items);

			if (isset($menu['items'][$item])) {
				$this->appendMenuItem($menu['items'][$item], $items, $params);
			}
			else {
				$menu['items'][$item] = array(
					'params' => $params,
					'items' => array()
				);

				$this->appendMenuItem($menu['items'][$item], $items, $params);
			}
		}
		else {
			$menu['items'][$params['name']] = array(
				'params' => $params,
				'items' => array()
			);
		}
	}
}
