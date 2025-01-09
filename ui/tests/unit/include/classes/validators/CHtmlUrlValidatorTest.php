<?php declare(strict_types = 0);
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


use PHPUnit\Framework\TestCase;

class CHtmlUrlValidatorTest extends TestCase {
	protected function setUp(): void {
		$settings = $this->createMock(CSettings::class);
		$settings->method('get')
			->will($this->returnValue([
				CSettingsHelper::VALIDATE_URI_SCHEMES => '1',
				CSettingsHelper::URI_VALID_SCHEMES => 'http,https,ftp,file,mailto,tel,ssh'
			]));

		$instances_map = [
			['settings', $settings]
		];
		$api_service_factory = $this->createMock(CApiServiceFactory::class);
		$api_service_factory->method('getObject')
			->will($this->returnValueMap($instances_map));

		API::setApiServiceFactory($api_service_factory);
	}

	// Expected results are defined assuming that CSettingsHelper::VALIDATE_URI_SCHEMES is enabled (set to be 1).
	public function providerValidateURL() {
		return [
			// Valid URLs.
			['http',													[],															true],
			['http://zabbix.com',										[],															true],
			['https://zabbix.com',										[],															true],
			['http://localhost',										[],															true],
			['http://192.168.1.1',										[],															true],
			['http://localhost/file.php',								[],															true],
			['http://localhost/file.html',								[],															true],
			['http://localhost/file',									[],															true],
			['http://zabbix.php',										[],															true],
			['http://hello/world/hosts.html?abc=123',					[],															true],
			['http:/zabbix.php',										[],															true], // Because we allow tel:1-111-111-1111 and "/zabbix.php" is a valid path which falls in same category.
			['http:localost',											[],															true], // Because we allow tel:1-111-111-1111 and "localost" is a valid path which falls in same category.
			['http:/localost',											[],															true], // Because we allow tel:1-111-111-1111 and "/localost" is a valid path which falls in same category.
			['http/',													[],															true], // Because "http/" is a valid relative path.
			['http:/localhost/zabbix.php',								[],															true], // Because we allow tel:1-111-111-1111 and "/localhost/zabbix.php" is a valid path which falls in same category.
			['http:myhost/zabbix.php',									[],															true], // Because we allow tel:1-111-111-1111 and "myhost/zabbix.php" is a valid path which falls in same category.
			['localhost',												[],															true],
			['notzabbix.php',											[],															true],
			['zabbix.php',												[],															true],
			['hosts.html',												[],															true],
			['/secret/.htaccess',										[],															true], // No file type restrictions.
			['/zabbix.php',												[],															true],
			['subdir/zabbix.php',										[],															true],
			['subdir/hosts/id/10084',									[],															true],
			['subdir/'.'/100500/',										[],															true], // Comment hook does not allow "//".
			['zabbix.php/..',											[],															true],
			['hosts/..php',												[],															true],
			['subdir1/../subdir2/../subdir3/',							[],															true],
			['subdir1/subdir2/zabbix.php',								[],															true],
			['192.168.1.1.',											[],															true], // Not a valid IP, but it is accepted as "path".
			['zabbix.php?a=1',											[],															true],
			['zabbix.php?action=image.list',							[],															true],
			['chart_bar.php?a=1&b=2',									[],															true],
			['mailto:example@example.com',								[],															true],
			['file://localhost/path',									[],															true],
			['tel:1-111-111-1111',										[],															true],
			['ssh://username@hostname:/path ',							[],															true],
			['/chart_bar.php?a=1&b=2',									[],															true],
			['http://localhost:{$PORT}',								[],															true], // Macros allowed.
			['http://localhost:{MANUALINPUT}',							['allow_manualinput_macro' => true],						true], // Manual input macro allowed.
			['http://{$INVALID!MACRO}',									[],															true], // Macros allowed, but it's not a valid macro.
			['/',														[],															true], // "/" is a valid path to home directory.
			['/../',													[],															true],
			['../',														[],															true],
			['/..',														[],															true],
			['../././not_so_zabbix',									[],															true],
			['jav&#x09;ascript:alert(1];',								[],															true], // "jav" is a valid path with everything else in "fragment".
			['ftp://user@host:21',										[],															true],
			['ftp://somehost',											[],															true],
			['ftp://user@host',											[],															true],
			['{$USER_URL_MACRO}',										[],															true],
			['{$USER_URL_MACRO}?a=1',									[],															true],
			['http://{$USER_URL_MACRO}?a=1',							[],															true],
			['http://{$USER_URL_MACRO}',								[],															true],
			['http://{{$M}.regsub("(.*)", \1)}',						[],															true],
			['http://{{{$USER_URL_MACRO}',								[],															true],
			['http://{$MACRO{$MACRO}}',									[],															true],
			['{$MACRO{',												[],															true],
			["\x00\x20https://zabbix.com\x1F\x20",						[],															true], // Leading and trailing C0 control and space characters are ignored by browsers.
			["h\tt\rt\nps://zabbix.com",								[],															true], // CR, LF and TAB characters are ignored by browsers.
			['ht tps://zabbix.com',										[],															true], // URL with spaces in schema is treated as a path.
			// Inventory macros are going to be considered as "path".
			['{INVENTORY.URL.A}',										['allow_inventory_macro' => INVENTORY_URL_MACRO_NONE],		true],
			['{INVENTORY.URL.A}',										['allow_inventory_macro' => INVENTORY_URL_MACRO_HOST],		true],
			['{INVENTORY.URL.A}',										['allow_inventory_macro' => INVENTORY_URL_MACRO_TRIGGER],	true],
			['{INVENTORY.URL.A1}',										['allow_inventory_macro' => INVENTORY_URL_MACRO_HOST],		true],
			['{INVENTORY.URL.A1}',										['allow_inventory_macro' => INVENTORY_URL_MACRO_TRIGGER],	true],
			['{{INVENTORY.URL.B}.regsub("(\d+)", \1)}',					['allow_inventory_macro' => INVENTORY_URL_MACRO_HOST],		true],
			// Event tag macros are going to be considered as "path".
			['text{EVENT.TAGS."JIRAID"}text',							[],															true],
			['text{EVENT.TAGS."JIRAID"}text',							['allow_event_tags_macro' => true],							true],
			// Macros not allowed.
			['http://{$USER_URL_MACRO}',								['allow_user_macro' => false],								true], // User macros not allowed, but it's a host.
			['{$USER_URL_MACRO}',										['allow_user_macro' => false],								true],
			['{INVENTORY.URL.A}',										['allow_user_macro' => false],								true],
			['http://localhost/{$USER_URL_MACRO}/',						['allow_user_macro' => false],								true], // User macros not allowed, but it's a subdir.
			['http://localhost/zabbix.php?hostid={$ID}',				['allow_user_macro' => false],								true], // User macros not allowed, but it's in query.
			['http://localhost/zabbix.php?hostid=1#comment={$COMMENT}',	['allow_user_macro' => false],								true],
			['http://localhost/{NOT_AUSER_MACRO}/',						['allow_user_macro' => false],								true], // User macros not allowed, but it's not a macro.
			['http://localhost?host={HOST.NAME}',						['allow_user_macro' => false],								true],
			// Invalid URLs.
			['http:?abc',												[],															false], // Scheme with no host.
			['http:/',													[],															false], // Special case where single "/" is not allowed in path.
			['http://',													[],															false], // url_parse() returns false.
			['http:///',												[],															false], // url_parse() returns false.
			['http:',													[],															false], // Scheme with no host.
			['http://?',												[],															false], // url_parse() returns false.
			["\x00\x20javascript:alert(1)\x1F\x20",						[],															false], // Invalid scheme. Leading and trailing C0 control and space characters are ignored by browsers.
			["ja\tva\rsc\nript:alert(1)",								[],															false], // Invalid scheme. CR, LF and TAB characters are ignored by browsers.
			['javascript:alert(]',										[],															false], // Invalid scheme.
			['protocol://{$INVALID!MACRO}',								[],															false], // Invalid scheme. Also macro is not valid, but that's secondary.
			['',														[],															false], // Cannot be empty.
			['ftp://user@host:port',									[],															false], // Scheme is allowed, but "port" is not a valid number and url_parse() returns false.
			['vbscript:msgbox(]',										[],															false], // Invalid scheme.
			['notexist://localhost',									[],															false], // Invalid scheme.
			['http://localhost:{$PORT}',								['allow_user_macro' => false],								false], // User macro not allowed.
			['http://localhost:{MANUALINPUT}',							[],															false] // Manual input macro not allowed.
		];
	}

	/**
	 * @dataProvider providerValidateURL
	 */
	public function test_validateURL($url, $options, $expected) {
		$this->assertSame($expected, CHtmlUrlValidator::validate($url, $options));
	}

	public function dataProviderValidateSameSiteURL() {
		return [
			['zabbix.php',									true],
			['zabbix.php?',									true],
			['zabbix.php?action=host.list',					true],
			['zabbix.php?action=item.list&context=host',	true],
			['zabbix.php?action=host.list#id=12345',		true],
			['zabbix.php?action=item.list&context=host&filter_hostids%5B%5D=10605',	true],

			['items1.php',								false],
			['items.html',								false],
			['zabbix.php&itemids=12345',				false],
			['http://www.zabbix.com/zabbix.php',		false],
			['http://www.zabbix.com',					false],
			['www.zabbix.com',							false],
			['zabbix.com',								false]
		];
	}

	/**
	 * @dataProvider dataProviderValidateSameSiteURL
	 */
	public function testValidateSameSiteURL($url, $expected) {
		$this->assertSame($expected, CHtmlUrlValidator::validateSameSite($url));
	}
}
