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

require_once dirname(__FILE__) . '/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

class testSID extends CWebTest {

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

			// Regular expressions delete.
			[['link' => 'zabbix.php?action=regex.delete&regexids%5B0%5D=20']],

			// Regular expressions update.
			[['link' => 'zabbix.php?action=regex.update&regexid=20&form_refresh=1&name=1_regexp_1_1&expressions'.
					'%5B0%5D%5Bexpression_type%5D=0&expressions%5B0%5D%5Bexpression%5D=first+test+string&'.
					'expressions%5B0%5D%5Bexp_delimiter%5D=%2C&expressions%5B0%5D%5Bcase_sensitive%5D='.
					'1&expressions%5B0%5D%5Bexpressionid%5D=20&test_string=first+test+string&update=Update']],

			// Regular expressions creation.
			[['link' => 'zabbix.php?action=regex.create&form_refresh=1&name=cccc&expressions%5B0%5D%5Bexpression_type%5D=0&'.
					'expressions%5B0%5D%5Bexpression%5D=ccccc&expressions%5B0%5D%5Bexp_delimiter%5D=%2C&test_string=&add=Add']],

			// Regular expressions test.
			[['link' => 'zabbix.php?ajaxdata%5BtestString%5D=&ajaxdata%5Bexpressions%5D%5B0%5D%5Bexpression%5D=2&'.
					'ajaxdata%5Bexpressions%5D%5B0%5D%5Bexpression_type%5D=0&ajaxdata%5Bexpressions%5D%5B0%5D%5B'.
					'exp_delimiter%5D=%2C&ajaxdata%5Bexpressions%5D%5B0%5D%5Bcase_sensitive%5D=0&action=regex.test']],

			// Timeselector update.
			[['link' => 'zabbix.php?action=timeselector.update&type=11&method=rangechange']],

			// Value mapping delete.
			[['link' => 'zabbix.php?action=valuemap.delete&valuemapids%5B0%5D=83']],

			// Value mapping update.
			[['link' => 'zabbix.php?action=valuemap.update&form_refresh=1&valuemapid=161&name=new_name&mappings'.
					'%5B0%5D%5Bvalue%5D=test&mappings%5B0%5D%5Bnewvalue%5D=test&update=Update']],

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

			// Template dashboard widget edit.
			[['link' => 'zabbix.php?action=dashboard.widget.edit&templateid=10076']],

			// Dashboard widget sanitize.
			[['link' => 'zabbix.php?action=dashboard.widget.sanitize&fields=%7B%22reference%22%3A%22IACGE%22%7D&type=navtree']],

			// Macros update.
			[['link' => 'zabbix.php?action=macros.update&form_refresh=1&macros%5B16%5D%5Bmacro%5D=%7B%24FGDFGDF%7D&'.
					'macros%5B16%5D%5Bvalue%5D=dfsdfsfs&macros%5B16%5D%5Btype%5D=0&macros%5B16%5D%5Bdescription%5D=&'.
					'update=Update']],

			// Autoregistration update.
			[['link' => 'zabbix.php?action=autoreg.edit&form_refresh=1&tls_accept=2&tls_in_psk=1&tls_psk_identity=sss&'.
					'tls_psk=88888888888888888888888888888888&action=autoreg.update']],

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
					'authentication_type=0&http_auth_enabled=1&http_login_form=0&http_strip_domains=&'.
					'http_case_sensitive=1&ldap_configured=0&change_bind_password=1&saml_auth_enabled=0&update=Update']],

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

			// Event correlation delete.
			[
				[
					'incorrect_request' => true,
					'link' => 'correlation.php?delete=1&form=update&correlationid=99005'
				]
			],

			// Event correlation enable.
			[
				[
					'incorrect_request' => true,
					'link' => 'correlation.php?action=correlation.massenable&g_correlationid[]=99004'
				]
			],

			// Event correlation disable.
			[
				[
					'incorrect_request' => true,
					'link' => 'correlation.php?action=correlation.massdisable&g_correlationid[]=99004'
				]
			],

			// Event correlation creation.
			[
				[
					'incorrect_request' => true,
					'link' => 'correlation.php?form_refresh=3&form=Create+correlation&name=11111&evaltype=0&formula=&'.
							'conditions%5B0%5D%5Btype%5D=0&conditions%5B0%5D%5Boperator%5D=0&conditions%5B0%5D%5Btag%5D'.
							'=ttt&conditions%5B0%5D%5Bformulaid%5D=A&description=&status=0&operations%5B%5D%5Btype%5D=0&add=Add'
				]
			],

			// Event correlation update.
			[
				[
					'incorrect_request' => true,
					'link' => 'correlation.php?form_refresh=1&form=update&correlationid=99004&name=11111&evaltype=0'.
						'&formula=&conditions%5B0%5D%5Btype%5D=0&conditions%5B0%5D%5Btag%5D=ttt&conditions%5B0%5D%5B'.
						'formulaid%5D=A&conditions%5B0%5D%5Boperator%5D=0&description=sssss&status=0&operations'.
						'%5B%5D%5Btype%5D=0&update=Update'
				]
			],

			// Event correlation condition add.
			[
				[
					'incorrect_request' => true,
					'link' => 'correlation.php?form_refresh=1&form=Create+correlation&name=asdsa&evaltype=0&formula=&'.
						'description=sdasda&status=0&new_condition%5Btype%5D=0&new_condition%5Boperator%5D=0&'.
						'new_condition%5Btag%5D=aaaa&add_condition=1'
				]
			],

			// Application creation.
			[
				[
					'incorrect_request' => true,
					'link' => 'applications.php?form_refresh=1&form=create&hostid=50011&appname=1111&add=Add'
				]
			],

			// Application delete.
			[
				[
					'incorrect_request' => true,
					'link' => 'applications.php?delete=1&hostid=50011&form=update&applicationid=99014'
				]
			],

			// Application disable.
			[
				[
					'incorrect_request' => true,
					'link' => 'applications.php?form_refresh=1&applications%5B99010%5D=99010&action=application.massdisable'
				]
			],

			// Application enable.
			[
				[
					'incorrect_request' => true,
					'link' => 'applications.php?form_refresh=1&applications%5B99010%5D=99010&action=application.massenable'
				]
			],

			// Application update.
			[
				[
					'incorrect_request' => true,
					'link' => 'applications.php?form_refresh=1&form=update&hostid=50011&applicationid=99014&appname=11111&update=Update'
				]
			],

			// Discovery creation.
			[
				[
					'incorrect_request' => true,
					'link' => 'discoveryconf.php?form_refresh=1&form=Create+discovery+rule&name=aaa&proxy_hostid=0&'.
						'iprange=192.168.0.1-254&delay=1h&dchecks%5Bnew1%5D%5Btype%5D=3&dchecks%5Bnew1%5D%5Bports%5D'.
						'=21&dchecks%5Bnew1%5D%5Bsnmpv3_securitylevel%5D=0&dchecks%5Bnew1%5D%5Bname%5D=FTP&'.
						'dchecks%5Bnew1%5D%5Bhost_source%5D=1&dchecks%5Bnew1%5D%5Bname_source%5D=0&dchecks%5Bnew'.
						'1%5D%5Bdcheckid%5D=new1&uniqueness_criteria=-1&host_source=1&name_source=0&status=1&add=Add'
				]
			],

			// Discovery delete.
			[
				[
					'incorrect_request' => true,
					'link' => 'discoveryconf.php?form_refresh=1&g_druleid%5B7%5D=7&action=drule.massdelete'
				]
			],

			// Discovery disable.
			[
				[
					'incorrect_request' => true,
					'link' => 'discoveryconf.php?action=drule.massdisable&g_druleid[]=4'
				]
			],

			// Discovery enable.
			[
				[
					'incorrect_request' => true,
					'link' => 'discoveryconf.php?action=drule.massenable&g_druleid[]=6'
				]
			],

			// Discovery update.
			[
				[
					'incorrect_request' => true,
					'link' => 'discoveryconf.php?form_refresh=1&form=update&druleid=2&name=Local+network&proxy_hostid=0&'.
						'iprange=192.168.0.1-254&delay=2h&dchecks%5B2%5D%5Btype%5D=9&dchecks%5B2%5D%5Bdcheckid%5D=2&'.
						'dchecks%5B2%5D%5Bports%5D=10050&dchecks%5B2%5D%5Buniq%5D=0&dchecks%5B2%5D%5Bhost_source%5D=1&'.
						'dchecks%5B2%5D%5Bname_source%5D=0&dchecks%5B2%5D%5Bname%5D=Zabbix+agent+%22system.uname%22&'.
						'dchecks%5B2%5D%5Bkey_%5D=system.uname&uniqueness_criteria=-1&host_source=1&name_source=0&'.
						'status=1&update=Update'
				]
			],

			// Export.
			[['link' => 'zabbix.php?action=export.hosts.xml&backurl=hosts.php&form_refresh=1&hosts%5B10358%5D=10358']],

			// Favourite create.
			[['link' => 'zabbix.php?action=favourite.create&object=screenid&objectid=200021']],

			// Favourite delete.
			[['link' => 'zabbix.php?action=favourite.delete&object=screenid&objectid=200021']],

			// Host creation.
			[
				[
					'incorrect_request' => true,
					'link' => 'hosts.php?form_refresh=1&form=create&flags=0&tls_connect=1&tls_accept=1&host=1111&visiblename=&'.
					'groups%5B%5D%5Bnew%5D=111&interfaces%5B1%5D%5Bitems%5D=&interfaces%5B1%5D%5Blocked%5D=&'.
					'interfaces%5B1%5D%5BisNew%5D=true&interfaces%5B1%5D%5Binterfaceid%5D=1&interfaces%5B1%5D%5Btype%5D=1&'.
					'interfaces%5B1%5D%5Bip%5D=127.0.0.1&interfaces%5B1%5D%5Bdns%5D=&interfaces%5B1%5D%5Buseip%5D=1&'.
					'interfaces%5B1%5D%5Bport%5D=10050&mainInterfaces%5B1%5D=1&description=&proxy_hostid=0&status=0&'.
					'ipmi_authtype=-1&ipmi_privilege=2&ipmi_username=&ipmi_password=&tags%5B0%5D%5Btag%5D=&'.
					'tags%5B0%5D%5Bvalue%5D=&show_inherited_macros=0&macros%5B0%5D%5Bmacro%5D=&macros%5B0%5D%5Bvalue%5D=&'.
					'macros%5B0%5D%5Btype%5D=0&macros%5B0%5D%5Bdescription%5D=&inventory_mode=-1&tls_connect=1&'.
					'tls_in_none=1&tls_psk_identity=&tls_psk=&tls_issuer=&tls_subject=&add=Add'
				]
			],

			// Host update.
			[
				[
					'incorrect_request' => true,
					'link' => 'hosts.php?form_refresh=1&form=update&flags=0&tls_connect=1&tls_accept=1&psk_edit_mode=1&'.
					'hostid=99452&host=11111111&visiblename=&groups%5B%5D=50020&interfaces%5B55079%5D%5Bitems%5D=false&'.
					'interfaces%5B55079%5D%5BisNew%5D=&interfaces%5B55079%5D%5Binterfaceid%5D=55079&interfaces'.
					'%5B55079%5D%5Btype%5D=1&interfaces%5B55079%5D%5Bip%5D=127.0.0.1&interfaces%5B55079%5D%5Bdns%5D=&'.
					'interfaces%5B55079%5D%5Buseip%5D=1&interfaces%5B55079%5D%5Bport%5D=10050&mainInterfaces%5B1%5D=55079&'.
					'description=&proxy_hostid=0&status=0&ipmi_authtype=-1&ipmi_privilege=2&ipmi_username=&ipmi_password=&'.
					'tags%5B0%5D%5Btag%5D=&tags%5B0%5D%5Bvalue%5D=&show_inherited_macros=0&macros%5B0%5D%5Bmacro%5D=&'.
					'macros%5B0%5D%5Bvalue%5D=&macros%5B0%5D%5Btype%5D=0&macros%5B0%5D%5Bdescription%5D=&inventory_mode=-1&'.
					'tls_connect=1&tls_in_none=1&tls_psk_identity=&tls_psk=&tls_issuer=&tls_subject=&update=Update'
				]
			],

			// Host delete.
			[
				[
					'incorrect_request' => true,
					'link' => 'hosts.php?delete=1&form=update&hostid=99452'
				]
			],

			// Host disable.
			[
				[
					'incorrect_request' => true,
					'link' => 'hosts.php?action=host.massdisable&hosts[0]=50011'
				]
			],

			// Host enable.
			[
				[
					'incorrect_request' => true,
					'link' => 'hosts.php?action=host.massenable&hosts[0]=50011'
				]
			],

			// Notifications get.
			[['link' => 'zabbix.php?action=notifications.get&known_eventids%5B%5D=126']],

			// Notifications mute.
			[['link' => 'zabbix.php?action=notifications.mute&muted=1']],

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
			[['link' => 'zabbix.php?form_refresh=1&userids%5B6%5D=6&action=user.unblock']]
		];
	}

	/**
	 * @dataProvider getLinksData
	 */
	public function testSID_Links($data) {
		foreach ([$data['link'], $data['link'].'&sid=test111116666666'] as $link) {
			$this->page->login()->open($link)->waitUntilReady();
			if (array_key_exists('incorrect_request', $data)) {
				$this->assertMessage(TEST_BAD, 'Zabbix has received an incorrect request.', 'Operation cannot be'.
						' performed due to unauthorized request.');
			}
			else {
				$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "Admin". You have no permissions to access this page.');
				$this->query('button:Go to dashboard')->one()->waitUntilClickable()->click();
				$this->assertContains('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
			}
		}
	}

	public static function getElementRemoveData() {
		return [
			// Screen creation.
			[
				[
					'db' => 'SELECT * FROM screens',
					'link' => 'screenconf.php?form=Create+screen'
				]
			],

			// Screen update.
			[
				[
					'db' => 'SELECT * FROM screens',
					'link' => 'screenconf.php?form=update&screenid=200021'
				]
			],

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
					'link' => 'hosts.php?form=create'
				]
			],

			// Hosts update.
			[
				[
					'db' => 'SELECT * FROM hosts',
					'link' => 'hosts.php?form=update&hostid=10084'
				]
			],

			// Application update.
			[
				[
					'db' => 'SELECT * FROM applications',
					'link' => 'applications.php?form=update&applicationid=99010&hostid=50011'
				]
			],

			// Application creation.
			[
				[
					'db' => 'SELECT * FROM applications',
					'link' => 'applications.php?form=create&hostid=50011'
				]
			],

			// Item update.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=update&hostid=50011&itemid=99086'
				]
			],

			// Item creation.
			[
				[
					'db' => 'SELECT * FROM items',
					'link' => 'items.php?form=create&hostid=50011'
				]
			],

			// Trigger update.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?form=update&triggerid=100034'
				]
			],

			// Trigger creation.
			[
				[
					'db' => 'SELECT * FROM triggers',
					'link' => 'triggers.php?hostid=50011&form=create'
				]
			],

			// Graph update.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?form=update&graphid=700026&filter_hostids%5B0%5D=99202'
				]
			],

			// Graph creation.
			[
				[
					'db' => 'SELECT * FROM graphs',
					'link' => 'graphs.php?hostid=50011&form=create'
				]
			],

			// Discovery rule update.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=update&itemid=99107'
				]
			],

			// Discovery rule creation.
			[
				[
					'db' => 'SELECT * FROM drules',
					'link' => 'host_discovery.php?form=create&hostid=99202'
				]
			],

			// Web update.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=update&hostid=50001&httptestid=102'
				]
			],

			// Web creation.
			[
				[
					'db' => 'SELECT * FROM httptest',
					'link' => 'httpconf.php?form=create&hostid=50001'
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
					'link' => 'correlation.php?form=Create+correlation'
				]
			],

			// Event correlation update.
			[
				[
					'db' => 'SELECT * FROM correlation',
					'link' => 'correlation.php?form=update&correlationid=99003'
				]
			],

			// Discovery creation.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'link' => 'discoveryconf.php?form=Create+discovery+rule'
				]
			],

			// Discovery update.
			[
				[
					'db' => 'SELECT * FROM host_discovery',
					'link' => 'discoveryconf.php?form=update&druleid=2'
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

			// Value map update.
			[
				[
					'db' => 'SELECT * FROM valuemaps',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=valuemap.edit&valuemapid=83'
				]
			],

			// Value map creation.
			[
				[
					'db' => 'SELECT * FROM valuemaps',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=valuemap.edit'
				]
			],

			// Working time update.
			[
				[
					'db' => 'SELECT * FROM config',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=workingtime.edit'
				]
			],

			// Trigger severities update.
			[
				[
					'db' => 'SELECT * FROM config',
					'incorrect_request' => true,
					'link' => 'zabbix.php?action=trigseverity.edit'
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
			]
		];
	}

	/**
	 * @dataProvider getElementRemoveData
	 */
	public function testSID_ElementRemove($data) {
		$hash_before = CDBHelper::getHash($data['db']);
		$this->page->login()->open($data['link'])->waitUntilReady();
		$this->query('xpath://input[@name="sid"]')->one()->delete();
		$this->query(($this->query('button:Update')->exists()) ? 'button:Update' : 'xpath://button[text()="Add" and'.
				' @type="submit"]')->waitUntilClickable()->one()->click();

		if (array_key_exists('incorrect_request', $data)) {
			$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "Admin". You have no permissions to access this page.');
			$this->query('button:Go to dashboard')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->assertContains('zabbix.php?action=dashboard', $this->page->getCurrentUrl());
		}
		else {
			$this->assertMessage(TEST_BAD, 'Zabbix has received an incorrect request.', 'Operation cannot be'.
					' performed due to unauthorized request.');
		}

		$this->assertEquals($hash_before, CDBHelper::getHash($data['db']));
	}
}
