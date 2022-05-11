<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup token
 *
 * @dataSource ScheduledReports
 *
 * @onBefore prepareTokenData
 */
class testSID extends CWebTest {

	/**
	 * Token ID used for update.
	 *
	 * @var string
	 */
	protected static $token_id;

	public function prepareTokenData() {
		$response = CDataHelper::call('token.create', [
			'name' => 'api_update',
			'userid' => '1'
		]);
		$this->assertArrayHasKey('tokenids', $response);
		self::$token_id = $response['tokenids'][0];
	}

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getLinksData() {
		return [
			// Icon mapping delete.
			[['link' => 'zabbix.php?action=iconmap.delete&iconmapid=101']],

			// Icon mapping update.
			[['link' => 'zabbix.php?action=iconmap.update&form_refresh=1&form=1&iconmapid='.
					'101&iconmap%5Bname%5D=Icon+mapping+name+change&iconmap%5Bmappings%5D%5B0%5D%5Binventory_link'.
					'%5D=4&iconmap%5Bmappings%5D%5B0%5D%5Bexpression%5D=%281%21%40%23%24%25%5E-%3D2*%29&iconmap'.
					'%5Bmappings%5D%5B0%5D%5Biconid%5D=5&iconmap%5Bdefault_iconid%5D=15&update=Update']],

			// Icon mapping creation.
			[['link' => 'zabbix.php?action=iconmap.create&form_refresh=1&form=1&iconmap%5Bname%5D=ccccc&iconmap%5B'.
					'mappings%5D%5Bnew0%5D%5Binventory_link%5D=1&iconmap%5Bmappings%5D%5Bnew0%5D%5Bexpression%5D='.
					'ccccc&iconmap%5Bmappings%5D%5Bnew0%5D%5Biconid%5D=2&iconmap%5Bdefault_iconid%5D=2&add=Add']],

			// Image icon delete.
			[['link' => 'zabbix.php?action=image.delete&imageid=1&imagetype=1']],

			// Image icon update.
			[['link' => 'zabbix.php?action=image.update&form_refresh=1&imagetype=1&imageid=1&name=new_name2&update=Update']],

			// Module scan.
			[['link' => 'zabbix.php?form_refresh=1&action=module.scan&form=Scan+directory']],

			// Module enable.
			[['link' => 'zabbix.php?action=module.enable&moduleids[]=1']],

			// Module disable.
			[['link' => 'zabbix.php?action=module.disable&moduleids[]=1']],

			// Module update.
			[['link' => 'zabbix.php?action=module.update&moduleids%5B%5D=1&form_refresh=1&status=1']],

			// Regular expressions creation.
			[['link' => 'zabbix.php?action=regex.create&form_refresh=1&name=cccc&expressions%5B0%5D%5Bexpression_type%5D=0&'.
					'expressions%5B0%5D%5Bexpression%5D=ccccc&expressions%5B0%5D%5Bexp_delimiter%5D=%2C&test_string=&add=Add']],

			// Regular expressions delete.
			[['link' => 'zabbix.php?form_refresh=1&regexids%5B20%5D=20&regexids%5B31%5D=31&action=regex.delete']],

			// Regular expressions update.
			[['link' => 'zabbix.php?action=regex.update&regexid=32&form_refresh=1&name=ssss&expressions%5B0%5D%5B'.
					'expression_type%5D=0&expressions%5B0%5D%5Bexpression%5D=ssssssss&expressions%5B0%5D%5Bexp_delimiter'.
					'%5D=%2C&expressions%5B0%5D%5Bexpressionid%5D=32&test_string=&update=Update']],

			// Regular expressions test.
			[['link' => 'zabbix.php?ajaxdata%5BtestString%5D=&ajaxdata%5Bexpressions%5D%5B0%5D%5Bexpression%5D=2&'.
					'ajaxdata%5Bexpressions%5D%5B0%5D%5Bexpression_type%5D=0&ajaxdata%5Bexpressions%5D%5B0%5D%5B'.
					'exp_delimiter%5D=%2C&ajaxdata%5Bexpressions%5D%5B0%5D%5Bcase_sensitive%5D=0&action=regex.test']],

			// Timeselector update.
			[['link' => 'zabbix.php?action=timeselector.update&type=11&method=rangechange']],

			// Monitoring hosts, tab filter clicking.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.hosts.selected']],

			// Monitoring hosts, tab filter collapse.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_int=0&idx=web.monitoring.hosts.expanded']],

			// Monitoring hosts, tab filter expand.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.hosts.expanded']],

			// Monitoring hosts, tab filter order.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_str=0%2C2%2C1&idx=web.monitoring.hosts.taborder']],

			// Monitoring hosts, tab filter update.
			[['link' => 'zabbix.php?action=popup.tabfilter.update&idx=web.monitoring.hosts&idx2=1&create=0&'.
					'support_custom_time=0&filter_name=Untitled']],

			// Monitoring hosts, tab filter delete.
			[['link' => 'zabbix.php?action=popup.tabfilter.delete&idx=web.monitoring.hosts&idx2=1']],

			// Monitoring problems, tab filter clicking.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.problem.selected']],

			// Monitoring problems, tab filter collapse.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_int=0&idx=web.monitoring.problem.expanded']],

			// Monitoring problems, tab filter expand.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_int=1&idx=web.monitoring.problem.expanded']],

			// Monitoring problems, tab filter order.
			[['link' => 'zabbix.php?action=tabfilter.profile.update&value_str=0%2C2%2C1%2C3&idx=web.monitoring.problem.taborder']],

			// Monitoring problems, tab filter update.
			[['link' => 'zabbix.php?action=popup.tabfilter.update&idx=web.monitoring.problem&idx2=1&create=0&'.
					'support_custom_time=1&filter_name=Untitled_2']],

			// Monitoring problems, tab filter delete.
			[['link' => 'zabbix.php?action=popup.tabfilter.delete&idx=web.monitoring.problem&idx2=1']],

			// Host mass update.
			[['link' => 'zabbix.php?form_refresh=1&action=popup.massupdate.host&ids%5B0%5D=50011&ids%5B1%5D=50012&'.
					'tls_accept=0&update=1&location_url=hosts.php&visible%5Bstatus%5D=1&status=1']],

			// Item mass update.
			[['link' => 'zabbix.php?form_refresh=1&ids%5B0%5D=99086&ids%5B1%5D=99091&action=popup.massupdate.item&'.
					'prototype=0&update=1&location_url=items.php%3Fcontext%3Dhost&context=host&'.
					'visible%5Bstatus%5D=1&status=1']],

			// Template mass update.
			[['link' => 'zabbix.php?form_refresh=1&action=popup.massupdate.template&update=1&ids%5B0%5D=10076&'.
					'ids%5B1%5D=10207&location_url=templates.php&visible%5Bdescription%5D=1&description=%2C']],

			// Trigger mass update.
			[['link' => 'zabbix.php?form_refresh=1&action=popup.massupdate.trigger&ids%5B0%5D=100034&'.
					'ids%5B1%5D=100036&update=1&location_url=triggers.php%3Fcontext%3Dhost&context=host&'.
					'visible%5Bmanual_close%5D=1&manual_close=1']],

			// Dashboard properties update.
			[['link' => 'zabbix.php?action=dashboard.update&dashboardid=143&userid=1&name=sssdfsfsdfNew+dashboardss']],

			// Dashboard share update.
			[['link' => 'zabbix.php?action=dashboard.share.update&form_refresh=1&dashboardid=143&users%5Bempty_user'.
					'%5D=1&userGroups%5Bempty_group%5D=1&private=0']],

			// Dashboard delete.
			[['link' => 'zabbix.php?action=dashboard.delete&dashboardids[]=142']],

			// Dashboard update.
			[['link' => 'zabbix.php?action=dashboard.update&dashboardid=142&userid=1&name=1111&widgets%5B0%5D%5B'.
					'pos%5D%5Bwidth%5D=12&widgets%5B0%5D%5Bpos%5D%5Bheight%5D=5&widgets%5B0%5D%5Bpos%5D%5Bx%5D=0&'.
					'widgets%5B0%5D%5Bpos%5D%5By%5D=0&widgets%5B0%5D%5Btype%5D=actionlog&widgets%5B0%5D%5Bname%5D=&'.
					'widgets%5B0%5D%5Bview_mode%5D=0&widgets%5B0%5D%5Bfields%5D=%7B%22rf_rate%22%3A%22-1%22%2C%22'.
					'sort_triggers%22%3A%224%22%2C%22show_lines%22%3A%2225%22%7D']],

			// Dashboard widget configure.
			[['link' => 'zabbix.php?action=dashboard.widget.configure&type=actionlog&view_mode=0&fields=%7B%22rf_rate'.
					'%22%3A%22-1%22%2C%22sort_triggers%22%3A%224%22%2C%22show_lines%22%3A%2225%22%7D']],

			// Dashboard widget refresh rate.
			[['link' => 'zabbix.php?action=dashboard.widget.rfrate&widgetid=2002&rf_rate=120']],

			// Dashboard widget sanitize.
			[['link' => 'zabbix.php?action=dashboard.widgets.sanitize&fields=%7B%22reference%22%3A%22IACGE%22%7D&type=navtree']],

			// Template dashboard update/create.
			[['link' => 'zabbix.php?action=template.dashboard.update&templateid=10076&name=New+dashboard']],

			// Template dashboard delete.
			[['link' => 'zabbix.php?form_refresh=1&templateid=10076&dashboardids%5B146%5D=146&action=template.dashboard.delete']],

			// Template dashboard widget edit.
			[['link' => 'zabbix.php?action=dashboard.widget.edit&templateid=10076']],

			// User token delete.
			[['link' => 'zabbix.php?action=token.delete&action_src=user.token.list&tokenids%5B0%5D=1']],

			// User token disable.
			[['link' => 'zabbix.php?action_src=user.token.list&action=token.disable&tokenids[0]=2']],

			// User token enable.
			[['link' => 'zabbix.php?action_src=user.token.list&action=token.enable&tokenids[0]=2']],

			// User token creation.
			[['link' => 'zabbix.php?form_refresh=1&userid=1&action_src=user.token.edit&action_dst=user.token.view&'.
					'action=token.create&tokenid=0&name=adad&description=&expires_state=1&'.
					'expires_at=2021-04-20+00%3A00%3A00&status=0']],

			// User token update.
			[['link' => 'zabbix.php?form_refresh=1&userid=1&action_src=user.token.edit&action_dst=user.token.list&'.
					'action=token.update&tokenid=3&name=aaaa&description=sssss&expires_state=1&'.
					'expires_at=2021-04-21+00%3A00%3A00&status=0']],

			// Macros update.
			[['link' => 'zabbix.php?action=macros.update&form_refresh=1&macros%5B16%5D%5Bmacro%5D=%7B%24FGDFGDF%7D&'.
					'macros%5B16%5D%5Bvalue%5D=dfsdfsfs&macros%5B16%5D%5Btype%5D=0&macros%5B16%5D%5Bdescription%5D=&'.
					'update=Update']],

			// Autoregistration update.
			[['link' => 'zabbix.php?action=autoreg.edit&form_refresh=1&tls_accept=2&tls_in_psk=1&tls_psk_identity=sss&'.
					'tls_psk=88888888888888888888888888888888&action=autoreg.update']],

			// Token delete.
			[['link' => 'zabbix.php?form_refresh=1&action_src=token.list&tokenids%5B2%5D=2&action=token.delete']],

			// Token disable.
			[['link' => 'zabbix.php?action_src=token.list&action=token.disable&tokenids[0]=2']],

			// Token enable.
			[['link' => 'zabbix.php?action_src=token.list&action=token.enable&tokenids[0]=2']],

			// Token creation.
			[['link' => 'zabbix.php?form_refresh=1&action_src=token.edit&action_dst=token.view&action=token.create&'.
					'tokenid=0&name=ghfhf&userid=7&description=&expires_state=1&expires_at=2021-04-08+00%3A00%3A00&'.
					'status=0']],

			// Token update.
			[['link' => 'zabbix.php?form_refresh=1&action_src=token.edit&action_dst=token.list&action=token.update&'.
					'tokenid=3&name=aaaa&userid=1&description=ssssssss&expires_state=1&'.
					'expires_at=2021-04-21+00%3A00%3A00&status=0']],

			// Correlation condition creation.
			[['link' => 'zabbix.php?action=correlation.condition.add&form_refresh=3&name=dddd&evaltype=0&formula=&'.
					'conditions%5B0%5D%5Bformulaid%5D=A&conditions%5B0%5D%5Btype%5D=0&conditions%5B0%5D%5Boperator%5D'.
					'=0&conditions%5B0%5D%5Btag%5D=ddd&description=&op_close_old=1&status=0&action=correlation.create']],

			// Correlation condition disable.
			[['link' => 'zabbix.php?correlationids[0]=99004&action=correlation.disable']],

			// Correlation condition enable.
			[['link' => 'zabbix.php?correlationids[0]=99004&action=correlation.enable']],

			// Correlation condition update.
			[['link' => 'zabbix.php?action=correlation.condition.add?form_refresh=1&correlationid=99005&name='.
					'%D1%81%D0%BC%D1%87%D1%81%D0%BC%D1%87&evaltype=0&formula=&conditions%5B0%5D%5Btype%5D=0&'.
					'conditions%5B0%5D%5Btag%5D=%D0%BC%D1%81%D0%BC&conditions%5B0%5D%5Bformulaid%5D=A&'.
					'conditions%5B0%5D%5Boperator%5D=0&description=%D1%81%D1%87%D1%81%D0%BC%D1%81%D1%81%D1%81%'.
					'D1%81%D1%81%D1%81%D1%81&op_close_old=1&status=0&action=correlation.update']],

			// Correlation condition delete.
			[['link' => 'zabbix.php?action=correlation.delete&correlationids%5B0%5D=99005']],

			// Correlation condition add.
			[['link' => 'zabbix.php?action=correlation.condition.add&form_refresh=2&name=add&evaltype=0&formula=&'.
					'description=ssdsd&op_close_old=1&op_close_new=1&status=0&new_condition%5Btype%5D=0&new_condition%5B'.
					'operator%5D=0&new_condition%5Btag%5D=1111&add_condition=1']],

			// GUI update.
			[['link' => 'zabbix.php?action=gui.update&form_refresh=1&default_lang=en_GB&default_timezone=system&'.
					'default_theme=blue-theme&search_limit=1000&max_overview_table_size=50&max_in_table=51&'.
					'server_check_interval=0&work_period=1-5%2C09%3A00-18%3A00&show_technical_errors=0&'.
					'history_period=24h&period_default=1h&max_period=2y&update=Update']],

			// Housekeeping update.
			[['link' => 'zabbix.php?action=housekeeping.update&form_refresh=1&hk_events_mode=1&hk_events_trigger=365d&'.
					'hk_events_internal=1d&hk_events_discovery=1d&hk_events_autoreg=1d&hk_services_mode=1&'.
					'hk_services=365d&hk_audit_mode=1&hk_audit=365d&hk_sessions_mode=1&hk_sessions=365d&'.
					'hk_history_mode=1&hk_history_global=1&hk_history=90d&hk_trends_mode=1&update=Update']],

			// User group creation.
			[['link' => 'zabbix.php?form_refresh=1&name=1111&gui_access=0&users_status=0&debug_mode=0&'.
					'group_rights%5B0%5D%5Bname%5D=&group_rights%5B0%5D%5Bgrouped%5D=1&group_rights%5B0%5D%5B'.
					'permission%5D=-1&new_group_right%5Bpermission%5D=-1&new_tag_filter%5Btag%5D=&new_tag_filter'.
					'%5Bvalue%5D=&action=usergroup.create']],

			// User group massupdate (disable/enable).
			[['link' => 'zabbix.php?action=usergroup.massupdate&users_status=1&usrgrpids[0]=93']],

			// User group update.
			[['link' => 'zabbix.php?form_refresh=1&usrgrpid=93&name=1111&gui_access=0&users_status=1&debug_mode=0&'.
					'group_rights%5B0%5D%5Bname%5D=&group_rights%5B0%5D%5Bgrouped%5D=1&group_rights%5B0%5D%5B'.
					'permission%5D=-1&new_group_right%5Bpermission%5D=-1&new_tag_filter%5Btag%5D=&new_tag_filter'.
					'%5Bvalue%5D=&action=usergroup.update']],

			// User group delete.
			[['link' => 'zabbix.php?action=usergroup.delete&usrgrpids%5B0%5D=93']],

			// User group group right add.
			[['link' => 'zabbix.php?new_group_right%5Bgroupids%5D%5B%5D=50012&new_group_right%5Binclude_subgroups%5D=0&'.
					'new_group_right%5Bpermission%5D=-1&group_rights%5B0%5D%5Bname%5D=&group_rights%5B0%5D%5Bgrouped%5D=1&'.
					'group_rights%5B0%5D%5Bpermission%5D=-1&action=usergroup.groupright.add']],

			// User group tag filter add.
			[['link' => 'zabbix.php?new_tag_filter%5Binclude_subgroups%5D=0&new_tag_filter%5Btag%5D=&new_tag_filter'.
					'%5Bvalue%5D=&action=usergroup.tagfilter.add']],

			// Script creation.
			[['link' => 'zabbix.php?form_refresh=1&form=1&scriptid=0&name=11111&scope=1&menu_path=&type=5&execute_on=0&'.
					'authtype=0&username=&publickey=&privatekey=&password=&passphrase=&port=&command=&commandipmi=&'.
					'script=fdg&timeout=30s&description=&hgstype=0&usrgrpid=0&host_access=2&action=script.create']],

			// Script update.
			[['link' => 'zabbix.php?form_refresh=1&form=1&scriptid=203&name=11111&scope=1&menu_path=&type=5&'.
					'execute_on=2&authtype=0&username=&publickey=&privatekey=&password=&passphrase=&port=&command=&'.
					'commandipmi=&script=fdg&timeout=30s&description=zzzz&hgstype=0&usrgrpid=0&host_access=2&'.
					'action=script.update']],

			// Script delete.
			[['link' => 'zabbix.php?action=script.delete&scriptids%5B%5D=203']],

			// User role creation.
			[['link' => 'zabbix.php?form_refresh=1&name=sadasda&type=1&ui_monitoring_dashboard=1&ui_monitoring_problems='.
					'1&ui_monitoring_hosts=1&ui_monitoring_overview=1&ui_monitoring_latest_data=1&ui_monitoring_screens=1'.
					'&ui_monitoring_maps=1&ui_monitoring_discovery=0&ui_services_services=1&ui_inventory_overview=1&'.
					'ui_inventory_hosts=1&ui_reports_system_info=0&ui_reports_availability_report=1&ui_reports_top_triggers'.
					'=1&ui_reports_audit=0&ui_reports_action_log=0&ui_reports_notifications=0&ui_configuration_host_groups=0'.
					'&ui_configuration_templates=0&ui_configuration_hosts=0&ui_configuration_maintenance=0&'.
					'ui_configuration_actions=0&ui_configuration_event_correlation=0&ui_configuration_discovery=0&'.
					'ui_configuration_services=0&ui_administration_general=0&ui_administration_proxies=0&'.
					'ui_administration_authentication=0&ui_administration_user_groups=0&ui_administration_user_roles=0&'.
					'ui_administration_users=0&ui_administration_media_types=0&ui_administration_scripts=0&'.
					'ui_administration_queue=0&ui_default_access=1&modules_default_access=1&api_access=1&api_mode=0&'.
					'actions_edit_dashboards=1&actions_edit_maps=1&actions_edit_maintenance=0&actions_add_problem_comments'.
					'=1&actions_change_severity=1&actions_acknowledge_problems=1&actions_close_problems=1&'.
					'actions_execute_scripts=1&actions_manage_api_tokens=1&actions_default_access=1&action=userrole.create']],

			// User role update.
			[['link' => 'zabbix.php?form_refresh=1&roleid=5&name=sadasda&type=2&ui_monitoring_dashboard=1&'.
					'ui_monitoring_problems=1&ui_monitoring_hosts=1&ui_monitoring_overview=1&ui_monitoring_latest_data=1'.
					'&ui_monitoring_screens=1&ui_monitoring_maps=1&ui_monitoring_discovery=0&ui_monitoring_discovery=1&'.
					'ui_services_services=1&ui_inventory_overview=1&ui_inventory_hosts=1&ui_reports_system_info=0&'.
					'ui_reports_availability_report=1&ui_reports_top_triggers=1&ui_reports_audit=0&ui_reports_action_log=0'.
					'&ui_reports_notifications=0&ui_reports_notifications=1&ui_configuration_host_groups=0&'.
					'ui_configuration_host_groups=1&ui_configuration_templates=0&ui_configuration_templates=1&'.
					'ui_configuration_hosts=0&ui_configuration_hosts=1&ui_configuration_maintenance=0&'.
					'ui_configuration_maintenance=1&ui_configuration_actions=0&ui_configuration_actions=1&'.
					'ui_configuration_event_correlation=0&ui_configuration_discovery=0&ui_configuration_discovery=1&'.
					'ui_configuration_services=0&ui_configuration_services=1&ui_administration_general=0&'.
					'ui_administration_proxies=0&ui_administration_authentication=0&ui_administration_user_groups=0&'.
					'ui_administration_user_roles=0&ui_administration_users=0&ui_administration_media_types=0&'.
					'ui_administration_scripts=0&ui_administration_queue=0&ui_default_access=1&modules_default_access=1'.
					'&api_access=1&api_mode=0&actions_edit_dashboards=1&actions_edit_maps=1&actions_edit_maintenance=0'.
					'&actions_edit_maintenance=1&actions_add_problem_comments=1&actions_change_severity=1&'.
					'actions_acknowledge_problems=1&actions_close_problems=1&actions_execute_scripts=1&'.
					'actions_manage_api_tokens=1&actions_default_access=1&action=userrole.update']],

			// User role delete.
			[['link' => 'zabbix.php?action=userrole.delete&roleids%5B0%5D=5']],

			// Popup acknowledge creation.
			[['link' => 'zabbix.php?action=popup.acknowledge.create&eventids%5B0%5D=95&message=ddddd&scope=0']],

			// Proxy creation.
			[['link' => 'zabbix.php?form_refresh=1&proxyid=0&tls_accept=1&psk_edit_mode=1&host=dfsdfsdfsdfsf&status=5&'.
					'ip=127.0.0.1&dns=localhost&useip=1&port=10051&proxy_address=&description=&tls_in_none=1&action=proxy.create']],

			// Proxy update.
			[['link' => 'zabbix.php?form_refresh=1&proxyid=99455&tls_accept=1&psk_edit_mode=1&host=1111111&status=5&'.
					'ip=127.0.0.1&dns=localhost&useip=1&port=10051&proxy_address=&description=ffffff&'.
					'tls_in_none=1&action=proxy.update']],

			// Proxy delete.
			[['link' => 'zabbix.php?action=proxy.delete&proxyids[]=99455']],

			// Proxy host disable.
			[['link' => 'zabbix.php?form_refresh=1&proxyids%5B20000%5D=20000&action=proxy.hostdisable']],

			// Proxy host enable.
			[['link' => 'zabbix.php?form_refresh=1&proxyids%5B20000%5D=20000&action=proxy.hostenable']],

			// Authentication update.
			[['link' => 'zabbix.php?form_refresh=3&action=authentication.update&db_authentication_type=0&'.
					'authentication_type=0&passwd_min_length=8&passwd_check_rules%5B%5D=1&passwd_check_rules%5B%5D=2&'.
					'passwd_check_rules%5B%5D=4&passwd_check_rules%5B%5D=8&http_auth_enabled=1&http_login_form=0&'.
					'http_strip_domains=&http_case_sensitive=1&ldap_configured=0&change_bind_password=1&'.
					'saml_auth_enabled=0&update=Update']],

			// Media type create.
			[['link' => 'zabbix.php?form_refresh=1&form=1&mediatypeid=0&status=1&name=1111&type=0&'.
					'smtp_server=mail.example.com&smtp_port=25&smtp_helo=example.com&smtp_email=zabbix%40example.com&'.
					'smtp_security=0&smtp_authentication=0&smtp_username=&exec_path=&gsm_modem=%2Fdev%2FttyS0&passwd=&'.
					'content_type=1&parameters%5Bname%5D%5B%5D=URL&parameters%5Bvalue%5D%5B%5D=&parameters'.
					'%5Bname%5D%5B%5D=HTTPProxy&parameters%5Bvalue%5D%5B%5D=&parameters%5Bname%5D%5B%5D=To&parameters'.
					'%5Bvalue%5D%5B%5D=%7BALERT.SENDTO%7D&parameters%5Bname%5D%5B%5D=Subject&parameters%5Bvalue'.
					'%5D%5B%5D=%7BALERT.SUBJECT%7D&parameters%5Bname%5D%5B%5D=Message&parameters%5Bvalue%5D%5B%5D='.
					'%7BALERT.MESSAGE%7D&script=&timeout=30s&process_tags=0&show_event_menu=0&description=&status='.
					'0&maxsessions_type=one&maxsessions=1&maxattempts=3&attempt_interval=10s&action=mediatype.create']],

			// Media type update.
			[['link' => 'zabbix.php?form_refresh=1&form=1&mediatypeid=105&status=1&name=1111&type=0&smtp_server='.
					'mail.example.com&smtp_port=25&smtp_helo=example.com&smtp_email=zabbix%40example.com&smtp_security=0&'.
					'smtp_authentication=0&smtp_username=&exec_path=&gsm_modem=&passwd=&content_type=1&parameters%5Bname'.
					'%5D%5B%5D=URL&parameters%5Bvalue%5D%5B%5D=&parameters%5Bname%5D%5B%5D=HTTPProxy&parameters%5Bvalue'.
					'%5D%5B%5D=&parameters%5Bname%5D%5B%5D=To&parameters%5Bvalue%5D%5B%5D=%7BALERT.SENDTO%7D&parameters'.
					'%5Bname%5D%5B%5D=Subject&parameters%5Bvalue%5D%5B%5D=%7BALERT.SUBJECT%7D&parameters%5Bname'.
					'%5D%5B%5D=Message&parameters%5Bvalue%5D%5B%5D=%7BALERT.MESSAGE%7D&script=&timeout=30s&process_tags=0&'.
					'show_event_menu=0&description=sssss&status=0&maxsessions_type=one&maxsessions=1&maxattempts=3&'.
					'attempt_interval=10s&action=mediatype.update']],

			// Media type disable.
			[['link' => 'zabbix.php?action=mediatype.disable&mediatypeids[]=105']],

			// Media type enable.
			[['link' => 'zabbix.php?action=mediatype.enable&mediatypeids[]=105']],

			// Media type delete.
			[['link' => 'zabbix.php?action=mediatype.delete&mediatypeids[]=105']],

			// Trigger display update.
			[['link' => 'zabbix.php?action=trigdisplay.update&form_refresh=1&custom_color=1&problem_unack_color=CC0000&'.
					'problem_unack_style=1&problem_ack_color=CC0000&problem_ack_style=1&ok_unack_color=009900&'.
					'ok_unack_style=1&ok_ack_color=009900&ok_ack_style=1&ok_period=5m&blink_period=2m&'.
					'severity_name_0=Not+classified&severity_color_0=97AAB3&severity_name_1=Information&'.
					'severity_color_1=7499FF&severity_name_2=Warning&severity_color_2=FFC859&'.
					'severity_name_3=Average&severity_color_3=FFA059&severity_name_4=High&severity_color_4=E97659&'.
					'severity_name_5=Disaster&severity_color_5=E45959&update=Update']],

			// Other configuration parameters update.
			[['link' => 'zabbix.php?action=miscconfig.update&form_refresh=1&discovery_groupid=5&'.
					'default_inventory_mode=-1&alert_usrgrpid=15&snmptrap_logging=1&login_attempts=5&'.
					'login_block=30s&validate_uri_schemes=1&uri_valid_schemes=http%2Chttps%2Cftp%2Cfile'.
					'%2Cmailto%2Ctel%2Cssh&x_frame_options=SAMEORIGIN&iframe_sandboxing_enabled=1&'.
					'iframe_sandboxing_exceptions=&socket_timeout=4s&connect_timeout=3s&media_type_test_timeout=65s&'.
					'script_timeout=60s&item_test_timeout=60s&update=Update']],

			// Discovery create.
			[['link' => 'zabbix.php?form_refresh=1&name=11111&proxy_hostid=0&iprange=192.168.0.1-254&delay=1h&'.
					'dchecks%5Bnew1%5D%5Btype%5D=3&dchecks%5Bnew1%5D%5Bports%5D=21&dchecks%5Bnew1%5D%5B'.
					'snmpv3_securitylevel%5D=0&dchecks%5Bnew1%5D%5Bsnmpv3_authprotocol%5D=0&dchecks%5Bnew1%5D%5B'.
					'snmpv3_privprotocol%5D=0&dchecks%5Bnew1%5D%5Bname%5D=FTP&dchecks%5Bnew1%5D%5Bhost_source%5D=1&dchecks'.
					'%5Bnew1%5D%5Bname_source%5D=0&dchecks%5Bnew1%5D%5Bdcheckid%5D=new1&uniqueness_criteria=-1&host_source='.
					'1&name_source=0&status=0&action=discovery.create']],

			// Discovery delete.
			[['link' => 'zabbix.php?form_refresh=1&druleids%5B7%5D=7&action=discovery.delete']],

			// Discovery disable.
			[['link' => 'zabbix.php?druleids[0]=2&action=discovery.disable']],

			// Discovery enable.
			[['link' => 'zabbix.php?druleids[0]=2&action=discovery.enable']],

			// Discovery update.
			[['link' => 'zabbix.php?form_refresh=1&druleid=2&name=Local+network&proxy_hostid=0&iprange=192.168.0.1-254&'.
					'delay=2h&dchecks%5B2%5D%5Btype%5D=9&dchecks%5B2%5D%5Bdcheckid%5D=2&dchecks%5B2%5D%5Bports%5D=10050&'.
					'dchecks%5B2%5D%5Buniq%5D=0&dchecks%5B2%5D%5Bhost_source%5D=1&dchecks%5B2%5D%5Bname_source%5D=0&'.
					'dchecks%5B2%5D%5Bname%5D=Zabbix+agent+%22system.uname%22&dchecks%5B2%5D%5Bkey_%5D=system.uname&'.
					'uniqueness_criteria=-1&host_source=1&name_source=0&status=1&action=discovery.update']],

			// Export.
			[['link' => 'zabbix.php?action=export.hosts&format=yaml&backurl=hosts.php&form_refresh=1&hosts%5B50011%5D=50011']],

			// Favourite create.
			[['link' => 'zabbix.php?action=favourite.create&object=screenid&objectid=200021']],

			// Favourite delete.
			[['link' => 'zabbix.php?action=favourite.delete&object=screenid&objectid=200021']],

			// Host creation.
			[[
				'link' => 'zabbix.php?action=host.create&flags=0&tls_connect=1&tls_accept=1&host=1111&visiblename=&'.
						'groups%5B%5D%5Bnew%5D=111&interfaces%5B1%5D%5Bitems%5D=&interfaces%5B1%5D%5Blocked%5D=&'.
						'interfaces%5B1%5D%5BisNew%5D=true&interfaces%5B1%5D%5Binterfaceid%5D=1&interfaces%5B1%5D%5Btype%5D=1&'.
						'interfaces%5B1%5D%5Bip%5D=127.0.0.1&interfaces%5B1%5D%5Bdns%5D=&interfaces%5B1%5D%5Buseip%5D=1&'.
						'interfaces%5B1%5D%5Bport%5D=10050&mainInterfaces%5B1%5D=1&description=&proxy_hostid=0&status=0&'.
						'ipmi_authtype=-1&ipmi_privilege=2&ipmi_username=&ipmi_password=&tags%5B0%5D%5Btag%5D=&'.
						'tags%5B0%5D%5Bvalue%5D=&show_inherited_macros=0&macros%5B0%5D%5Bmacro%5D=&macros%5B0%5D%5Bvalue%5D=&'.
						'macros%5B0%5D%5Btype%5D=0&macros%5B0%5D%5Bdescription%5D=&inventory_mode=-1&tls_connect=1&'.
						'tls_in_none=1&tls_psk_identity=&tls_psk=&tls_issuer=&tls_subject='
			]],

			// Host update.
			[[
				'link' => 'zabbix.php?action=host.update&form=update&flags=0&tls_connect=1&tls_accept=1&psk_edit_mode=1&'.
						'hostid=99452&host=11111111&visiblename=&groups%5B%5D=50020&interfaces%5B55079%5D%5Bitems%5D=false&'.
						'interfaces%5B55079%5D%5BisNew%5D=&interfaces%5B55079%5D%5Binterfaceid%5D=55079&interfaces'.
						'%5B55079%5D%5Btype%5D=1&interfaces%5B55079%5D%5Bip%5D=127.0.0.1&interfaces%5B55079%5D%5Bdns%5D=&'.
						'interfaces%5B55079%5D%5Buseip%5D=1&interfaces%5B55079%5D%5Bport%5D=10050&mainInterfaces%5B1%5D=55079&'.
						'description=&proxy_hostid=0&status=0&ipmi_authtype=-1&ipmi_privilege=2&ipmi_username=&ipmi_password=&'.
						'tags%5B0%5D%5Btag%5D=&tags%5B0%5D%5Bvalue%5D=&show_inherited_macros=0&macros%5B0%5D%5Bmacro%5D=&'.
						'macros%5B0%5D%5Bvalue%5D=&macros%5B0%5D%5Btype%5D=0&macros%5B0%5D%5Bdescription%5D=&inventory_mode=-1&'.
						'tls_connect=1&tls_in_none=1&tls_psk_identity=&tls_psk=&tls_issuer=&tls_subject='
			]],

			// Host delete.
			[[
				'link' => 'zabbix.php?action=host.massdelete&hostids%5B0%5D=99452'
			]],

			// Host disable.
			[[
				'link' => 'zabbix.php?action=popup.massupdate.host&visible%5Bstatus%5D=1&update=1&backurl='.
					'zabbix.php%3Faction%3Dhost.list&status=1'
			]],

			// Host enable.
			[[
				'link' => 'zabbix.php?action=popup.massupdate.host&visible%5Bstatus%5D=1&update=1&backurl='.
					'zabbix.php%3Faction%3Dhost.list&status=0'
			]],

			// Notifications get.
			[['link' => 'zabbix.php?action=notifications.get&known_eventids%5B%5D=126']],

			// Notifications mute.
			[['link' => 'zabbix.php?action=notifications.mute&muted=1']],

			// Popup import.
			[['link' => 'zabbix.php?rules_preset=host&action=popup.import']],

			// Popup item test edit.
			[['link' => 'zabbix.php?action=popup.itemtest.edit&key=agent.hostname&delay=1m&value_type=3&item_type=0&'.
					'itemid=0&interfaceid=50040&hostid=50012&test_type=0&step_obj=-2&show_final_result=1&get_value=1']],

			// Popup item test get value.
			[['link' => 'zabbix.php?action=popup.itemtest.getvalue&key=agent.hostname&value_type=3&item_type=0&itemid=0&'.
					'interface%5Baddress%5D=127.0.0.1&interface%5Bport%5D=10050&proxy_hostid=0&test_type=0&hostid=50012&value=']],

			// Popup item test send.
			[['link' => 'zabbix.php?key=agent.hostname&delay=&value_type=4&item_type=0&itemid=0&interfaceid=0&get_value=1&'.
					'interface%5Baddress%5D=127.0.0.1&interface%5Bport%5D=10050&proxy_hostid=0&show_final_result=1&'.
					'test_type=0&hostid=10386&valuemapid=0&value=&action=popup.itemtest.send']],

			// Popup maintenance period.
			[['link' => 'zabbix.php?index=1&action=popup.maintenance.period']],

			// Popup massupdate host.
			[['link' => 'zabbix.php?ids%5B%5D=50011&ids%5B%5D=50012&action=popup.massupdate.host']],

			// Popup massupdate item.
			[['link' => 'zabbix.php?ids%5B%5D=99086&context=host&prototype=0&action=popup.massupdate.item']],

			// Popup massupdate template.
			[['link' => 'zabbix.php?ids%5B%5D=10076&action=popup.massupdate.template']],

			// Popup massupdate trigger.
			[['link' => 'zabbix.php?ids%5B%5D=100034&context=host&action=popup.massupdate.trigger']],

			// Popup media type test edit.
			[['link' => 'zabbix.php?mediatypeid=29&action=popup.mediatypetest.edit']],

			// Popup media type test send.
			[['link' => 'zabbix.php?action=popup.mediatypetest.send&mediatypeid=10&parameters%5B0%5D%5Bname%5D=alert_message&'.
					'parameters%5B0%5D%5Bvalue%5D=%7BALERT.MESSAGE%7D&parameters%5B1%5D%5Bname%5D=alert_subject&'.
					'parameters%5B1%5D%5Bvalue%5D=%7BALERT.SUBJECT%7D&parameters%5B2%5D%5Bname%5D=discord_endpoint&'.
					'parameters%5B2%5D%5Bvalue%5D=%7BALERT.SENDTO%7D&parameters%5B3%5D%5Bname%5D=event_date&parameters'.
					'%5B3%5D%5Bvalue%5D=%7BEVENT.DATE%7D&parameters%5B4%5D%5Bname%5D=event_id&parameters%5B4%5D%5Bvalue'.
					'%5D=%7BEVENT.ID%7D&parameters%5B5%5D%5Bname%5D=event_name&parameters%5B5%5D%5Bvalue%5D=%7'.
					'BEVENT.NAME%7D&parameters%5B6%5D%5Bname%5D=event_nseverity&parameters%5B6%5D%5Bvalue%5D=%7'.
					'BEVENT.NSEVERITY%7D&parameters%5B7%5D%5Bname%5D=event_opdata&parameters%5B7%5D%5Bvalue%5D=%7'.
					'BEVENT.OPDATA%7D&parameters%5B8%5D%5Bname%5D=event_recovery_date&parameters%5B8%5D%5Bvalue%5D=%7'.
					'BEVENT.RECOVERY.DATE%7D&parameters%5B9%5D%5Bname%5D=event_recovery_time&parameters%5B9%5D%5Bvalue'.
					'%5D=%7BEVENT.RECOVERY.TIME%7D&parameters%5B10%5D%5Bname%5D=event_severity&parameters%5B10%5D%5Bvalue'.
					'%5D=%7BEVENT.SEVERITY%7D&parameters%5B11%5D%5Bname%5D=event_source&parameters%5B11%5D%5Bvalue%5D=%7'.
					'BEVENT.SOURCE%7D&parameters%5B12%5D%5Bname%5D=event_tags&parameters%5B12%5D%5Bvalue%5D=%7BEVENT.TAGS%7'.
					'D&parameters%5B13%5D%5Bname%5D=event_time&parameters%5B13%5D%5Bvalue%5D=%7BEVENT.TIME%7D&parameters'.
					'%5B14%5D%5Bname%5D=event_update_action&parameters%5B14%5D%5Bvalue%5D=%7BEVENT.UPDATE.ACTION%7D'.
					'&parameters%5B15%5D%5Bname%5D=event_update_date&parameters%5B15%5D%5Bvalue%5D=%7BEVENT.UPDATE.DATE%7'.
					'D&parameters%5B16%5D%5Bname%5D=event_update_message&parameters%5B16%5D%5Bvalue%5D=%7BEVENT.'.
					'UPDATE.MESSAGE%7D&parameters%5B17%5D%5Bname%5D=event_update_status&parameters%5B17%5D%5Bvalue%5D=%7'.
					'BEVENT.UPDATE.STATUS%7D&parameters%5B18%5D%5Bname%5D=event_update_time&parameters%5B18%5D%5Bvalue%5D=%7'.
					'BEVENT.UPDATE.TIME%7D&parameters%5B19%5D%5Bname%5D=event_update_user&parameters%5B19%5D%5Bvalue%5D=%7'.
					'BUSER.FULLNAME%7D&parameters%5B20%5D%5Bname%5D=event_value&parameters%5B20%5D%5Bvalue%5D='.
					'%7BEVENT.VALUE%7D&parameters%5B21%5D%5Bname%5D=host_ip&parameters%5B21%5D%5Bvalue%5D=%7BHOST.IP%7D&'.
					'parameters%5B22%5D%5Bname%5D=host_name&parameters%5B22%5D%5Bvalue%5D=%7BHOST.NAME%7D&parameters'.
					'%5B23%5D%5Bname%5D=trigger_description&parameters%5B23%5D%5Bvalue%5D=%7BTRIGGER.DESCRIPTION%7D&'.
					'parameters%5B24%5D%5Bname%5D=trigger_id&parameters%5B24%5D%5Bvalue%5D=%7BTRIGGER.ID%7D&parameters'.
					'%5B25%5D%5Bname%5D=use_default_message&parameters%5B25%5D%5Bvalue%5D=false&parameters%5B26%5D%5Bname'.
					'%5D=zabbix_url&parameters%5B26%5D%5Bvalue%5D=%7B%24ZABBIX.URL%7D']],

			// Popup script execution.
			[['link' => 'zabbix.php?scriptid=1&hostid=10386&action=popup.scriptexec']],

			// Profile update.
			[['link' => 'zabbix.php?form_refresh=1&action=userprofile.edit&userid=1&medias%5B3%5D%5Bmediatypeid%5D=10&'.
					'medias%5B3%5D%5Bperiod%5D=1-7%2C00%3A00-24%3A00&medias%5B3%5D%5Bsendto%5D=test%40jabber.com&'.
					'medias%5B3%5D%5Bseverity%5D=16&medias%5B3%5D%5Bactive%5D=0&medias%5B3%5D%5Bname%5D=Discord&'.
					'medias%5B3%5D%5Bmediatype%5D=4&medias%5B1%5D%5Bmediatypeid%5D=1&medias%5B1%5D%5Bperiod%5D='.
					'1-7%2C00%3A00-24%3A00&medias%5B1%5D%5Bsendto%5D%5B0%5D=test2%40zabbix.com&medias%5B1%5D%5B'.
					'severity%5D=60&medias%5B1%5D%5Bactive%5D=1&medias%5B1%5D%5Bname%5D=Email&medias%5B1%5D%5B'.
					'mediatype%5D=0&medias%5B0%5D%5Bmediatypeid%5D=1&medias%5B0%5D%5Bperiod%5D=1-7%2C00%3A00-24%3A00&'.
					'medias%5B0%5D%5Bsendto%5D%5B0%5D=test%40zabbix.com&medias%5B0%5D%5Bseverity%5D=63&medias%5B0%5D%5B'.
					'active%5D=0&medias%5B0%5D%5Bname%5D=Email&medias%5B0%5D%5Bmediatype%5D=0&medias%5B4%5D%5B'.
					'mediatypeid%5D=12&medias%5B4%5D%5Bperiod%5D=6-7%2C09%3A00-18%3A00&medias%5B4%5D%5Bsendto%5D='.
					'test_account&medias%5B4%5D%5Bseverity%5D=63&medias%5B4%5D%5Bactive%5D=0&medias%5B4%5D%5Bname%5D='.
					'Jira&medias%5B4%5D%5Bmediatype%5D=4&medias%5B2%5D%5Bmediatypeid%5D=3&medias%5B2%5D%5Bperiod%5D='.
					'1-7%2C00%3A00-24%3A00&medias%5B2%5D%5Bsendto%5D=123456789&medias%5B2%5D%5Bseverity%5D=32&'.
					'medias%5B2%5D%5Bactive%5D=0&medias%5B2%5D%5Bname%5D=SMS&medias%5B2%5D%5Bmediatype%5D=2&lang=default&'.
					'timezone=default&theme=default&autologin=1&autologout=0&refresh=30s&rows_per_page=99&url=&'.
					'messages%5Benabled%5D=0&action=userprofile.update']],

			// User creation.
			[['link' => 'zabbix.php?form_refresh=2&action=user.edit&userid=0&username=1111&name=&surname=&'.
					'user_groups%5B%5D=8&password1=1&password2=1&lang=default&timezone=default&theme=default&autologin=0&'.
					'autologout=0&refresh=30s&rows_per_page=50&url=&roleid=1&user_type=User&action=user.create']],

			// User delete.
			[['link' => 'zabbix.php?action=user.delete&userids[]=95']],

			// User update.
			[['link' => 'zabbix.php?form_refresh=1&action=user.edit&userid=95&username=11111&name=&surname=&'.
					'user_groups%5B%5D=8&lang=default&timezone=default&theme=default&autologin=0&autologout=0&'.
					'refresh=30s&rows_per_page=50&url=&roleid=1&user_type=User&action=user.update']],

			// User unblock.
			[['link' => 'zabbix.php?form_refresh=1&userids%5B6%5D=6&action=user.unblock']],

			// Scheduled report creation.
			[['link' => 'zabbix.php?form_refresh=1&userid=1&name=testsid&dashboardid=1&period=0&cycle=0&hours=00&'.
				'minutes=00&weekdays%5B1%5D=1&weekdays%5B8%5D=8&weekdays%5B32%5D=32&weekdays%5B2%5D=2&'.
				'weekdays%5B16%5D=16&weekdays%5B64%5D=64&weekdays%5B4%5D=4&active_since=&active_till='.
				'&subject=&message=&subscriptions%5B0%5D%5Brecipientid%5D=1&subscriptions%5B0%5D%5Brecipient_type%5D=0&'.
				'subscriptions%5B0%5D%5Brecipient_name%5D=Admin+%28Zabbix+Administrator%29&'.
				'subscriptions%5B0%5D%5Brecipient_inaccessible%5D=0&subscriptions%5B0%5D%5Bcreatorid%5D=1&'.
				'subscriptions%5B0%5D%5Bcreator_type%5D=0&subscriptions%5B0%5D%5Bcreator_name%5D=Admin+%28Zabbix+Administrator%29&'.
				'subscriptions%5B0%5D%5Bcreator_inaccessible%5D=0&subscriptions%5B0%5D%5Bexclude%5D=0&description=&'.
				'status=0&action=scheduledreport.create']],

			// Scheduled report delete.
			[['link' => 'zabbix.php?action=scheduledreport.delete&reportids[]=1']],

			// Scheduled report update.
			[['link' => 'zabbix.php?form_refresh=1&reportid=8&old_dashboardid=2&userid=1&'.
				'name=Report+for+filter+-+enabled+sid&dashboardid=2&period=3&cycle=2&hours=00&'.
				'minutes=00&weekdays%5B1%5D=1&weekdays%5B8%5D=8&weekdays%5B32%5D=32&weekdays%5B2%5D=2&'.
				'weekdays%5B16%5D=16&weekdays%5B64%5D=64&weekdays%5B4%5D=4&active_since=&active_till=&'.
				'subject=&message=&subscriptions%5B0%5D%5Brecipientid%5D=1&subscriptions%5B0%5D%5Brecipient_type%5D=0&'.
				'subscriptions%5B0%5D%5Brecipient_name%5D=Admin+%28Zabbix+Administrator%29&'.
				'subscriptions%5B0%5D%5Brecipient_inaccessible%5D=0&subscriptions%5B0%5D%5Bcreatorid%5D=0&'.
				'subscriptions%5B0%5D%5Bcreator_type%5D=1&subscriptions%5B0%5D%5Bcreator_name%5D=Recipient&'.
				'subscriptions%5B0%5D%5Bcreator_inaccessible%5D=0&subscriptions%5B0%5D%5Bexclude%5D=0&description=&'.
				'status=0&action=scheduledreport.update']],

			// Scheduled report test.
			[['link' => 'zabbix.php?action=popup.scheduledreport.test&period=2&now=1627543595&dashboardid=1'.
				'&name=Report+for+testFormScheduledReport&subject=Report+subject+for+testFormScheduledReport&'.
				'message=Report+message+text']]
		];
	}

	/**
	 * @dataProvider getLinksData
	 */
	public function testSID_Links($data) {
		foreach ([$data['link'], $data['link'].'&sid=test111116666666'] as $link) {
			$this->page->login()->open($link)->waitUntilReady();

			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "Admin". You have no permissions to access this page.');
			$this->query('button:Go to "Dashboard"')->one()->waitUntilClickable()->click();

			$this->assertStringContainsString('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
		}
	}

	public static function getElementRemoveData() {
		return [
			// Map creation.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=Create+map'
				]
			],

			// Map update.
			[
				[
					'db' => 'SELECT * FROM sysmaps',
					'link' => 'sysmaps.php?form=update&sysmapid=3'
				]
			],

			// Host groups creation.
			[
				[
					'db' => 'SELECT * FROM hosts_groups',
					'link' => 'hostgroups.php?form=create'
				]
			],

			// Host groups update.
			[
				[
					'db' => 'SELECT * FROM hosts_groups',
					'link' => 'hostgroups.php?form=update&groupid=50012'
				]
			],

			// Template creation.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'templates.php?form=create'
				]
			],

			// Template update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'templates.php?form=update&templateid=10169'
				]
			],

			// Hosts creation.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'server_error' => true,
					'link' => 'zabbix.php?action=host.edit'
				]
			],

			// Hosts update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'server_error' => true,
					'link' => 'zabbix.php?action=host.edit&hostid=99062'
				]
			],

			// Item update.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=update&hostid=50011&itemid=99086&context=host'
				]
			],

			// Item creation.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=create&hostid=50011&context=host'
				]
			],

			// Trigger update.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?form=update&triggerid=100034&context=host'
				]
			],

			// Trigger creation.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?hostid=50011&form=create&context=host'
				]
			],

			// Graph update.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?form=update&graphid=700026&filter_hostids%5B0%5D=99202&context=host'
				]
			],

			// Graph creation.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?hostid=50011&form=create&context=host'
				]
			],

			// Discovery rule update.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=update&itemid=99107&context=host'
				]
			],

			// Discovery rule creation.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=create&hostid=99202&context=host'
				]
			],

			// Web update.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=update&hostid=50001&httptestid=102&context=host'
				]
			],

			// Web creation.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=create&hostid=50001&context=host'
				]
			],

			// Maintenance creation.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'link' => 'maintenance.php?form=create'
				]
			],

			// Maintenance update.
			[
				[
					'db' => 'SELECT * FROM maintenances',
					'link' => 'maintenance.php?form=update&maintenanceid=3'
				]
			],

			// Action creation.
			[
				[
					'db' => 'SELECT * FROM actions',
					'link' => 'actionconf.php?eventsource=0&form=Create+action'
				]
			],

			// Action update.
			[
				[
					'db' => 'SELECT * FROM actions',
					'link' => 'actionconf.php?form=update&actionid=3'
				]
			],

			// Event correlation creation.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=correlation.edit'
				]
			],

			// Event correlation update.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'incorrect_request' => true,
					'link' => 'zabbix.php?correlationid=99002&action=correlation.edit'
				]
			],

			// Discovery creation.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=discovery.edit'
				]
			],

			// Discovery update.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=discovery.edit&druleid=5'
				]
			],

			// GUI update.
			[
				[
					'db' => 'SELECT * FROM config',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=gui.edit'
				]
			],

			// Autoregistration update.
			[
				[
					'db' => 'SELECT * FROM autoreg_host',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=autoreg.edit'
				]
			],

			// Housekeeping update.
			[
				[
					'db' => 'SELECT * FROM housekeeper',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=housekeeping.edit'
				]
			],

			// Image update.
			[
				[
					'db' => 'SELECT * FROM images',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=image.edit&imageid=1'
				]
			],

			// Image creation.
			[
				[
					'db' => 'SELECT * FROM images',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=image.edit&imagetype=1'
				]
			],

			// Icon map update.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=iconmap.edit&iconmapid=101'
				]
			],

			// Icon map creation.
			[
				[
					'db' => 'SELECT * FROM icon_map',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=iconmap.edit'
				]
			],

			// Regular expression update.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=regex.edit&regexid=20'
				]
			],

			// Regular expression added.
			[
				[
					'db' => 'SELECT * FROM regexps',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=regex.edit'
				]
			],

			// Macros update.
			[
				[
					'db' => 'SELECT * FROM globalmacro',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=macros.edit'
				]
			],

			// Trigger displaying update.
			[
				[
					'db' => 'SELECT * FROM config',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=trigdisplay.edit'
				]
			],

			// API token creation.
			[
				[
					'db' => 'SELECT * FROM token',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=token.edit'
				]
			],

			// API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=token.edit&tokenid='
				]
			],

			// Other update.
			[
				[
					'db' => 'SELECT * FROM config',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=miscconfig.edit'
				]
			],

			// Proxy update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=proxy.edit&proxyid=20000'
				]
			],

			// Proxy creation.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=proxy.edit'
				]
			],

			// Authentication update.
			[
				[
					'db' => 'SELECT * FROM config',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=authentication.edit'
				]
			],

			// User group update.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=usergroup.edit&usrgrpid=7'
				]
			],

			// User group creation.
			[
				[
					'db' => 'SELECT * FROM users_groups',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=usergroup.edit'
				]
			],

			// User update.
			[
				[
					'db' => 'SELECT * FROM users',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=user.edit&userid=1'
				]
			],

			// User creation.
			[
				[
					'db' => 'SELECT * FROM users',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=user.edit'
				]
			],

			// Media update.
			[
				[
					'db' => 'SELECT * FROM media',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=mediatype.edit&mediatypeid=1'
				]
			],

			// Media creation.
			[
				[
					'db' => 'SELECT * FROM media',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=mediatype.edit'
				]
			],

			// Script update.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=script.edit&scriptid=1'
				]
			],

			// Script creation.
			[
				[
					'db' => 'SELECT * FROM scripts',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=script.edit'
				]
			],

			// User profile update.
			[
				[
					'db' => 'SELECT * FROM profiles',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=userprofile.edit'
				]
			],

			// User role update.
			[
				[
					'db' => 'SELECT * FROM role',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=userrole.edit&roleid=2'
				]
			],

			// User role creation.
			[
				[
					'db' => 'SELECT * FROM role',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=userrole.edit'
				]
			],

			// User API token creation.
			[
				[
					'db' => 'SELECT * FROM token',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=user.token.edit'
				]
			],

			// User API token update.
			[
				[
					'db' => 'SELECT * FROM token',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=user.token.edit&tokenid='
				]
			],

			// Scheduled report creation.
			[
				[
					'db' => 'SELECT * FROM report',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=scheduledreport.edit'
				]
			],
			// Scheduled report update.
			[
				[
					'db' => 'SELECT * FROM report',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=scheduledreport.edit&reportid=3'
				]
			]
		];
	}

	/**
	 * @dataProvider getElementRemoveData
	 */
	public function testSID_ElementRemove($data) {
		$hash_before = CDBHelper::getHash($data['db']);
		$url = (!str_contains($data['link'], 'tokenid') ? $data['link'] : $data['link'].self::$token_id);
		$this->page->login()->open($url)->waitUntilReady();
		$this->query('xpath://input[@name="sid"]')->one()->delete();
		$this->query(($this->query('button:Update')->exists()) ? 'button:Update' : 'xpath://button[text()="Add" and'.
				' @type="submit"]')->waitUntilClickable()->one()->click();

		if (CTestArrayHelper::get($data, 'incorrect_request')) {
			$message = 'Access denied';
			$details = 'You are logged in as "Admin". You have no permissions to access this page.';
		}
		elseif (CTestArrayHelper::get($data, 'server_error')) {
			$message = 'Unexpected server error.';
			$details = null;
		}
		else {
			$message = 'Zabbix has received an incorrect request.';
			$details = 'Operation cannot be performed due to unauthorized request.';
		}
		$this->assertMessage(TEST_BAD, $message, $details);

		if (CTestArrayHelper::get($data, 'incorrect_request'))  {
			$this->query('button:Go to "Dashboard"')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->assertStringContainsString('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
		}

		$this->assertEquals($hash_before, CDBHelper::getHash($data['db']));
	}
}
