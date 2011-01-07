package com.zabbix.proxy;

import java.net.InetAddress;

class ConfigurationParameter
{
	public static final int TYPE_INTEGER = 0;
	public static final int TYPE_INETADDRESS = 1;

	private String name;
	private int type;
	private Object value;
	private InputValidator validator;

	public ConfigurationParameter(String name, int type, Object defaultValue, InputValidator validator)
	{
		this.name = name;
		this.type = type;
		this.value = defaultValue;
		this.validator = validator;
	}

	public String getName()
	{
		return name;
	}

	public int getType()
	{
		return type;
	}

	public Object getValue()
	{
		return value;
	}

	public void setValue(String text)
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
			}
		}
		catch (Exception exception)
		{
			throw new IllegalArgumentException(exception);
		}

		if (null != validator && !validator.validate(userValue))
			throw new IllegalArgumentException("value '" + text + "' not allowed for parameter '" + name + "'");

		this.value = userValue;
	}
}
