package com.zabbix.proxy;

import java.util.Vector;

class ZabbixItem
{
	private String keyId = null;
	private Vector<String> args = null;

	public ZabbixItem(String key)
	{
		int bracket = key.indexOf('[');

		if (-1 != bracket)
		{
			if (key.charAt(key.length() - 1) != ']')
				throw new IllegalArgumentException("malformed item key: " + key);

			keyId = key.substring(0, bracket);
			args = parseArguments(key.substring(bracket + 1, key.length() - 1));
		}
		else
			keyId = key;
	}

	public String getKeyId()
	{
		return keyId;
	}
	
	public String getArgument(int n)
	{
		return args.elementAt(n - 1);
	}

	public int getArgumentCount()
	{
		return null == args ? 0 : args.size();
	}

	private Vector<String> parseArguments(String keyArgs)
	{
		Vector<String> args = new Vector<String>();

		while (!keyArgs.equals(""))
		{
			if ('"' == keyArgs.charAt(0))
			{
				int index = 1;

				while (index < keyArgs.length())
				{
					if ('"' == keyArgs.charAt(index) && '\\' != keyArgs.charAt(index - 1))
						break;
					else
						index++;
				}

				if (index == keyArgs.length())
					throw new IllegalArgumentException("malformed quoted arguments: " + keyArgs);

				args.add(keyArgs.substring(1, index).replaceAll("\\\"", "\""));

				if (index + 1 < keyArgs.length())
					if (',' != keyArgs.charAt(index + 1))
						throw new IllegalArgumentException("badly terminated quoted argument: " + keyArgs);
					else
						index += 2;
				else
					index++;
				
				keyArgs = keyArgs.substring(index);
			}
			else
			{
				int index = 0;

				while (index < keyArgs.length() && ',' != keyArgs.charAt(index))
					index++;

				args.add(keyArgs.substring(0, index));

				if (index < keyArgs.length())
					index++;

				keyArgs = keyArgs.substring(index);
			}
		}

		return args;
	}
}
