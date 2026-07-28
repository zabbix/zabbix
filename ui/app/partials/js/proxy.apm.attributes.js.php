<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


/**
 * @var CPartial $this
 * @var array    $data
 */

$attribute = [
	'name' => '',
	'value' => '',
	'type' => PROXY_APM_ATTR_TYPE_TRACES
];

(new CTemplateTag('attr-row-tmpl', renderProxyApmAttrRow('#{rowNum}', $attribute, [
	'add_post_js' => false,
	'attribute_types' => $data['attribute_types'],
	'field_name' => $data['field_name'],
	'has_inline_validation' => $data['has_inline_validation'],
	'readonly' => $data['readonly']
])))->show();

?>

<script type="text/javascript">
	jQuery(function() {
		const tabsEventHandler = (event, ui) => {
			const $panel = event.type === 'tabscreate' ? ui.panel : ui.newPanel;

			if ($panel.is('#<?= $data['attr_tab_id'] ?>')) {
				$('#<?= $data['tabs_id'] ?>').off('tabscreate.tags-tab tabsactivate.tags-tab', tabsEventHandler);
				bindAttrTableEvents($panel);
			}
		};
		const bindAttrTableEvents = ($panel) => {
			const $table = $panel.find('.attributes-table');

			$table.dynamicRows({template: '#attr-row-tmpl', allow_empty: true});
		}
		const apm_tab = $('#<?= $data['attr_tab_id'] ?>[aria-hidden="false"]');

		if (apm_tab.length) {
			bindAttrTableEvents(apm_tab);
		}
		else {
			$('#<?= $data['tabs_id'] ?>').on('tabscreate.tags-tab tabsactivate.tags-tab', tabsEventHandler);
		}
	});
</script>
