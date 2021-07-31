/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "string.h"
#include "sysinfo.h"
#include <time.h>
#include <stdlib.h>

#ifdef HAVE_SYS_UTSNAME_H
#	include <sys/utsname.h>
#endif

#include "common.h"

#define CUID_PID_BLOCK_SIZE		2
#define CUID_HOSTNAME_BLOCK_SIZE	2
#define CUID_BLOCK_SIZE			4
#define CUID_BASE_36			36
#define DISCRETE_VALUES			1679616
#define CUID_TIMESTAMP_SIZE		8
#define PID_TMP_36_BASE_BUF_LEN		10
#define HOST_TMP_36_BASE_BUF_LEN	10
#define RAND_TMP_36_BASE_BUF_LEN	10

static char	host_block[HOST_TMP_36_BASE_BUF_LEN];

static void	pad(char *input, size_t pad_size)
{
	size_t	i, input_len;

	input_len = strlen(input);

	if (pad_size > input_len)
	{
		for (i = 0; i < input_len; i++)
			input[i + pad_size - input_len] = input[i];
		memset(input, '0', pad_size-input_len);
	}
	else
	{
		for (i = 0; i < pad_size; i++)
			input[i] = input[i + input_len - pad_size];
	}
	input[pad_size] = '\0';
}

static char	base36_digit(size_t num)
{
	if (num <= 9)
		return (char)(num + '0');

	return (char)(num - 10 + 'a');
}

static void	str_rev(char *str)
{
	size_t	len, i;
	char	temp;

	len = strlen(str);

	for (i = 0; i < len/2; i++)
	{
		temp = str[i];
		str[i] = str[len - i - 1];
		str[len - i - 1] = temp;
	}
}
static void	from_decimal(char *res, size_t base, size_t input_num)
{
	size_t	index = 0;

	if (0 == input_num)
	{
		res[0] = '0';
		res[1] = '\0';
	}
	else
	{
		while (0 < input_num)
		{
			res[index++] = base36_digit(input_num % base);
			input_num /= base;
		}
		res[index] = '\0';
	}

	str_rev(res);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_cuid_init                                                    *
 *                                                                            *
 * Purpose: initializes context for the cuid generation                       *
 *                                                                            *
 ******************************************************************************/
static void	zbx_cuid_init(void)
{
	char		*hostname;
	size_t		hostname_num, hostname_len, i;
	struct utsname	name;

	srand((unsigned int)time(NULL) + (unsigned int)getpid());

	if (-1 == uname(&name))
		hostname = zbx_strdup(NULL, "dummy");
	else
		hostname = zbx_strdup(NULL, name.nodename);

	hostname_len = strlen(hostname);
	hostname_num = hostname_len + CUID_BASE_36;

	for (i = 0; i < hostname_len; i++)
		hostname_num = hostname_num + (size_t)hostname[i];

	from_decimal(host_block, CUID_BASE_36, hostname_num);
	pad(host_block, CUID_HOSTNAME_BLOCK_SIZE);
	zbx_free(hostname);
}

static size_t	next(void)
{
	size_t		out;
	static int	counter_value = -1;

	if (-1 == counter_value)
		zbx_cuid_init();

	counter_value++;
	out = (size_t)counter_value;

	if (counter_value >= DISCRETE_VALUES)
		counter_value = 0;

	return out;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_new_cuid                                                     *
 *                                                                            *
 * Purpose: generates cuid, is based on the go cuid implementation from       *
 *          https://github.com/lucsky/cuid/blob/master/cuid.go                *
 *          consider using mutexes around it if used inside threads           *
 *                                                                            *
 * Parameters: cuid      - [OUT] resulting cuid                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_new_cuid(char *cuid)
{
	char		rand_block_1[RAND_TMP_36_BASE_BUF_LEN + 1], rand_block_2[RAND_TMP_36_BASE_BUF_LEN + 1],
			fingerprint[CUID_BLOCK_SIZE + 1], timestamp[CUID_TIMESTAMP_SIZE + 1],
			counter[CUID_BLOCK_SIZE+1], pid_block[PID_TMP_36_BASE_BUF_LEN];
	struct timeval	current_time;

	from_decimal(counter, CUID_BASE_36, next());
	pad(counter, CUID_BLOCK_SIZE);

	from_decimal(pid_block, CUID_BASE_36, (size_t)getpid());
	pad(pid_block, CUID_PID_BLOCK_SIZE);

	gettimeofday(&current_time, NULL);
	from_decimal(timestamp, CUID_BASE_36, (size_t)(current_time.tv_sec * 1000 + current_time.tv_usec / 1000));

	from_decimal(rand_block_1, CUID_BASE_36, (size_t)rand());
	pad(rand_block_1, CUID_BLOCK_SIZE);

	from_decimal(rand_block_2, CUID_BASE_36, (size_t)rand());
	pad(rand_block_2, CUID_BLOCK_SIZE);

	zbx_snprintf(fingerprint, sizeof(fingerprint), "%s%s", pid_block, host_block);
	zbx_snprintf(cuid, CUID_LEN, "c%s%s%s%s%s", timestamp, counter, fingerprint, rand_block_1, rand_block_2);
}
