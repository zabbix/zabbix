<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


$schema = DB::getSchema('config');
?>

<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery("#resetDefaults").click(function() {
			overlayDialogue({
				'title': <?= CJs::encodeJson(_('Reset confirmation')) ?>,
				'content': jQuery('<span>').text(<?= CJs::encodeJson(_('Reset all fields to default values?')) ?>),
				'buttons': [
					{
						'title': <?= CJs::encodeJson(_('Cancel')) ?>,
						'cancel': true,
						'class': '<?= ZBX_STYLE_BTN_ALT ?>',
						'action': function() {}
					},
					{
						'title': <?= CJs::encodeJson(_('Reset defaults')) ?>,
						'focused': true,
						'action': function() {
							jQuery('#severity_name_0').val("<?= $schema['fields']['severity_name_0']['default'] ?>");
							jQuery('#severity_name_1').val("<?= $schema['fields']['severity_name_1']['default'] ?>");
							jQuery('#severity_name_2').val("<?= $schema['fields']['severity_name_2']['default'] ?>");
							jQuery('#severity_name_3').val("<?= $schema['fields']['severity_name_3']['default'] ?>");
							jQuery('#severity_name_4').val("<?= $schema['fields']['severity_name_4']['default'] ?>");
							jQuery('#severity_name_5').val("<?= $schema['fields']['severity_name_5']['default'] ?>");
							jQuery('#severity_color_0')
								.val("<?= $schema['fields']['severity_color_0']['default'] ?>")
								.change();
							jQuery('#severity_color_1')
								.val("<?= $schema['fields']['severity_color_1']['default'] ?>")
								.change();
							jQuery('#severity_color_2')
								.val("<?= $schema['fields']['severity_color_2']['default'] ?>")
								.change();
							jQuery('#severity_color_3')
								.val("<?= $schema['fields']['severity_color_3']['default'] ?>")
								.change();
							jQuery('#severity_color_4')
								.val("<?= $schema['fields']['severity_color_4']['default'] ?>")
								.change();
							jQuery('#severity_color_5')
								.val("<?= $schema['fields']['severity_color_5']['default'] ?>")
								.change();
						}
					}
				]
			}, this);
		});

		var $form = jQuery('form');
		$form.on('submit', function() {
			$form.trimValues(['#severity_name_0', '#severity_name_1', '#severity_name_2', '#severity_name_3',
				'#severity_name_4', '#severity_name_5'
			]);
		});
	});
</script>
