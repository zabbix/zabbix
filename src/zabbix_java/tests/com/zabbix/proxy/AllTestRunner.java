package com.zabbix.proxy;

public class AllTestRunner
{
	public static void main(String[] args)
	{
		String[] testClasses = new String[]
		{
			"IntegerValidatorTest",
			"ZabbixItemTest"
		};

		for (int i = 0; i < testClasses.length; i++)
			testClasses[i] = ConfigurationManager.getPackage() + "." + testClasses[i];

		org.junit.runner.JUnitCore.main(testClasses);
	}
}
