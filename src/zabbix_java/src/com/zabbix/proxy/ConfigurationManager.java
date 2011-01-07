package com.zabbix.proxy;

class ConfigurationManager
{
	public static final String LISTEN_IP = "ListenIP";
	public static final String LISTEN_PORT = "ListenPort";
	public static final String START_POLLERS = "StartPollers";

	private static ConfigurationParameter[] parameters =
	{
		new ConfigurationParameter(
				START_POLLERS, ConfigurationParameter.TYPE_INTEGER, new Integer("5"), new IntegerValidator(1, 255)),
		new ConfigurationParameter(
				LISTEN_IP, ConfigurationParameter.TYPE_INETADDRESS, null, null),
		new ConfigurationParameter(
				LISTEN_PORT, ConfigurationParameter.TYPE_INTEGER, new Integer("10052"), new IntegerValidator(1, 65535))
	};

	public static void parseConfiguration()
	{
		for (int i = 0; i < parameters.length; i++)
		{
			String property = System.getProperty(getPackage() + "." + parameters[i].getName());

			if (null != property)
				parameters[i].setValue(property);
		}
	}

	public static ConfigurationParameter getParameter(String name)
	{
		for (int i = 0; i < parameters.length; i++)
			if (parameters[i].getName().equals(name))
				return parameters[i];

		throw new IllegalArgumentException("unknown configuration parameter '" + name + "'");
	}

	public static int getIntegerParameterValue(String name)
	{
		return ((Integer)getParameter(name).getValue()).intValue();
	}

	public static String getPackage()
	{
		return "com.zabbix.proxy";
	}
}
