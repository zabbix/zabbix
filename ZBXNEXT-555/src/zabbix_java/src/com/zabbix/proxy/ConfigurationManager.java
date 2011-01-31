package com.zabbix.proxy;

class ConfigurationManager
{
	public static final String LISTEN_IP = "ListenIP";
	public static final String LISTEN_PORT = "ListenPort";
	public static final String START_POLLERS = "StartPollers";

	private static ConfigurationParameter[] parameters =
	{
		new ConfigurationParameter(START_POLLERS, ConfigurationParameter.TYPE_INTEGER, 5, new IntegerValidator(1, 255)),
		new ConfigurationParameter(LISTEN_IP, ConfigurationParameter.TYPE_INETADDRESS, null, null),
		new ConfigurationParameter(LISTEN_PORT, ConfigurationParameter.TYPE_INTEGER, 10051, new IntegerValidator(1, 65535))
	};

	public static void parseConfiguration()
	{
		for (ConfigurationParameter parameter : parameters)
		{
			String property = System.getProperty(getPackage() + "." + parameter.getName());

			if (null != property)
				parameter.setValue(property);
		}
	}

	public static ConfigurationParameter getParameter(String name)
	{
		for (ConfigurationParameter parameter : parameters)
			if (parameter.getName().equals(name))
				return parameter;

		throw new IllegalArgumentException("unknown configuration parameter '" + name + "'");
	}

	public static int getIntegerParameterValue(String name)
	{
		return (Integer)getParameter(name).getValue();
	}

	public static String getPackage()
	{
		return "com.zabbix.proxy";
	}
}
