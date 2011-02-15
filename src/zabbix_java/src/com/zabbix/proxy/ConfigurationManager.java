package com.zabbix.proxy;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

class ConfigurationManager
{
	private static final Logger logger = LoggerFactory.getLogger(ConfigurationManager.class);

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
		logger.debug("starting to parse configuration parameters");

		for (ConfigurationParameter parameter : parameters)
		{
			String property = System.getProperty(getPackage() + "." + parameter.getName());

			if (null != property)
			{
				logger.debug("found {} configuration parameter with value '{}'", parameter.getName(), property);
				parameter.setValue(property);
			}
		}

		logger.debug("finished parsing configuration parameters");
	}

	public static ConfigurationParameter getParameter(String name)
	{
		for (ConfigurationParameter parameter : parameters)
			if (parameter.getName().equals(name))
				return parameter;

		throw new IllegalArgumentException("unknown configuration parameter: '" + name + "'");
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
