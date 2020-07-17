<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * @var CView $this
 */
(new CScriptTemplate('filter-tag-row-tmpl'))
	->addItem(
		(new CRow([
			(new CTextBox('filter_tags[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CRadioButtonList('filter_tags[#{rowNum}][operator]', TAG_OPERATOR_LIKE))
				->addValue(_('Contains'), TAG_OPERATOR_LIKE)
				->addValue(_('Equals'), TAG_OPERATOR_EQUAL)
				->setModern(true),
			(new CTextBox('filter_tags[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton('filter_tags[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row'))
	->show();

?>
<script type="text/javascript">
	jQuery(function($) {
		function hostPage() {
			this.refresh_url = '<?= $data['refresh_url'] ?>';
			this.refresh_interval = <?= $data['refresh_interval'] ?>;
			this.running = false;
			this.timeout = null;

			this.filter = new CTabFilter($('#monitoringhostsfilter')[0], <?= json_encode($data['filter_options']) ?>);
			this.filter.afterTabContentRender = this.afterTabContentRender.bind(this);

			if (this.filter._active_item) {
				this.afterTabContentRender(this.filter._active_item);
			}
		}

		hostPage.prototype = {
			afterTabContentRender: function (item) {
				let data = item._data,
					fields = $.extend({}, data.default, data.fields);

				var content = item._content_container;
				console.log(`${data.uniqid} rendered`, data);

				// Host groups multiselect.
				$('#filter_groupids_' + data.uniqid, content).multiSelectHelper({
					id: 'filter_groupids_' + data.uniqid, content,
					object_name: 'hostGroup',
					name: 'filter_groupids[]',
					data: data.groups_multiselect||[],
					popup: {
						parameters: {
							multiselect: '1',
							noempty: '1',
							srctbl: 'host_groups',
							srcfld1: 'groupid',
							dstfrm: 'zbx_filter',
							dstfld1: 'filter_groupids_' + data.uniqid,
							real_hosts: 1,
							enrich_parent_groups: 1
						}
					}
				});

				// Tags table
				var tag_row = new Template($('#filter-tag-row-tmpl').html()),
					i = 0;

				fields.tags.forEach(tag => {
					var $row = $(tag_row.evaluate({rowNum: i++}));

					$row.find('[name$="[tag]"]').val(tag.tag);
					$row.find('[name$="[value]"]').val(tag.value);
					$row.find('[name$="[operator]"][value="'+tag.operator+'"]').attr('checked', 'checked');

					$('#filter_tags_' + data.uniqid, content).append($row);
				});
				$('#filter_tags_' + data.uniqid, content).dynamicRows({template: '#filter-tag-row-tmpl'});

				// Show hosts in maintenance events.
				$('[name="filter_maintenance_status"]', content).click(function () {
					$('[name="filter_show_suppressed"]', content).prop('disabled', !this.checked);
				});
			},
			getCurrentForm: function() {
				return $('form[name=host_view]');
			},
			addMessages: function(messages) {
				$('.wrapper main').before(messages);
			},
			removeMessages: function() {
				$('.wrapper .msg-bad').remove();
			},
			refresh: function() {
				this.setLoading();

				var deferred = $.getJSON(this.refresh_url);

				return this.bindDataEvents(deferred);
			},
			setLoading: function() {
				this.getCurrentForm().addClass('is-loading is-loading-fadein delayed-15s');
			},
			clearLoading: function() {
				this.getCurrentForm().removeClass('is-loading is-loading-fadein delayed-15s');
			},
			doRefresh: function(body) {
				this.getCurrentForm().replaceWith(body);
			},
			bindDataEvents: function(deferred) {
				var that = this;

				deferred
					.done(function(response) {
						that.onDataDone.call(that, response);
					})
					.fail(function(jqXHR) {
						that.onDataFail.call(that, jqXHR);
					})
					.always(this.onDataAlways.bind(this));

				return deferred;
			},
			onDataDone: function(response) {
				this.clearLoading();
				this.removeMessages();
				this.doRefresh(response.body);

				if ('messages' in response) {
					this.addMessages(response.messages);
				}
			},
			onDataFail: function(jqXHR) {
				// Ignore failures caused by page unload.
				if (jqXHR.status == 0) {
					return;
				}

				this.clearLoading();

				var messages = $(jqXHR.responseText).find('.msg-global');

				if (messages.length) {
					this.getCurrentForm().html(messages);
				}
				else {
					this.getCurrentForm().html(jqXHR.responseText);
				}
			},
			onDataAlways: function() {
				if (this.running) {
					this.scheduleRefresh();
				}
			},
			scheduleRefresh: function() {
				this.unscheduleRefresh();
				this.timeout = setTimeout((function() {
					this.timeout = null;
					this.refresh();
				}).bind(this), this.refresh_interval);
			},
			unscheduleRefresh: function() {
				if (this.timeout !== null) {
					clearTimeout(this.timeout);
					this.timeout = null;
				}
			},
			start: function() {
				if (this.refresh_interval != 0) {
					this.running = true;
					this.refresh();
				}
			},
			stop: function() {
				this.running = false;
				this.unscheduleRefresh();
			}
		};

		window.host_page = new hostPage();

		// TODO: create generic wrapper for 'pagination and sorting' via ajax.
		host_page.getCurrentForm().parent().on('click', '.list-table th a[href*="sort="],.paging-btn-container a', function(ev) {
			var location_url = new Curl(),
				action_url = new Curl($(ev.target).attr('href'));

			host_page.refresh_url = action_url.getUrl();
			action_url.setArgument('action', location_url.getArgument('action'));
			history.replaceState(history.state, '', action_url.getUrl());
			host_page.refresh();

			return cancelEvent(ev);
		});
	});
</script>
