package com.zabbix.proxy;

import java.net.InetAddress;
import java.net.ServerSocket;
import java.util.concurrent.*;

public class JavaProxy
{
	public static void main(String[] args)
	{
		try
		{
			ConfigurationManager.parseConfiguration();

			InetAddress listenIP = (InetAddress)ConfigurationManager.getParameter(ConfigurationManager.LISTEN_IP).getValue();
			int listenPort = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.LISTEN_PORT);

			ServerSocket socket = new ServerSocket(listenPort, 0, listenIP);

			int startPollers = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.START_POLLERS);

			ExecutorService threadPool = new ThreadPoolExecutor(
					startPollers,
					startPollers,
					30L, TimeUnit.SECONDS,
					new ArrayBlockingQueue<Runnable>(startPollers),
					new ThreadPoolExecutor.CallerRunsPolicy());

			while (true)
				threadPool.execute(new RequestProcessor(socket.accept()));
		}
		catch (Exception exception)
		{
			exception.printStackTrace();
		}
	}
}
