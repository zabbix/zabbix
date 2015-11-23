/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "common.h"
#include "sysinfo.h"
#include "zbxregexp.h"

#if defined(KERNEL_2_4)
#define DEVICE_DIR	"/proc/sys/dev/sensors"
#else
#define DEVICE_DIR	"/sys/class/hwmon"
static char	*locations[] = {"", "/device", NULL};
#endif

#define ATTR_MAX	128

static void	count_sensor(int do_task, const char *filename, double *aggr, int *cnt)
{
	FILE	*f;
	char	line[MAX_STRING_LEN];
	double	value;

	if (NULL == (f = fopen(filename, "r")))
		return;

	if (NULL == fgets(line, sizeof(line), f))
	{
		zbx_fclose(f);
		return;
	}

	zbx_fclose(f);

#if defined(KERNEL_2_4)
	if (1 == sscanf(line, "%*f\t%*f\t%lf\n", &value))
	{
#else
	if (1 == sscanf(line, "%lf", &value))
	{
		if (NULL == strstr(filename, "fan"))
			value = value / 1000;
#endif
		(*cnt)++;

		switch (do_task)
		{
			case ZBX_DO_ONE:
				*aggr = value;
				break;
			case ZBX_DO_AVG:
				*aggr += value;
				break;
			case ZBX_DO_MAX:
				*aggr = (1 == *cnt ? value : MAX(*aggr, value));
				break;
			case ZBX_DO_MIN:
				*aggr = (1 == *cnt ? value : MIN(*aggr, value));
				break;
		}
	}
}

/*********************************************************************************
 *                                                                               *
 * Function: sysfs_read_attr                                                     *
 *                                                                               *
 * Purpose: locate and read the name attribute of a sensor from sysfs            *
 *                                                                               *
 * Parameters:  device        - [IN] the path to sensor data in sysfs            *
 *              attribute     - [OUT] the sensor name                            *
 *                                                                               *
 * Return value: Subfolder where the sensor name file was found or NULL          *
 *                                                                               *
 * Comments: attribute string must be freed by caller after it's been used.      *
 *                                                                               *
 *********************************************************************************/
static const char	*sysfs_read_attr(const char *device, char **attribute)
{
	char	path[MAX_STRING_LEN], buf[ATTR_MAX], *p, **location;
	FILE	*f;

	for (location = locations; NULL != *location; location++)
	{
		zbx_snprintf(path, MAX_STRING_LEN, "%s%s/name", device, *location);

		if (NULL != (f = fopen(path, "r")))
		{
			p = fgets(buf, ATTR_MAX, f);
			zbx_fclose(f);

			if (NULL == p)
				break;

			/* Last byte is a '\n'; chop that off */
			buf[strlen(buf) - 1] = '\0';

			if (NULL != attribute)
				*attribute = zbx_strdup(*attribute, buf);

			return *location;
		}
	}

	return NULL;
}

static int	get_device_info(const char *dev_path, const char *dev_name, char *device_info, const char **name_subfolder)
{
	char		bus_path[MAX_STRING_LEN], linkpath[MAX_STRING_LEN], subsys_path[MAX_STRING_LEN];
	char		*subsys, *prefix = NULL, *bus_attr = NULL;
	const char	*bus_subfolder;
	int		domain, bus, slot, fn, addr, vendor, product, sub_len, ret = FAIL;
	short int	bus_spi, bus_i2c;

	/* ignore any device without name attribute */
	if (NULL == (*name_subfolder = sysfs_read_attr(dev_path, &prefix)))
		goto out;

	if (NULL == dev_name)
	{
		/* Virtual device */
		/* Assuming that virtual devices are unique */
		addr = 0;
		zbx_snprintf(device_info, MAX_STRING_LEN, "%s-virtual-%x", prefix, addr);
		ret = SUCCEED;

		goto out;
	}

	/* Find bus type */
	zbx_snprintf(linkpath, MAX_STRING_LEN, "%s/device/subsystem", dev_path);

	sub_len = readlink(linkpath, subsys_path, MAX_STRING_LEN - 1);

	if (0 > sub_len && ENOENT == errno)
	{
		/* Fallback to "bus" link for kernels <= 2.6.17 */
		zbx_snprintf(linkpath, MAX_STRING_LEN, "%s/device/bus", dev_path);
		sub_len = readlink(linkpath, subsys_path, MAX_STRING_LEN - 1);
	}

	if (0 > sub_len)
	{
		/* Older kernels (<= 2.6.11) have neither the subsystem symlink nor the bus symlink */
		if (errno == ENOENT)
			subsys = NULL;
		else
			goto out;
	}
	else
	{
		subsys_path[sub_len] = '\0';
		subsys = strrchr(subsys_path, '/') + 1;
	}

	if ((NULL == subsys || 0 == strcmp(subsys, "i2c")))
	{
		if (2 != sscanf(dev_name, "%hd-%x", &bus_i2c, &addr))
			goto out;

		/* find out if legacy ISA or not */
		if (9191 == bus_i2c)
		{
			zbx_snprintf(device_info, MAX_STRING_LEN, "%s-isa-%04x", prefix, addr);
		}
		else
		{
			zbx_snprintf(bus_path, sizeof(bus_path), "/sys/class/i2c-adapter/i2c-%d", bus_i2c);
			bus_subfolder = sysfs_read_attr(bus_path, &bus_attr);

			if (NULL != bus_subfolder && '\0' != *bus_subfolder)
			{
				if (0 != strncmp(bus_attr, "ISA ", 4))
					goto out;

				zbx_snprintf(device_info, MAX_STRING_LEN, "%s-isa-%04x", prefix, addr);
			}
			else
				zbx_snprintf(device_info, MAX_STRING_LEN, "%s-i2c-%hd-%02x", prefix, bus_i2c, addr);
		}

		ret = SUCCEED;
	}
	else if (0 == strcmp(subsys, "spi"))
	{
		/* SPI */
		if (2 != sscanf(dev_name, "spi%hd.%d", &bus_spi, &addr))
			goto out;

		zbx_snprintf(device_info, MAX_STRING_LEN, "%s-spi-%hd-%x", prefix, bus_spi, addr);

		ret = SUCCEED;
	}
	else if (0 == strcmp(subsys, "pci"))
	{
		/* PCI */
		if (4 != sscanf(dev_name, "%x:%x:%x.%x", &domain, &bus, &slot, &fn))
			goto out;

		addr = (domain << 16) + (bus << 8) + (slot << 3) + fn;
		zbx_snprintf(device_info, MAX_STRING_LEN, "%s-pci-%04x", prefix, addr);

		ret = SUCCEED;
	}
	else if (0 == strcmp(subsys, "platform") || 0 == strcmp(subsys, "of_platform"))
	{
		/* must be new ISA (platform driver) */
		if (1 != sscanf(dev_name, "%*[a-z0-9_].%d", &addr))
			addr = 0;

		zbx_snprintf(device_info, MAX_STRING_LEN, "%s-isa-%04x", prefix, addr);

		ret = SUCCEED;
	}
	else if (0 == strcmp(subsys, "acpi"))
	{
		/* Assuming that acpi devices are unique */
		addr = 0;
		zbx_snprintf(device_info, MAX_STRING_LEN, "%s-acpi-%x", prefix, addr);

		ret = SUCCEED;
	}
	else if (0 == strcmp(subsys, "hid"))
	{
		/* As of kernel 2.6.32, the hid device names do not look good */
		if (4 != sscanf(dev_name, "%x:%x:%x.%x", &bus, &vendor, &product, &addr))
			goto out;

		zbx_snprintf(device_info, MAX_STRING_LEN, "%s-hid-%hd-%x", prefix, bus, addr);

		ret = SUCCEED;
	}
out:
	zbx_free(bus_attr);
	zbx_free(prefix);

	return ret;
}

static void	get_device_sensors(int do_task, const char *device, const char *name, double *aggr, int *cnt)
{
	char	sensorname[MAX_STRING_LEN];

#if defined(KERNEL_2_4)
	if (ZBX_DO_ONE == do_task)
	{
		zbx_snprintf(sensorname, sizeof(sensorname), "%s/%s/%s", DEVICE_DIR, device, name);
		count_sensor(do_task, sensorname, aggr, cnt);
	}
	else
	{
		DIR		*devicedir = NULL, *sensordir = NULL;
		struct dirent	*deviceent, *sensorent;
		char		devicename[MAX_STRING_LEN];

		if (NULL == (devicedir = opendir(DEVICE_DIR)))
			return;

		while (NULL != (deviceent = readdir(devicedir)))
		{
			if (0 == strcmp(deviceent->d_name, ".") || 0 == strcmp(deviceent->d_name, ".."))
				continue;

			if (NULL == zbx_regexp_match(deviceent->d_name, device, NULL))
				continue;

			zbx_snprintf(devicename, sizeof(devicename), "%s/%s", DEVICE_DIR, deviceent->d_name);

			if (NULL == (sensordir = opendir(devicename)))
				continue;

			while (NULL != (sensorent = readdir(sensordir)))
			{
				if (0 == strcmp(sensorent->d_name, ".") || 0 == strcmp(sensorent->d_name, ".."))
					continue;

				if (NULL == zbx_regexp_match(sensorent->d_name, name, NULL))
					continue;

				zbx_snprintf(sensorname, sizeof(sensorname), "%s/%s", devicename, sensorent->d_name);
				count_sensor(do_task, sensorname, aggr, cnt);
			}
			closedir(sensordir);
		}
		closedir(devicedir);
	}
#else
	DIR		*sensordir = NULL, *devicedir = NULL;
	struct dirent	*sensorent, *deviceent;
	char		hwmon_dir[MAX_STRING_LEN], devicepath[MAX_STRING_LEN], deviced[MAX_STRING_LEN],
			device_info[MAX_STRING_LEN], regex[MAX_STRING_LEN], *device_p;
	const char	*subfolder;
	int		err, dev_len;

	zbx_snprintf(hwmon_dir, sizeof(hwmon_dir), "%s", DEVICE_DIR);

	if (NULL == (devicedir = opendir(hwmon_dir)))
		return;

	while (NULL != (deviceent = readdir(devicedir)))
	{
		if (0 == strcmp(deviceent->d_name, ".") || 0 == strcmp(deviceent->d_name, ".."))
			continue;

		zbx_snprintf(devicepath, sizeof(devicepath), "%s/%s/device", DEVICE_DIR, deviceent->d_name);
		dev_len = readlink(devicepath, deviced, MAX_STRING_LEN - 1);
		zbx_snprintf(devicepath, sizeof(devicepath), "%s/%s", DEVICE_DIR, deviceent->d_name);

		if (0 > dev_len)
		{
			/* No device link? Treat device as virtual */
			err = get_device_info(devicepath, NULL, device_info, &subfolder);
		}
		else
		{
			deviced[dev_len] = '\0';
			device_p = strrchr(deviced, '/') + 1;

			if (0 == strcmp(device, device_p))
			{
				zbx_snprintf(device_info, sizeof(device_info), "%s", device);
				err = (NULL != (subfolder = sysfs_read_attr(devicepath, NULL)) ? SUCCEED : FAIL);
			}
			else
				err = get_device_info(devicepath, device_p, device_info, &subfolder);
		}

		if (SUCCEED == err && 0 == strcmp(device_info, device))
		{
			zbx_snprintf(devicepath, sizeof(devicepath), "%s/%s%s", DEVICE_DIR, deviceent->d_name,
					subfolder);

			if (ZBX_DO_ONE == do_task)
			{
				zbx_snprintf(sensorname, sizeof(sensorname), "%s/%s_input", devicepath, name);
				count_sensor(do_task, sensorname, aggr, cnt);
			}
			else
			{
				zbx_snprintf(regex, sizeof(regex), "%s[0-9]*_input", name);

				if (NULL == (sensordir = opendir(devicepath)))
					return;

				while (NULL != (sensorent = readdir(sensordir)))
				{
					if (0 == strcmp(sensorent->d_name, ".") ||
							0 == strcmp(sensorent->d_name, ".."))
						continue;

					if (NULL == zbx_regexp_match(sensorent->d_name, regex, NULL))
						continue;

					zbx_snprintf(sensorname, sizeof(sensorname), "%s/%s", devicepath,
							sensorent->d_name);
					count_sensor(do_task, sensorname, aggr, cnt);
				}
				closedir(sensordir);
			}
		}
	}
	closedir(devicedir);
#endif
}

int	GET_SENSOR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*device, *name, *function;
	int	do_task, cnt = 0;
	double	aggr = 0;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	device = get_rparam(request, 0);
	name = get_rparam(request, 1);
	function = get_rparam(request, 2);

	if (NULL == device || '\0' == *device)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == name || '\0' == *name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == function || '\0' == *function)
		do_task = ZBX_DO_ONE;
	else if (0 == strcmp(function, "avg"))
		do_task = ZBX_DO_AVG;
	else if (0 == strcmp(function, "max"))
		do_task = ZBX_DO_MAX;
	else if (0 == strcmp(function, "min"))
		do_task = ZBX_DO_MIN;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (ZBX_DO_ONE != do_task && 0 != isdigit(name[strlen(name) - 1]))
		do_task = ZBX_DO_ONE;

	if (ZBX_DO_ONE != do_task && 0 == isalpha(name[strlen(name) - 1]))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Generic sensor name must be specified for selected mode."));
		return SYSINFO_RET_FAIL;
	}

	get_device_sensors(do_task, device, name, &aggr, &cnt);

	if (0 == cnt)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain sensor information."));
		return SYSINFO_RET_FAIL;
	}

	if (ZBX_DO_AVG == do_task)
		SET_DBL_RESULT(result, aggr / cnt);
	else
		SET_DBL_RESULT(result, aggr);

	return SYSINFO_RET_OK;
}
