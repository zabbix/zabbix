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
	 * @param bool   $options['isMap']
	 * @param string $options['id']
	 * @param string $options['hostid']
	 * @param array  $options['scripts']
	 * @param array  $options['goto']
	 * @param array  $options['goto']['params']
	 * @param array  $options['goto']['items']
	 * @param array  $options['urls']
	 * @param array  $options['urls'][n]['name']
	 * @param array  $options['urls'][n]['url']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', $options['id']);
		$this->addClass('menuPopup');

		// scripts
		if (!empty($options['scripts'])) {
			if (is_bool($options['scripts']) && isset($options['hostid'])) {
				$options['scripts'] = array();

				foreach (API::Script()->getScriptsByHosts($options['hostid']) as $hostScripts) {
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

						$this->appendMenuItem($menuItems, $items, $name, array(
							'scriptid' => $script['scriptid'],
							'hostid' => $script['hostid'],
							'confirmation' => $script['confirmation']
						));
					}
					else {
						$this->appendMenuItem($menuItems, array(), $script['name'], array(
							'scriptid' => $script['scriptid'],
							'hostid' => $script['hostid'],
							'confirmation' => $script['confirmation']
						));
					}
				}

				$menu = new CList(null, 'menu');
				$this->addMenu($menu, $menuItems['items']);

				if (!$menu->emptyList) {
					$this->addItem(new CDiv(_('Scripts'), 'title'));
					$this->addItem($menu);
				}
			}
		}

		// goto
		if (!empty($options['goto']['items'])) {
			$paramsGlobal = '';

			if (!empty($options['goto']['params'])) {
				foreach ($options['goto']['params'] as $key => $value) {
					$paramsGlobal .= ($paramsGlobal ? '&' : '?').$key.'='.$value;
				}
			}

			$menu = new CList(null, 'menu');

			foreach ($options['goto']['items'] as $name => $args) {
				if ($args) {
					$params = $paramsGlobal;

					if (is_array($args)) {
						foreach ($args as $key => $value) {
							$params .= ($params ? '&' : '?').$key.'='.$value;
						}
					}

					switch ($name) {
						case 'latest':
							$menu->addItem(new CLink(_('Latest data'), 'latest.php'.$params));
							break;

						case 'screens':
							$menu->addItem(new CLink(_('Host screens'), 'host_screen.php'.$params));
							break;

						case 'inventories':
							$menu->addItem(new CLink(_('Host inventories'), 'hostinventories.php'.$params));
							break;

						case 'triggerStatus':
							$menu->addItem(new CLink(_('Status of triggers'), 'tr_status.php'.$params));
							break;

						case 'map':
							$menu->addItem(new CLink(_('Submap'), 'maps.php'.$params));
							break;

						case 'events':
							$menu->addItem(new CLink(_('Latest events'), 'events.php'.$params));
							break;
					}
				}
			}

			if (!$menu->emptyList) {
				$this->addItem(new CDiv(_('Go to'), 'title'));
				$this->addItem($menu);
			}
		}

		// urls
		if (!empty($options['urls'])) {
			$menu = new CList(null, 'menu');

			foreach ($options['urls'] as $url) {
				$menu->addItem(new CLink($url['name'], $url['url']));
			}

			if (!$menu->emptyList) {
				$this->addItem(new CDiv(_('URLs'), 'title'));
				$this->addItem($menu);
			}
		}

		// insert js
		if (!defined('IS_MENU_POPUP_JS_INSERTED')) {
			define('IS_MENU_POPUP_JS_INSERTED', true);

			if (empty($options['isMap'])) {
				insert_js('
					jQuery(document).ready(function() {
						jQuery("[data-menupopupid]").click(function() {
							var obj = jQuery("#" + jQuery(this).data("menupopupid"));

							if (empty(obj.data("isLoaded"))) {
								jQuery(".menuPopup").css("display", "none");

								obj.menuPopup();
							}
							else {
								if (obj.css("display") == "block") {
									jQuery(".menuPopup").css("display", "none");
								}
								else {
									jQuery(".menuPopup").css("display", "none");
									obj.fadeIn(50);
								}
							}

							obj.position({
								of: jQuery(this),
								my: "left top",
								at: "left bottom"
							});
						});
					});'
				);
			}

			// map
			else {
				insert_js('
					jQuery(document).ready(function() {
						jQuery(".map-container [data-menupopup]").click(function(e) {
							var container = jQuery("<div>", {
									html: jQuery(this).data("menupopup"),
									css: {
										position: "absolute",
										top: e.pageY,
										left: e.pageX
									}
								}),
								obj = container.children();

							jQuery(".map-container").append(container);

							if (empty(obj.data("isLoaded"))) {
								jQuery(".menuPopup").css("display", "none");

								obj.menuPopup();
							}
							else {
								if (obj.css("display") == "block") {
									jQuery(".menuPopup").css("display", "none");
								}
								else {
									jQuery(".menuPopup").css("display", "none");
									obj.fadeIn(50);
								}
							}

							obj.position({
								of: container,
								my: "left top",
								at: "left bottom"
							});

							return false;
						});
					});'
				);
			}
		}
	}

	/**
	 * Build menu using structure:
	 *
	 * array(
	 * 	'a' => array(
	 * 		'items' => array(
	 * 			'a' => array(
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

			$menuItem = new CListItem(array(new CLink($name), $subMenu));

			if (!empty($data['params'])) {
				foreach ($data['params'] as $key => $param) {
					$menuItem->attr('data-'.$key, $param);
				}
			}

			$menu->addItem($menuItem);
		}
	}

	private function appendMenuItem(&$menu, array $items, $name, array $params) {
		if ($items) {
			$item = current($items);

			array_shift($items);

			if (isset($menu['items'][$item])) {
				$this->appendMenuItem($menu['items'][$item], $items, $name, $params);
			}
			else {
				$menu['items'][$item] = array(
					'items' => array()
				);

				$this->appendMenuItem($menu['items'][$item], $items, $name, $params);
			}
		}
		else {
			$menu['items'][$name] = array(
				'params' => $params,
				'items' => array()
			);
		}
	}
}
