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

import java.util.Formatter;

class ZabbixException extends Exception
{
	ZabbixException(String message)
	{
		super(message);
	}

	ZabbixException(String message, Object... args)
	{
		this(new Formatter().format(message, args).toString());
	}

	ZabbixException(String message, Throwable cause)
	{
		super(message, cause);
	}

	ZabbixException(Throwable cause)
	{
		super(cause);
	}

	static Throwable getRootCause(Throwable e)
	{
		Throwable cause = null;
		Throwable result = e;

		while ((null != (cause = result.getCause())) && (result != cause))
			result = cause;

		return result;
	}

	static String getRootCauseMessage(Throwable e)
	{
		if (e != null)
			return getRootCause(e).getMessage();

		return null;
	}
}
