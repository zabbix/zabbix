package com.zabbix.proxy;

import java.util.Formatter;

class ZabbixException extends Exception
{
	public ZabbixException(String message)
	{
		super(message);
	}

	public ZabbixException(String message, Object... args)
	{
		this(new Formatter().format(message, args).toString());
	}

	public ZabbixException(String message, Throwable cause)
	{
		super(message, cause);
	}

	public ZabbixException(Throwable cause)
	{
		super(cause);
	}
}
