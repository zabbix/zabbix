/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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
	static final String APPLICATION_NAME = "Zabbix Java Gateway";
	static final String REVISION_DATE = "19 August 2025";
	static final String REVISION = "{ZABBIX_REVISION}";
	static final String VERSION = "6.0.42rc1";

	static void printVersion()
	{
		System.out.println(String.format("%s v%s (revision %s) (%s)", APPLICATION_NAME, VERSION, REVISION, REVISION_DATE));
	}
}
