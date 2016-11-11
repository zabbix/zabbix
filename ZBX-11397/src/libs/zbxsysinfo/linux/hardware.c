/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "../common/common.h"
#include "sysinfo.h"
#include <sys/mman.h>
#include "zbxalgo.h"
#include "hardware.h"
#include "zbxregexp.h"

/******************************************************************************
 *                                                                            *
 * Comments: read the string #num from dmi data into a buffer                 *
 *                                                                            *
 ******************************************************************************/
static size_t	get_dmi_string(char *buf, int bufsize, unsigned char *data, int num)
{
	char	*c = (char *)data;

	if (0 == num)
		return 0;

	c += data[1];	/* skip to string data */

	while (1 < num)
	{
		c += strlen(c);
		c++;
		num--;
	}

	return zbx_snprintf(buf, bufsize, " %s", c);
}

static size_t	get_chassis_type(char *buf, int bufsize, int type)
{
	/* from System Management BIOS (SMBIOS) Reference Specification v2.7.1 */
	static const char	*chassis_types[] =
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
		"Blade Enclosure",	/* 0x1d (MAX_CHASSIS_TYPE) */
	};

	type = CHASSIS_TYPE_BITS & type;

	if (1 > type || MAX_CHASSIS_TYPE < type)
		return 0;

	return zbx_snprintf(buf, bufsize, " %s", chassis_types[type]);
}

static int	get_dmi_info(char *buf, int bufsize, int flags)
{
	int		ret = SYSINFO_RET_FAIL, fd, offset = 0;
	unsigned char	membuf[SMBIOS_ENTRY_POINT_SIZE], *smbuf = NULL, *data;
	size_t		len, fp;
	void		*mmp = NULL;
	static long	pagesize = 0;
	static int	smbios_status = SMBIOS_STATUS_UNKNOWN;
	static size_t	smbios_len, smbios;	/* length and address of SMBIOS table (if found) */

	if (-1 != (fd = open(SYS_TABLE_FILE, O_RDONLY)))
	{
		if (-1 == (smbios_len = lseek(fd, 0, SEEK_END)))
			goto close;

		lseek(fd, 0, SEEK_SET);
		smbuf = zbx_malloc(NULL, smbios_len);
		smbios_len = read(fd, smbuf, smbios_len);
	}
	else if (-1 != (fd = open(DEV_MEM, O_RDONLY)))
	{
		if (SMBIOS_STATUS_UNKNOWN == smbios_status)	/* look for SMBIOS table only once */
		{
			pagesize = sysconf(_SC_PAGESIZE);

			/* find smbios entry point - located between 0xF0000 and 0xFFFFF (according to the specs) */
			for (fp = 0xf0000; 0xfffff > fp; fp += 16)
			{
				memset(membuf, 0, sizeof(membuf));

				len = fp % pagesize;	/* mmp needs to be a multiple of pagesize for munmap */
				if (MAP_FAILED == (mmp = mmap(0, len + SMBIOS_ENTRY_POINT_SIZE, PROT_READ, MAP_SHARED, fd, fp - len)))
					goto close;

				memcpy(membuf, (char *)mmp + len, sizeof(membuf));
				munmap(mmp, len + SMBIOS_ENTRY_POINT_SIZE);

				if (0 == strncmp((char *)membuf, "_DMI_", 5))	/* entry point found */
				{
					smbios_len = membuf[7] << 8 | membuf[6];
					smbios = (size_t)membuf[11] << 24 | (size_t)membuf[10] << 16 | (size_t)membuf[9] << 8 | membuf[8];
					smbios_status = SMBIOS_STATUS_OK;
					break;
				}
			}
		}

		if (SMBIOS_STATUS_OK != smbios_status)
		{
			smbios_status = SMBIOS_STATUS_ERROR;
			goto close;
		}

		smbuf = zbx_malloc(smbuf, smbios_len);

		len = smbios % pagesize;	/* mmp needs to be a multiple of pagesize for munmap */
		if (MAP_FAILED == (mmp = mmap(0, len + smbios_len, PROT_READ, MAP_SHARED, fd, smbios - len)))
			goto clean;

		memcpy(smbuf, (char *)mmp + len, smbios_len);
		munmap(mmp, len + smbios_len);
	}
	else
		return ret;

	data = smbuf;
	while (data + DMI_HEADER_SIZE <= smbuf + smbios_len)
	{
		if (1 == data[0])	/* system information */
		{
			if (0 != (flags & DMI_GET_VENDOR))
			{
				offset += get_dmi_string(buf + offset, bufsize - offset, data, data[4]);
				flags &= ~DMI_GET_VENDOR;
			}

			if (0 != (flags & DMI_GET_MODEL))
			{
				offset += get_dmi_string(buf + offset, bufsize - offset, data, data[5]);
				flags &= ~DMI_GET_MODEL;
			}

			if (0 != (flags & DMI_GET_SERIAL))
			{
				offset += get_dmi_string(buf + offset, bufsize - offset, data, data[7]);
				flags &= ~DMI_GET_SERIAL;
			}
		}
		else if (3 == data[0] && 0 != (flags & DMI_GET_TYPE))	/* chassis */
		{
			offset += get_chassis_type(buf + offset, bufsize - offset, data[5]);
			flags &= ~DMI_GET_TYPE;
		}

		if (0 == flags)
			break;

		data += data[1];			/* skip the main data */
		while (0 != data[0] || 0 != data[1])	/* string data ends with two nulls */
		{
			data++;
		}
		data += 2;
	}

	if (0 < offset)
		ret = SYSINFO_RET_OK;
clean:
	zbx_free(smbuf);
close:
	close(fd);

	return ret;
}

int	SYSTEM_HW_CHASSIS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode, buf[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;

	if (1 < request->nparam)
		return ret;

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "full"))	/* show full info by default */
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_TYPE | DMI_GET_VENDOR | DMI_GET_MODEL | DMI_GET_SERIAL);
	else if (0 == strcmp(mode, "type"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_TYPE);
	else if (0 == strcmp(mode, "vendor"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_VENDOR);
	else if (0 == strcmp(mode, "model"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_MODEL);
	else if (0 == strcmp(mode, "serial"))
		ret = get_dmi_info(buf, sizeof(buf), DMI_GET_SERIAL);

	if (SYSINFO_RET_OK == ret)
		SET_STR_RESULT(result, zbx_strdup(NULL, buf + 1));	/* buf has a leading space */

	return ret;
}

static zbx_uint64_t	get_cpu_max_freq(int cpu_num)
{
	zbx_uint64_t	freq = FAIL;
	char		filename[MAX_STRING_LEN];
	FILE		*f;

	zbx_snprintf(filename, sizeof(filename), CPU_MAX_FREQ_FILE, cpu_num);

	f = fopen(filename, "r");

	if (NULL != f)
	{
		if (1 != fscanf(f, ZBX_FS_UI64, &freq))
			freq = FAIL;

		fclose(f);
	}

	return freq;
}

static size_t	print_freq(char *buffer, size_t size, int filter, int cpu, zbx_uint64_t maxfreq, zbx_uint64_t curfreq)
{
	size_t	offset = 0;

	if (HW_CPU_SHOW_MAXFREQ == filter && FAIL != maxfreq)
	{
		if (HW_CPU_ALL_CPUS == cpu)
			offset += zbx_snprintf(buffer + offset, size - offset, " " ZBX_FS_UI64 "MHz", maxfreq / 1000);
		else
			offset += zbx_snprintf(buffer + offset, size - offset, " " ZBX_FS_UI64, maxfreq * 1000);
	}
	else if (HW_CPU_SHOW_CURFREQ == filter && FAIL != curfreq)
	{
		if (HW_CPU_ALL_CPUS == cpu)
			offset += zbx_snprintf(buffer + offset, size - offset, " " ZBX_FS_UI64 "MHz", curfreq);
		else
			offset += zbx_snprintf(buffer + offset, size - offset, " " ZBX_FS_UI64, curfreq * 1000000);
	}
	else if (HW_CPU_SHOW_ALL == filter)
	{
		if (FAIL != curfreq)
			offset += zbx_snprintf(buffer + offset, size - offset, " working at " ZBX_FS_UI64 "MHz", curfreq);

		if (FAIL != maxfreq)
			offset += zbx_snprintf(buffer + offset, size - offset, " (maximum " ZBX_FS_UI64 "MHz)", maxfreq / 1000);
	}

	return offset;
}

int     SYSTEM_HW_CPU(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL, filter, cpu, cur_cpu = -1, offset = 0;
	zbx_uint64_t	maxfreq = FAIL, curfreq = FAIL;
	char		line[MAX_STRING_LEN], name[MAX_STRING_LEN], tmp[MAX_STRING_LEN], buffer[MAX_BUFFER_LEN], *param;
	FILE		*f;

	if (2 < request->nparam)
		return ret;

	param = get_rparam(request, 0);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "all"))
		cpu = HW_CPU_ALL_CPUS;	/* show all CPUs by default */
	else if (FAIL == is_uint31(param, (uint32_t*)&cpu))
		return ret;

	param = get_rparam(request, 1);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "full"))
		filter = HW_CPU_SHOW_ALL;	/* show full info by default */
	else if (0 == strcmp(param, "maxfreq"))
		filter = HW_CPU_SHOW_MAXFREQ;
	else if (0 == strcmp(param, "vendor"))
		filter = HW_CPU_SHOW_VENDOR;
	else if (0 == strcmp(param, "model"))
		filter = HW_CPU_SHOW_MODEL;
	else if (0 == strcmp(param, "curfreq"))
		filter = HW_CPU_SHOW_CURFREQ;
	else
		return ret;

	if (NULL == (f = fopen(HW_CPU_INFO_FILE, "r")))
		return ret;

	*buffer = '\0';

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (2 != sscanf(line, "%[^:]: %[^\n]", name, tmp))
			continue;

		if (0 == strncmp(name, "processor", 9))
		{
			if (-1 != cur_cpu && (HW_CPU_ALL_CPUS == cpu || cpu == cur_cpu))	/* print info about the previous cpu */
				offset += print_freq(buffer + offset, sizeof(buffer) - offset, filter, cpu, maxfreq, curfreq);

			curfreq = FAIL;
			cur_cpu = atoi(tmp);

			if (HW_CPU_ALL_CPUS != cpu && cpu != cur_cpu)
				continue;

			if (HW_CPU_ALL_CPUS == cpu || HW_CPU_SHOW_ALL == filter)
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\nprocessor %d:", cur_cpu);

			if ((HW_CPU_SHOW_ALL == filter || HW_CPU_SHOW_MAXFREQ == filter) &&
					FAIL != (maxfreq = get_cpu_max_freq(cur_cpu)))
			{
				ret = SYSINFO_RET_OK;
			}
		}

		if (HW_CPU_ALL_CPUS != cpu && cpu != cur_cpu)
			continue;

		if (0 == strncmp(name, "vendor_id", 9) && (HW_CPU_SHOW_ALL == filter || HW_CPU_SHOW_VENDOR == filter))
		{
			ret = SYSINFO_RET_OK;
			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", tmp);
		}
		else if (0 == strncmp(name, "model name", 10) && (HW_CPU_SHOW_ALL == filter || HW_CPU_SHOW_MODEL == filter))
		{
			ret = SYSINFO_RET_OK;
			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", tmp);
		}
		else if (0 == strncmp(name, "cpu MHz", 7) && (HW_CPU_SHOW_ALL == filter || HW_CPU_SHOW_CURFREQ == filter))
		{
			ret = SYSINFO_RET_OK;
			sscanf(tmp, ZBX_FS_UI64, &curfreq);
		}
	}

	zbx_fclose(f);

	if (SYSINFO_RET_OK == ret)
	{
		if (-1 != cur_cpu && (HW_CPU_ALL_CPUS == cpu || cpu == cur_cpu))	/* print info about the last cpu */
			offset += print_freq(buffer + offset, sizeof(buffer) - offset, filter, cpu, maxfreq, curfreq);

		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer + 1));	/* buf has a leading space or '\n' */
	}

	return ret;
}

int	SYSTEM_HW_DEVICES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*type;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "pci"))
		return EXECUTE_STR("lspci", result);	/* list PCI devices by default */
	else if (0 == strcmp(type, "usb"))
		return EXECUTE_STR("lsusb", result);

	return SYSINFO_RET_FAIL;
}

int     SYSTEM_HW_MACADDR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	size_t			offset;
	int			ret = SYSINFO_RET_FAIL, s, i, show_names;
	char			*format, *p, *regex, address[MAX_STRING_LEN], buffer[MAX_STRING_LEN];
	struct ifreq		*ifr;
	struct ifconf		ifc;
	zbx_vector_str_t	addresses;

	if (2 < request->nparam)
		return ret;

	regex = get_rparam(request, 0);
	format = get_rparam(request, 1);

	if (NULL == format || '\0' == *format || 0 == strcmp(format, "full"))
		show_names = 1;	/* show interface names */
	else if (0 == strcmp(format, "short"))
		show_names = 0;
	else
		return ret;

	if (-1 == (s = socket(AF_INET, SOCK_DGRAM, 0)))
		return ret;

	/* get the interface list */
	ifc.ifc_len = sizeof(buffer);
	ifc.ifc_buf = buffer;
	if (-1 == ioctl(s, SIOCGIFCONF, &ifc))
		goto close;
	ifr = ifc.ifc_req;

	ret = SYSINFO_RET_OK;
	zbx_vector_str_create(&addresses);
	zbx_vector_str_reserve(&addresses, 8);

	/* go through the list */
	for (i = ifc.ifc_len / sizeof(struct ifreq); 0 < i--; ifr++)
	{
		if (NULL != regex && '\0' != *regex && NULL == zbx_regexp_match(ifr->ifr_name, regex, NULL))
			continue;

		if (-1 != ioctl(s, SIOCGIFFLAGS, ifr) &&		/* get the interface */
				0 == (ifr->ifr_flags & IFF_LOOPBACK) &&	/* skip loopback interface */
				-1 != ioctl(s, SIOCGIFHWADDR, ifr))	/* get the MAC address */
		{
			offset = 0;

			if (1 == show_names)
				offset += zbx_snprintf(address + offset, sizeof(address) - offset, "[%s  ", ifr->ifr_name);

			zbx_snprintf(address + offset, sizeof(address) - offset, "%.2hx:%.2hx:%.2hx:%.2hx:%.2hx:%.2hx",
					(unsigned short int)(unsigned char)ifr->ifr_hwaddr.sa_data[0],
					(unsigned short int)(unsigned char)ifr->ifr_hwaddr.sa_data[1],
					(unsigned short int)(unsigned char)ifr->ifr_hwaddr.sa_data[2],
					(unsigned short int)(unsigned char)ifr->ifr_hwaddr.sa_data[3],
					(unsigned short int)(unsigned char)ifr->ifr_hwaddr.sa_data[4],
					(unsigned short int)(unsigned char)ifr->ifr_hwaddr.sa_data[5]);

			if (0 == show_names && FAIL != zbx_vector_str_search(&addresses, address, ZBX_DEFAULT_STR_COMPARE_FUNC))
				continue;

			zbx_vector_str_append(&addresses, zbx_strdup(NULL, address));
		}
	}

	offset = 0;

	if (0 != addresses.values_num)
	{
		zbx_vector_str_sort(&addresses, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (i = 0; i < addresses.values_num; i++)
		{
			if (1 == show_names && NULL != (p = strchr(addresses.values[i], ' ')))
				*p = ']';

			offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%s, ", addresses.values[i]);
			zbx_free(addresses.values[i]);
		}

		offset -= 2;
	}

	buffer[offset] = '\0';

	if (SYSINFO_RET_OK == ret)
		SET_STR_RESULT(result, zbx_strdup(NULL, buffer));

	zbx_vector_str_destroy(&addresses);
close:
	close(s);

	return ret;
}
