package com.zabbix.proxy;

import java.net.InetAddress;
import java.net.ServerSocket;
import java.util.concurrent.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class JavaProxy
{
	private static final Logger logger = LoggerFactory.getLogger(JavaProxy.class);

	public static void main(String[] args)
	{
		logger.info("Zabbix Java Proxy has started");

		try
		{
			ConfigurationManager.parseConfiguration();

			InetAddress listenIP = (InetAddress)ConfigurationManager.getParameter(ConfigurationManager.LISTEN_IP).getValue();
			int listenPort = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.LISTEN_PORT);

			ServerSocket socket = new ServerSocket(listenPort, 0, listenIP);
			logger.info("listening on {}:{}", socket.getInetAddress(), socket.getLocalPort());

			int startPollers = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.START_POLLERS);
			logger.debug("creating a thread pool of {} pollers", startPollers);
			ExecutorService threadPool = new ThreadPoolExecutor(
					startPollers,
					startPollers,
					30L, TimeUnit.SECONDS,
					new ArrayBlockingQueue<Runnable>(startPollers),
					new ThreadPoolExecutor.CallerRunsPolicy());

			while (true)
				threadPool.execute(new SocketProcessor(socket.accept()));
		}
		catch (Exception e)
		{
			logger.error("caught fatal exception", e);
		}

		logger.info("Zabbix Java Proxy has stopped");
	}
}
