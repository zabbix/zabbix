package com.zabbix.proxy;

class HelperFunctionChest
{
	public static <T> boolean arrayContains(T[] array, T key)
	{
		for (T element : array)
			if (key.equals(element))
				return true;

		return false;
	}
}
