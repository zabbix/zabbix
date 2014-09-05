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

import org.json.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class InternalItemChecker extends ItemChecker
{
	private static final Logger logger = LoggerFactory.getLogger(InternalItemChecker.class);

	public InternalItemChecker(JSONObject request) throws ZabbixException
	{
		super(request);
	}

	@Override
	protected String getStringValue(String key) throws Exception
	{
		ZabbixItem item = new ZabbixItem(key);

		if (item.getKeyId().equals("zabbix"))
		{
			if (3 != item.getArgumentCount() ||
					!item.getArgument(1).equals("java") ||
					!item.getArgument(2).equals(""))
				throw new ZabbixException("required key format: zabbix[java,,<parameter>]");

			String parameter = item.getArgument(3);

			if (parameter.equals("version"))
				return GeneralInformation.VERSION;
			else if (parameter.equals("ping"))
				return "1";
			else
				throw new ZabbixException("third parameter '%s' is not supported", parameter);
		}
		else
			throw new ZabbixException("key ID '%s' is not supported", item.getKeyId());
	}
}
