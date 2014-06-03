/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

package com.zabbix.gateway;

class GeneralInformation
{
	public static final String APPLICATION_NAME = "Zabbix Java Gateway";
	public static final String REVISION_DATE = "30 May 2014";
	public static final String REVISION = "{ZABBIX_REVISION}";
	public static final String VERSION = "2.3.2";

	public static void printVersion()
	{
		System.out.printf("%s v%s (revision %s) (%s)\n", APPLICATION_NAME, VERSION, REVISION, REVISION_DATE);
	}
}
