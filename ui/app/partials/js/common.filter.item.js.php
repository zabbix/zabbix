<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


/**
 * @var CPartial $this
 */
?>

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate(); ?>
</script>

<script type="text/javascript">
	(function($) {
		$(function() {
			$('#filter-tags')
				.dynamicRows({ template: '#filter-tag-row-tmpl' })
				.on('afteradd.dynamicRows', function() {
					// Hide tag value field if operator is "Exists" or "Does not exist". Show tag value field otherwise.
					$(this)
						.find('z-select')
						.on('change', function() {
							let num = this.id.match(/filter_tags_(\d+)_operator/);
							if (num !== null) {
								let show = $(this).val() != <?= TAG_OPERATOR_EXISTS ?>
										&& $(this).val() != <?= TAG_OPERATOR_NOT_EXISTS ?>;
								$('#filter_tags_' + num[1] + '_value').toggle(show);
							}
						});
				});

			// Hide tag value field if operator is "Exists" or "Does not exist". Show tag value field otherwise.
			$('#filter-tags z-select')
				.on('change', function() {
					let num = this.id.match(/filter_tags_(\d+)_operator/);
					if (num !== null) {
						let show = $(this).val() != <?= TAG_OPERATOR_EXISTS ?>
								&& $(this).val() != <?= TAG_OPERATOR_NOT_EXISTS ?>;
						$('#filter_tags_' + num[1] + '_value').toggle(show);
					}
				})
				.trigger('change');
		});
	})(jQuery);
</script>
