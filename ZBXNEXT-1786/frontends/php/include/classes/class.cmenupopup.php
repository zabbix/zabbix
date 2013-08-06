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
	 * Menu width.
	 *
	 * @var int
	 */
	private $width = 150;

	/**
	 * Init menu popup data.
	 *
	 * @param bool   $options['isMap']
	 * @param string $options['id']
	 * @param int    $options['width']
	 * @param array  $options['scripts']
	 * @param array  $options['goto']
	 * @param array  $options['goto']['params']
	 * @param array  $options['goto']['items']
	 * @param array  $options['urls']
	 * @param array  $options['urls'][n]['name']
	 * @param array  $options['urls'][n]['url']
	 * @param array  $options['triggers']
	 * @param array  $options['triggers']['triggerid']
	 * @param array  $options['triggers']['events']
	 * @param array  $options['triggers']['acknow']
	 * @param array  $options['triggers']['configuration']
	 * @param array  $options['triggers']['url']
	 * @param array  $options['history']
	 * @param array  $options['history']['items']
	 * @param array  $options['history']['items']['name']
	 * @param array  $options['history']['items']['params']
	 * @param array  $options['history']['latest']
	 * @param array  $options['history']['latest']['itemid']
	 * @param array  $options['history']['latestValues']
	 * @param array  $options['history']['latestValues']['itemid']
	 */
	public function __construct(array $options = array()) {
		parent::__construct('div', 'yes');
		$this->attr('id', isset($options['id']) ? $options['id'] : null);
		$this->addClass('menuPopup');

		if (isset($options['width'])) {
			$this->addStyle('width: '.($options['width'] + 5).'px;');
			$this->width = $options['width'];
		}
		else {
			$this->addStyle('width: '.($this->width + 5).'px;');
		}

		// scripts
		if (!empty($options['scripts'])) {
			if (!is_array($options['scripts'])) {
				$hostId = $options['scripts'];

				$options['scripts'] = array();

				foreach (API::Script()->getScriptsByHosts($hostId) as $hostScripts) {
					$options['scripts'] = array_merge($options['scripts'], $hostScripts);
				}
			}

			if ($options['scripts']) {
				CArrayHelper::sort($options['scripts'], array('field' => 'name'));

				$menuItems = array();

				foreach ($options['scripts'] as $script) {
					if (preg_match_all('/(?<!\\\)\//', $script['name'], $matches, PREG_OFFSET_CAPTURE)) {
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
				$menu->addStyle('width: '.$this->width.'px;');

				$this->addMenu($menu, $menuItems['items']);

				if (!$menu->emptyList) {
					$this->addItem(new CDiv(_('Scripts'), 'title'));
					$this->addItem($menu);
				}
			}
		}

		// goto
		if (!empty($options['goto']['items'])) {
			$menu = new CList(null, 'menu');
			$menu->addStyle('width: '.$this->width.'px;');

			foreach ($options['goto']['items'] as $name => $args) {
				if ($args) {
					$params = self::getParams($args, isset($options['goto']['params']) ? $options['goto']['params'] : null);

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

		// triggers
		if (!empty($options['triggers'])) {
			$menu = new CList(null, 'menu');
			$menu->addStyle('width: '.$this->width.'px;');

			$params = isset($options['triggers']['triggerid'])
				? array('triggerid' => $options['triggers']['triggerid'])
				: null;

			// events
			if (!empty($options['triggers']['events'])) {
				$menu->addItem(new CLink(_('Events'),
					'events.php'.self::getParams($options['triggers']['events'], $params)));
			}

			// acknow
			if (!empty($options['triggers']['acknow'])) {
				$menu->addItem(new CLink(_('Acknowledge'),
					'acknow.php'.self::getParams($options['triggers']['acknow'])));
			}

			// configuration
			if (!empty($options['triggers']['configuration'])) {
				$params['form'] = 'update';
				$params['switch_node'] = isset($options['triggers']['triggerid'])
					? id2nodeid($options['triggers']['triggerid']) : null;

				$menu->addItem(new CLink(_('Configuration'),
					'triggers.php'.self::getParams($options['triggers']['configuration'], $params)));
			}

			// url
			if (!empty($options['triggers']['url'])) {
				$menu->addItem(new CLink(_('URL'), $options['triggers']['url']));
			}

			if (!$menu->emptyList) {
				$this->addItem(new CDiv(_('Trigger'), 'title'));
				$this->addItem($menu);
			}
		}

		// history
		if (!empty($options['history'])) {
			$menu = new CList(null, 'menu');
			$menu->addStyle('width: '.$this->width.'px;');

			// items
			if (!empty($options['history']['items'])) {
				foreach ($options['history']['items'] as $item) {
					$menu->addItem(new CLink($item['name'],
						'history.php'.self::getParams(isset($item['params']) ? $item['params'] : null)));
				}
			}

			// latest
			if (!empty($options['history']['latest']['itemid'])) {
				$menu->addItem(new CLink(_('Last hour graph'), 'history.php?'.
					'action=showgraph'.
					'&period=3600'.
					'&itemid='.$options['history']['latest']['itemid']
				));

				$menu->addItem(new CLink(_('Last week graph'), 'history.php?'.
					'action=showgraph'.
					'&period=604800&'.
					'itemid='.$options['history']['latest']['itemid']
				));

				$menu->addItem(new CLink(_('Last month graph'), 'history.php?'.
					'action=showgraph'.
					'&period=2678400'.
					'&itemid='.$options['history']['latest']['itemid']
				));
			}

			// latest values
			if (!empty($options['history']['latestValues'])) {
				$menu->addItem(new CLink(_('Latest values'), 'history.php?'.
					'action=showvalues'.
					'&period=3600'.
					'&itemid='.$options['history']['latestValues']['itemid']
				));
			}

			if (!$menu->emptyList) {
				$this->addItem(new CDiv(_('History'), 'title'));
				$this->addItem($menu);
			}
		}

		// urls
		if (!empty($options['urls'])) {
			$menu = new CList(null, 'menu');
			$menu->addStyle('width: '.$this->width.'px;');

			foreach ($options['urls'] as $url) {
				$menu->addItem(new CLink($url['name'], $url['url']));
			}

			if (!$menu->emptyList) {
				$this->addItem(new CDiv(_('URLs'), 'title'));
				$this->addItem($menu);
			}
		}

		// insert js
		if (empty($options['isMap'])) {
			if (!defined('IS_MENU_POPUP_COMMON_JS_INSERTED')) {
				define('IS_MENU_POPUP_COMMON_JS_INSERTED', true);

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

							setTimeout(function() {
								if (!obj.data("isActive")) {
									obj.fadeOut(50);
								}
							}, 2000);
						});
					});'
				);
			}
		}

		// map
		else {
			if (!defined('IS_MENU_POPUP_MAP_JS_INSERTED')) {
				define('IS_MENU_POPUP_MAP_JS_INSERTED', true);

				insert_js('
					jQuery(document).ready(function() {
						jQuery(".map-container [data-menupopup]").click(function(e) {
							var iframe = jQuery("#iframe"),
								mapContainer = jQuery(this).parent().parent(),
								container = jQuery(".menuPopupContainer", mapContainer),
								top = e.clientY + document.body.scrollTop,
								left = e.clientX;

							if (iframe.length > 0) {
								top -= iframe.position().top;
							}

							if (container.length > 0) {
								container.html(jQuery(this).data("menupopup"));
								container.css({
									top: top,
									left: left
								});
							}
							else {
								container = jQuery("<div>", {
									"class": "menuPopupContainer",
									html: jQuery(this).data("menupopup"),
									css: {
										position: "absolute",
										top: top,
										left: left
									}
								}),
								mapContainer.append(container);
							}

							obj = container.children();

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

							setTimeout(function() {
								if (!obj.data("isActive")) {
									obj.fadeOut(50);
								}
							}, 2000);

							return false;
						});
					});'
				);
			}
		}

		// insert script dialog
		if (!empty($options['scripts']) && !defined('IS_MENU_POPUP_SCRIPT_DIALOG_JS_INSERTED')) {
			define('IS_MENU_POPUP_SCRIPT_DIALOG_JS_INSERTED', true);

			insert_js('
				jQuery(document).ready(function() {
					jQuery("body").append(jQuery("<div>", {
						id: "scriptDialog",
						css: {
							display: "none",
							"white-space": "normal",
							"z-index": 1000
						}
					}));
				});

				function showScriptDialog(confirmation, buttons) {
					var obj = jQuery("#scriptDialog");

					obj.text(confirmation);

					var width = obj.outerWidth() + 20;

					obj.dialog({
						buttons: buttons,
						draggable: false,
						modal: true,
						width: (width > 600 ? 600 : "inherit"),
						resizable: false,
						minWidth: 200,
						minHeight: 100,
						title: '.CJs::encodeJson(_('Execution confirmation')).',
						close: function() {
							jQuery(this).dialog("destroy");
						}
					});

					return obj.dialog("widget");
				}

				function executeScript(hostId, scriptId, confirmation) {
					var execute = function() {
						openWinCentered("scripts_exec.php?execute=1&hostid=" + hostId + "&scriptid=" + scriptId, "Tools", 560, 470,
							"titlebar=no, resizable=yes, scrollbars=yes, dialog=no"
						);
					};

					if (confirmation == "") {
						execute();
					}
					else {
						var buttons = [
							{text: '.CJs::encodeJson(_('Execute')).', click: function() {
								jQuery(this).dialog("destroy");
								execute();
							}},
							{text: '.CJs::encodeJson(_('Cancel')).', click: function() {
								jQuery(this).dialog("destroy");
							}}
						];

						showScriptDialog(confirmation, buttons);

						jQuery(".ui-dialog-buttonset button:first").addClass("main");
					}
				}'
			);
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
				$subMenu->addStyle('width: '.$this->width.'px;');

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

	/**
	 * Recursive function to prepare menu tree items.
	 *
	 * @param object $menu
	 * @param array  $items
	 * @param string $name
	 * @param array  $params
	 */
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

	/**
	 * Get unique id.
	 *
	 * @return string
	 */
	public static function getId() {
		return uniqid();
	}

	/**
	 * Get url params.
	 *
	 * @param array $items
	 * @param array $globalItems
	 *
	 * @return string
	 */
	private static function getParams($items, $globalItems = array()) {
		$params = '';

		if ($items && is_array($items) && $globalItems && is_array($globalItems)) {
			$items = array_merge($items, $globalItems);
		}
		else {
			if (!is_array($items) && $globalItems) {
				$items = $globalItems;
			}
		}

		if (is_array($items)) {
			foreach ($items as $key => $value) {
				$params .= ($params ? '&' : '?').$key.'='.$value;
			}
		}

		return $params;
	}
}
