<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/maps.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of network maps');
$page['file'] = 'sysmap.php';
$page['scripts'] = ['class.svg.canvas.js', 'class.svg.map.js', 'class.cmap.js', 'colorpicker.js'];
$page['type'] = detect_page_type();

if (!CWebUser::checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS)) {
	access_deny(ACCESS_DENY_PAGE);
}

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'sysmapid' =>	[T_ZBX_INT, O_MAND, P_SYS,	DB_ID,		null],
	'selementid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null],
	'sysmap' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({action}) && {action} == "update"'],
	// actions
	'action' =>		[T_ZBX_STR, O_OPT, P_ACT,	IN('"update","expand"'),	null],
	'delete' =>		[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'cancel' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'form' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	// ajax
	'favobj' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'favid' =>		[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'name' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({action}) && {action} == "expand"'],
	'source' =>		[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({action}) && {action} == "expand"']
];
check_fields($fields);

/*
 * Ajax
 */
if (isset($_REQUEST['favobj'])) {
	if (getRequest('favobj') === 'sysmap' && hasRequest('action')) {
		if (getRequest('action') === 'update') {
			$sysmapid = getRequest('sysmapid', 0);

			@ob_start();

			try {
				DBstart();

				$sysmap = API::Map()->get([
					'sysmapids' => $sysmapid,
					'editable' => true,
					'output' => ['sysmapid']
				]);
				$sysmap = reset($sysmap);

				if ($sysmap === false) {
					throw new Exception(_('Access denied!'));
				}

				$sysmapUpdate = json_decode($_REQUEST['sysmap'], true);
				$sysmapUpdate['sysmapid'] = $sysmapid;
				$sysmapUpdate['lines'] = [];

				if (array_key_exists('selements', $sysmapUpdate)) {
					foreach ($sysmapUpdate['selements'] as $element) {
						if (!array_key_exists('tags', $element)) {
							continue;
						}

						if (array_key_exists('elementtype', $element)
								&& ($element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST
									|| $element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP)) {
							foreach ($element['tags'] as $key => $tag) {
								if ($tag['tag'] === '' && $tag['value'] === '') {
									unset($element['tags'][$key]);
								}
							}
						}
						else {
							unset($element['tags']);
						}
					}
				}

				if (array_key_exists('shapes', $sysmapUpdate)) {
					foreach ($sysmapUpdate['shapes'] as $key => &$shape) {
						if (array_key_exists('sysmap_shapeid', $shape) && !is_numeric($shape['sysmap_shapeid'])) {
							unset($shape['sysmap_shapeid']);
						}

						if ($shape['type'] == SYSMAP_SHAPE_TYPE_LINE) {
							$sysmapUpdate['lines'][$key] = CMapHelper::convertShapeToLine($shape);
							unset($sysmapUpdate['shapes'][$key]);
						}
					}
					unset($shape);
				}

				$result = API::Map()->update($sysmapUpdate);

				if ($result !== false) {
					$url = (new CUrl('sysmaps.php'))
						->setArgument('page', CPagerHelper::loadPage('sysmaps.php', null))
						->getUrl();

					echo
						'if (confirm('.json_encode(_('Map is updated! Return to map list?')).')) {'.
							'location.href = "'.$url.'";'.
						'}';
				}
				else {
					throw new Exception(_('Map update failed.'));
				}

				DBend(true);
			}
			catch (Exception $e) {
				DBend(false);
				$msg = [$e->getMessage()];

				foreach (get_and_clear_messages() as $errMsg) {
					$msg[] = $errMsg['type'].': '.$errMsg['message'];
				}

				ob_clean();

				echo 'alert('.zbx_jsvalue(implode("\r\n", $msg)).');';
			}

			@ob_flush();
			session_write_close();
			exit();
		}
		elseif (getRequest('action') === 'expand') {
			$values = ['selements' => [], 'links' => [], 'shapes' => []];
			$return = [];
			$name = getRequest('name');
			$sources = json_decode(getRequest('source'), true);

			foreach ($sources as $num => $source) {
				if (is_array($source) && (array_key_exists('label', $source) || array_key_exists('text', $source))) {
					if (array_key_exists('inherited_label', $source) && $source['inherited_label'] !== null) {
						$source['label'] = $source['inherited_label'];
					}

					if (array_key_exists('elementtype', $source) && array_key_exists('elements', $source)
							&& is_array($source['elements']) && CMapHelper::checkSelementPermissions([$source])) {
						$element_type = 'selements';
					}
					else {
						$element_type = array_key_exists('label', $source) ? 'links' : 'shapes';
					}

					$values[$element_type][$num] = $source;
				}
				else {
					$return[$num] = null;
				}
			}

			$values['links'] = CMacrosResolverHelper::resolveMapLinkLabelMacros($values['links'], ['label' => 'label']);
			$values['shapes'] = CMacrosResolverHelper::resolveMapShapeLabelMacros($name, $values['shapes'],
				['text' => 'label']
			);

			if ($values['selements']) {
				// Resolve macros in map element labels.
				$values['selements'] = CMacrosResolverHelper::resolveMacrosInMapElements($values['selements'],
					['resolve_element_label' => true]
				);
			}

			foreach ($values['selements'] + $values['links'] + $values['shapes'] as $num => $value) {
				$return[$num] = $value['label'];
			}

			ksort($return);

			echo json_encode($return);
			session_write_close();
			exit();
		}
	}
}

if ($page['type'] != PAGE_TYPE_HTML) {
	require_once dirname(__FILE__).'/include/page_footer.php';
}

/*
 * Permissions
 */
if (isset($_REQUEST['sysmapid'])) {
	$sysmap = API::Map()->get([
		'output' => ['sysmapid', 'name', 'expand_macros', 'grid_show', 'grid_align', 'grid_size', 'width', 'height',
			'iconmapid', 'backgroundid', 'label_location', 'label_type', 'label_format', 'label_type_host',
			'label_type_hostgroup', 'label_type_trigger', 'label_type_map', 'label_type_image', 'label_string_host',
			'label_string_hostgroup', 'label_string_trigger', 'label_string_map', 'label_string_image'
		],
		'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size',
			'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color',
			'background_color', 'zindex'
		],
		'selectLines' => ['sysmap_shapeid', 'x1', 'y1', 'x2', 'y2', 'line_type', 'line_width', 'line_color', 'zindex'],
		'selectSelements' => API_OUTPUT_EXTEND,
		'selectLinks' => API_OUTPUT_EXTEND,
		'sysmapids' => getRequest('sysmapid'),
		'editable' => true,
		'preservekeys' => true
	]);
	if (!$sysmap) {
		access_deny();
	}
	else {
		$sysmap = reset($sysmap);
	}
}

/*
 * Display
 */
$sysmap['links'] = CMacrosResolverHelper::resolveMapLinkLabelMacros($sysmap['links'], ['label' => 'expanded']);
$sysmap['shapes'] = CMacrosResolverHelper::resolveMapShapeLabelMacros($sysmap['name'], $sysmap['shapes'],
	['text' => 'expanded']
);

$data = [
	'sysmap' => $sysmap,
	'iconList' => [],
	'defaultAutoIconId' => null,
	'defaultIconId' => null,
	'defaultIconName' => null
];

// Apply inherited element label properties.
$data['sysmap'] = CMapHelper::applyMapElementLabelProperties($data['sysmap']);

// get selements
addElementNames($data['sysmap']['selements']);

foreach ($data['sysmap']['lines'] as $line) {
	$data['sysmap']['shapes'][] = CMapHelper::convertLineToShape($line);
}
unset($data['sysmap']['lines']);

$data['sysmap']['selements'] = zbx_toHash($data['sysmap']['selements'], 'selementid');
$data['sysmap']['shapes'] = zbx_toHash($data['sysmap']['shapes'], 'sysmap_shapeid');
$data['sysmap']['links'] = zbx_toHash($data['sysmap']['links'], 'linkid');

// Extend $selement adding resolved label as property named 'expanded'.
$resolve_opt = ['resolve_element_label' => true];
$selements_resolved = CMacrosResolverHelper::resolveMacrosInMapElements($data['sysmap']['selements'], $resolve_opt);

// Set extended and restore original labels.
foreach ($data['sysmap']['selements'] as $selementid => &$selement) {
	$selement['expanded'] = $selements_resolved[$selementid]['label'];
}
unset($selement);

// get links
foreach ($data['sysmap']['links'] as &$link) {
	foreach ($link['linktriggers'] as $lnum => $linkTrigger) {
		$dbTrigger = API::Trigger()->get([
			'triggerids' => $linkTrigger['triggerid'],
			'output' => ['description', 'expression'],
			'selectHosts' => API_OUTPUT_EXTEND,
			'preservekeys' => true,
			'expandDescription' => true
		]);
		$dbTrigger = reset($dbTrigger);
		$host = reset($dbTrigger['hosts']);

		$link['linktriggers'][$lnum]['desc_exp'] = $host['name'].NAME_DELIMITER.$dbTrigger['description'];
	}

	order_result($link['linktriggers'], 'desc_exp');
}
unset($link);

// get iconmapping
if ($data['sysmap']['iconmapid']) {
	$iconMap = API::IconMap()->get([
		'iconmapids' => $data['sysmap']['iconmapid'],
		'output' => ['default_iconid'],
		'preservekeys' => true
	]);
	$iconMap = reset($iconMap);
	$data['defaultAutoIconId'] = $iconMap['default_iconid'];
}

$images = API::Image()->get([
	'output' => ['imageid', 'name'],
	'filter' => ['imagetype' => IMAGE_TYPE_ICON],
	'select_image' => true
]);

foreach ($images as $image) {
	$image['image'] = base64_decode($image['image']);
	$ico = imagecreatefromstring($image['image']);

	$data['iconList'][] = [
		'imageid' => $image['imageid'],
		'name' => $image['name'],
		'width' => imagesx($ico),
		'height' => imagesy($ico)
	];

	if ($image['name'] == MAP_DEFAULT_ICON || !isset($data['defaultIconId'])) {
		$data['defaultIconId'] = $image['imageid'];
		$data['defaultIconName'] = $image['name'];
	}
}

if ($data['iconList']) {
	CArrayHelper::sort($data['iconList'], ['name']);
	$data['iconList'] = array_values($data['iconList']);
}

$data['theme'] = getUserGraphTheme();

// render view
echo (new CView('monitoring.sysmap.constructor', $data))->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';
