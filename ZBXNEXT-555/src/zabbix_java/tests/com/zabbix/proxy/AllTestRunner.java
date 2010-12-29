package com.zabbix.proxy;

public class AllTestRunner
{
	public static void main(String[] args)
	{
		String[] testClasses = new String[]
		{
			"ZabbixItemTest"
		};

		for (int i = 0; i < testClasses.length; i++)
			testClasses[i] = "com.zabbix.proxy." + testClasses[i];

		org.junit.runner.JUnitCore.main(testClasses);
	}
}
