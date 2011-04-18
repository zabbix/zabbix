/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include <sys/mman.h>

#define DMI_GET_TYPE	0x01
#define DMI_GET_VENDOR	0x02
#define DMI_GET_MODEL	0x04
#define DMI_GET_SERIAL	0x08

/******************************************************************************
 *                                                                            *
 * Comments: read the string #num from data into buf                          *
 *                                                                            *
 ******************************************************************************/
static int	set_dmi_string(char *buf, int bufsize, unsigned char *data, int num)
{
	char	*c = (char *)data;

	if (0 == num)
		return SYSINFO_RET_FAIL;

	c += data[1];	/* skip to string data */

	while (1 < num)
	{
		c += strlen(c);
		c++;
		num--;
	}

	zbx_strlcpy(buf, c, bufsize);

	return SYSINFO_RET_OK;
}

static int	set_chassis_type(char *buf, int bufsize, int type)
{
#define CHASSIS_TYPE_BITS	0x7f	/* bits 0-6 represent the chassis type */
#define MAX_CHASSIS_TYPE	0x1d

	/* from System Management BIOS (SMBIOS) Reference Specification v2.7.1 */
	static const char 	*chassis_type[] =
	{
		"",			/* 0x00 */
		"Other",
		"Unknown",
		"Desktop",
		"Low Profile Desktop",
		"Pizza Box",
		"Mini Tower",
		"Tower",
		"Portable",
		"LapTop",
		"Notebook",
		"Hand Held",
		"Docking Station",
		"All in One",
		"Sub Notebook",
		"Space-saving",
		"Lunch Box",
		"Main Server Chassis",
		"Expansion Chassis",
		"SubChassis",
		"Bus Expansion Chassis",
		"Peripheral Chassis",
		"RAID Chassis",
		"Rack Mount Chassis",
		"Sealed-case PC",
		"Multi-system chassis",
		"Compact PCI",
		"Advanced TCA",
		"Blade",
		"Blade Enclosure",	/* 0x1d */
	};

	type = CHASSIS_TYPE_BITS & type;

	if (1 > type || MAX_CHASSIS_TYPE < type)
		return SYSINFO_RET_FAIL;

	zbx_strlcpy(buf, chassis_type[type], bufsize);

	return SYSINFO_RET_OK;
}

static int	get_dmi_info(char *buf, int bufsize, int flags)
{
#define DEV_MEM			"/dev/mem"
#define SMBIOS_ENTRY_POINT_SIZE	0x20
#define DMI_HEADER_SIZE		4
	int			ret = SYSINFO_RET_FAIL, fd;
	unsigned char		membuf[SMBIOS_ENTRY_POINT_SIZE], *smbuf = NULL, *data;
	size_t			len, fp, smbios_len, smbios = 0;
	void			*mmp = NULL;

	if (-1 == (fd = open(DEV_MEM, O_RDONLY)))
		return ret;

	/* find smbios entry point - located between 0xF0000 and 0xFFFFF (according to the specs) */
	for (fp = 0xf0000; 0xfffff > fp; fp += 16)
	{
		memset(membuf, 0, sizeof(membuf));

		len = fp % getpagesize(); /* mmp needs to be a multiple of pagesize for munmap */
		if (MAP_FAILED == (mmp = mmap(0, len + SMBIOS_ENTRY_POINT_SIZE, PROT_READ, MAP_SHARED, fd, fp - len)))
			goto close;

		memcpy(membuf, mmp + len, sizeof(membuf));
		munmap(mmp, len + SMBIOS_ENTRY_POINT_SIZE);

		if (0 == strncmp(membuf, "_DMI_", 5)) /* entry point found */
		{
			smbios_len = membuf[7] << 8 | membuf[6];
			smbios = membuf[11] << 24 | membuf[10] << 16 | membuf[9] << 8 | membuf[8];
			break;
		}
	}

	if (0 == smbios) /* smbios points to the SMBIOS table if present */
		goto close;

	smbuf = zbx_malloc(smbuf, smbios_len);

	len = smbios % getpagesize(); /* mmp needs to be a multiple of pagesize for munmap */
	if (MAP_FAILED == (mmp = mmap(0, len + smbios_len, PROT_READ, MAP_SHARED, fd, smbios - len)))
		goto clean;

	memcpy(smbuf, mmp + len, smbios_len);
	munmap(mmp, len + smbios_len);

	data = smbuf;
	while (data + DMI_HEADER_SIZE <= smbuf + smbios_len)
	{
		if (1 == data[0]) /* system information */
		{
			if (0 != (flags & DMI_GET_VENDOR))
				ret = set_dmi_string(buf, bufsize, data, data[4]);
			else if (0 != (flags & DMI_GET_MODEL))
				ret = set_dmi_string(buf, bufsize, data, data[5]);
			else if (0 != (flags & DMI_GET_SERIAL))
				ret = set_dmi_string(buf, bufsize, data, data[7]);

			if (SYSINFO_RET_OK == ret)
				break;
		}
		else if (3 == data[0] && 0 != (flags & DMI_GET_TYPE)) /* chassis */
		{
			if (SYSINFO_RET_OK == (ret = set_chassis_type(buf, bufsize, data[5])))
				break;
		}

		data += data[1]; /* skip the main data */
		while (data[0] || data[1]) /* string data ends with two nulls */
		{
			data++;
		}
		data += 2;
	}
clean:
	zbx_free(smbuf);
close:
	close(fd);

	return ret;
}

int	SYSTEM_HW_CHASSIS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[8], buf[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "type")) /* show chassis type by default */
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_TYPE);
	else if (0 == strcmp(tmp, "vendor"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_VENDOR);
	else if (0 == strcmp(tmp, "model"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_MODEL);
	else if (0 == strcmp(tmp, "serial"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_SERIAL);

	if (SYSINFO_RET_OK == ret)
		SET_STR_RESULT(result, zbx_strdup(NULL, buf));

	return ret;
}

static int	get_cpu_max_speed(int cpu_num)
{
#define CPU_MAX_FREQ_FILE	"/sys/devices/system/cpu/cpu%d/cpufreq/cpuinfo_max_freq"
	int			result = -1;
	char			filename[MAX_STRING_LEN];
	FILE			*f;

	zbx_snprintf(filename, sizeof(filename), CPU_MAX_FREQ_FILE, cpu_num);

	f = fopen(filename, "r");

	if (NULL != f)
	{
		if (1 != fscanf(f, "%d", &result))
			result = -1;

		fclose(f);
	}

	return result;
}

int     SYSTEM_HW_CPU(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define HW_CPU_FILE		"/proc/cpuinfo"
#define HW_CPU_ALL_CPUS		0x01
#define HW_CPU_FULL_INFO	0x02
#define HW_CPU_MAXSPEED		0x04
#define HW_CPU_VENDOR		0x08
#define HW_CPU_MODEL		0x10
#define HW_CPU_CURSPEED		0x20
#define HW_CPU_CORES		0x40
	int			ret = SYSINFO_RET_FAIL, val, offset = 0, curcpu = -1, cpu = -2, show = 0;
	char			line[MAX_STRING_LEN], name[MAX_STRING_LEN], tmp[MAX_STRING_LEN], buf[MAX_BUFFER_LEN];
	FILE			*f;

	if (2 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		show |= HW_CPU_ALL_CPUS; /* show all CPUs by default */
	else if (FAIL == is_uint(tmp))
		return ret;
	else
		cpu = atoi(tmp);

	if (0 != get_param(param, 2, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "full"))
		show |= HW_CPU_FULL_INFO; /* show full info by default */
	else if (0 == strcmp(tmp, "maxspeed"))
		show |= HW_CPU_MAXSPEED;
	else if (0 == strcmp(tmp, "vendor"))
		show |= HW_CPU_VENDOR;
	else if (0 == strcmp(tmp, "model"))
		show |= HW_CPU_MODEL;
	else if (0 == strcmp(tmp, "curspeed"))
		show |= HW_CPU_CURSPEED;
	else if (0 == strcmp(tmp, "cores"))
		show |= HW_CPU_CORES;
	else
		return ret;

	if (NULL == (f = fopen(HW_CPU_FILE, "r")))
		return ret;

	*buf = '\0';

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (2 != sscanf(line, "%[^:]: %[^\n]", name, tmp))
			continue;

		if (0 == strncmp(name, "processor", 9))
		{
			val = atoi(tmp);
			curcpu = val;

			if (0 == (show & HW_CPU_ALL_CPUS) && cpu != curcpu)
				continue;

			if (0 != (show & (HW_CPU_ALL_CPUS | HW_CPU_FULL_INFO)))
				offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "\nprocessor %d:", curcpu);

			if (0 != (show & (HW_CPU_MAXSPEED | HW_CPU_FULL_INFO)) && -1 != (val = get_cpu_max_speed(curcpu)))
				offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, " %dMHz", val / 1000);
		}

		if (0 == (show & HW_CPU_ALL_CPUS) && cpu != curcpu)
			continue;

		if (0 == strncmp(name, "vendor_id", 9) && 0 != (show & (HW_CPU_VENDOR | HW_CPU_FULL_INFO)))
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, " %s", tmp);
		else if (0 == strncmp(name, "model name", 10) && 0 != (show & (HW_CPU_MODEL | HW_CPU_FULL_INFO)))
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, " %s", tmp);
		else if (0 == strncmp(name, "cpu MHz", 7) && 0 != (show & (HW_CPU_CURSPEED | HW_CPU_FULL_INFO)))
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, " %dMHz", atoi(tmp));
		else if (0 == strncmp(name, "cpu cores", 9) && 0 != (show & (HW_CPU_CORES | HW_CPU_FULL_INFO)))
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, " %s", tmp);

	}
	zbx_fclose(f);

	if (0 < offset)
	{
		ret = SYSINFO_RET_OK;
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buf + 1)); /* first symbol is a space or '\n' */
	}

	return ret;
}

int	SYSTEM_HW_DEVICES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL, offset;
	char	tmp[MAX_STRING_LEN], buffer[MAX_BUFFER_LEN];
	FILE	*f;

	if (1 < num_param(param))
		return ret;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "pci"))
		zbx_strlcpy(tmp, "lspci 2>/dev/null", sizeof(tmp)); /* list PCI devices by default */
	else if (0 == strcmp(tmp, "usb"))
		zbx_strlcpy(tmp, "lsusb 2>/dev/null", sizeof(tmp));
	else
		return ret;

	if (NULL == (f = popen(tmp, "r")))
		return ret;

	if (1 < (offset = fread(buffer, 1, sizeof(buffer), f)))
	{
		buffer[offset - 1] = '\0'; /* remove '\n' */
		ret = SYSINFO_RET_OK;
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
	}

	return ret;
}

int     SYSTEM_HW_MACADDR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define HW_MACADDR_LIST	0x01
#define HW_MACADDR_FULL	0x02
	int		ret = SYSINFO_RET_FAIL, offset = 0, s, i, show = 0;
	char		tmp[MAX_STRING_LEN], buf[MAX_STRING_LEN], buffer[MAX_STRING_LEN];
	struct ifreq	*ifr;
	struct ifconf	ifc;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)) || '\0' == *tmp || 0 == strcmp(tmp, "list"))
		show |= HW_MACADDR_LIST;
	else if (0 == strcmp(tmp, "full"))
		show |= HW_MACADDR_FULL;
	/* else show MAC address only for tmp interface */

	if (-1 == (s = socket(AF_INET, SOCK_DGRAM, 0)))
		return ret;

	/* get the interface list */
	ifc.ifc_len = sizeof(buf);
	ifc.ifc_buf = buf;
	ioctl(s, SIOCGIFCONF, &ifc);
	ifr = ifc.ifc_req;

	/* go through the list */
	for (i = ifc.ifc_len / sizeof(struct ifreq); 0 < i--; ifr++)
	{
		if (0 == (show & (HW_MACADDR_FULL | HW_MACADDR_LIST)) && 0 != strcmp(tmp, ifr->ifr_name))
			continue;

		if (0 == ioctl(s, SIOCGIFFLAGS, ifr) &&			/* get the interface */
				0 == (ifr->ifr_flags & IFF_LOOPBACK) &&	/* skip loopback interface */
				0 == ioctl(s, SIOCGIFHWADDR, ifr))	/* get the MAC address */
		{
			if (0 != (show & HW_MACADDR_FULL))
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s:", ifr->ifr_name);

			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%.2x:%.2x:%.2x:%.2x:%.2x:%.2x, ",
				(unsigned char)ifr->ifr_hwaddr.sa_data[0],
				(unsigned char)ifr->ifr_hwaddr.sa_data[1],
				(unsigned char)ifr->ifr_hwaddr.sa_data[2],
				(unsigned char)ifr->ifr_hwaddr.sa_data[3],
				(unsigned char)ifr->ifr_hwaddr.sa_data[4],
				(unsigned char)ifr->ifr_hwaddr.sa_data[5]);
		}
	}

	close(s);

	if (0 < offset)
	{
		buffer[offset - 2] = '\0'; /* remove ", " */
		ret = SYSINFO_RET_OK;
		SET_STR_RESULT(result, zbx_strdup(NULL, buffer));
	}

	return ret;
}
