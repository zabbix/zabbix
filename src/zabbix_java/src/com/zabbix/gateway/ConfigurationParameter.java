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

package com.zabbix.gateway;

import java.io.File;
import java.net.InetAddress;

class ConfigurationParameter
{
	static final int TYPE_INTEGER = 0;
	static final int TYPE_INETADDRESS = 1;
	static final int TYPE_FILE = 2;

	private String name;
	private int type;
	private Object value;
	private InputValidator validator;
	private PostInputValidator postValidator;

	ConfigurationParameter(String name, int type, Object defaultValue, InputValidator validator, PostInputValidator postValidator)
	{
		this.name = name;
		this.type = type;
		this.value = defaultValue;
		this.validator = validator;
		this.postValidator = postValidator;
	}

	String getName()
	{
		return name;
	}

	int getType()
	{
		return type;
	}

	Object getValue()
	{
		return value;
	}

	void setValue(String text)
	{
		Object userValue = null;

		try
		{
			switch (type)
			{
				case TYPE_INTEGER:
					userValue = Integer.valueOf(text);
					break;
				case TYPE_INETADDRESS:
					userValue = InetAddress.getByName(text);
					break;
				case TYPE_FILE:
					userValue = new File(text);
					break;
				default:
					throw new IllegalArgumentException(String.format("unknown type %d", type));
			}
		}
		catch (Exception e)
		{
			throw new IllegalArgumentException(e);
		}

		if (null != validator && !validator.validate(userValue))
			throw new IllegalArgumentException(String.format("bad value for %s parameter: '%s'", name, text));

		if (null != postValidator)
			postValidator.execute(userValue);

		this.value = userValue;
	}
}
