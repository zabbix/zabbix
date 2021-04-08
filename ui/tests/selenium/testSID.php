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
			// Icon mapping delete
			[['link' => 'zabbix.php?action=iconmap.delete&iconmapid=101']],

			// Icon maping update.
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

			// Timeselector update.
			[['link' => 'zabbix.php?action=timeselector.update&type=11&method=rangechange']],

			// Value mapping delete.
			[['link' => 'zabbix.php?action=valuemap.delete&valuemapids%5B0%5D=83']],

			// Value mapping update.
			[['link' => 'zabbix.php?action=valuemap.update&form_refresh=1&valuemapid=161&name=new_name&mappings'.
					'%5B0%5D%5Bvalue%5D=test&mappings%5B0%5D%5Bnewvalue%5D=test&update=Update']],

			// Dashboard properties update.
			[['link' => 'zabbix.php?action=dashboard.update&dashboardid=143&userid=1&name=sssdfsfsdfNew+dashboardss']],

			// Template dashboard widget edit.
			[['link' => 'zabbix.php?action=dashboard.widget.edit&templateid=10076']],

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

			// Dashboard share update.
			[['link' => 'zabbix.php?action=dashboard.share.update&form_refresh=1&dashboardid=143&users%5Bempty_user'.
					'%5D=1&userGroups%5Bempty_group%5D=1&private=0']],

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
					'correlation' => true,
					'link' => 'correlation.php?delete=1&form=update&correlationid=99005'
				]
			],

			// Event correlation enable.
			[
				[
					'correlation' => true,
					'link' => 'correlation.php?action=correlation.massenable&g_correlationid[]=99004'
				]
			],

			// Event correlation disable.
			[
				[
					'correlation' => true,
					'link' => 'correlation.php?action=correlation.massdisable&g_correlationid[]=99004'
				]
			],

			// Event correlation creation.
			[
				[
					'correlation' => true,
					'link' => 'correlation.php?form_refresh=3&form=Create+correlation&name=11111&evaltype=0&formula=&'.
							'conditions%5B0%5D%5Btype%5D=0&conditions%5B0%5D%5Boperator%5D=0&conditions%5B0%5D%5Btag%5D'.
							'=ttt&conditions%5B0%5D%5Bformulaid%5D=A&description=&status=0&operations%5B%5D%5Btype%5D=0&add=Add'
				]
			],

			// Event correlation update.
			[
				[
					'correlation' => true,
					'link' => 'correlation.php?form_refresh=1&form=update&correlationid=99004&name=11111&evaltype=0'.
						'&formula=&conditions%5B0%5D%5Btype%5D=0&conditions%5B0%5D%5Btag%5D=ttt&conditions%5B0%5D%5B'.
						'formulaid%5D=A&conditions%5B0%5D%5Boperator%5D=0&description=sssss&status=0&operations'.
						'%5B%5D%5Btype%5D=0&update=Update'
				]
			]
		];
	}

	/**
	 * @dataProvider getLinksData
	 */
	public function testSID_Links($data) {
		foreach ([$data['link'], $data['link'].'&sid=test111116666666'] as $link) {
			$this->page->login()->open($link)->waitUntilReady();
			if (array_key_exists('correlation', $data)) {
				$this->assertMessage(TEST_BAD, 'Zabbix has received an incorrect request.', 'Operation cannot be'.
						' performed due to unauthorized request.');
			}
			else {
				$this->assertMessage(TEST_BAD, 'Access denied', 'You are logged in as "Admin". You have no permissions to access this page.');
				$this->query('button:Go to dashboard')->one()->waitUntilClickable()->click();
				$this->assertContains('zabbix.php?action=dashboard.view', $this->page->getCurrentUrl());
			}
		}
	}
}
