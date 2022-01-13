/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

import javax.management.*;
import java.lang.management.*;

public class SimpleAgent
{
	private MBeanServer mbs = null;

	public SimpleAgent()
	{
		mbs = ManagementFactory.getPlatformMBeanServer();

		Hello helloBean = new Hello();
		ObjectName helloName = null;

		try
		{
			helloName = new ObjectName("FOO:name=HelloBean");
			mbs.registerMBean(helloBean, helloName);
		}
		catch(Exception e)
		{
			e.printStackTrace();
		}
	}

	// Utility method: so that the application continues to run
	private static void waitForEnterPressed()
	{
		try
		{
			System.out.println("Press  to continue...");
			System.in.read();
		}
		catch (Exception e)
		{
			e.printStackTrace();
		}
	}

	public static void main(String argv[])
	{
		SimpleAgent agent = new SimpleAgent();
		System.out.println("SimpleAgent is running...");
		SimpleAgent.waitForEnterPressed();
	}
}
