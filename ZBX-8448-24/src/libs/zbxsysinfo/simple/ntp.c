/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "comms.h"
#include "log.h"
#include "ntp.h"

#define NTP_SCALE  4294967296.0        /* 2^32, of course! */

#define NTP_PACKET_MIN       48        /* Without authentication */
#define NTP_PACKET_MAX       68        /* With authentication (ignored) */
#define NTP_DISP_FIELD        8        /* Offset of dispersion field */
#define NTP_REFERENCE        16        /* Offset of reference timestamp */
#define NTP_ORIGINATE        24        /* Offset of originate timestamp */
#define NTP_RECEIVE          32        /* Offset of receive timestamp */
#define NTP_TRANSMIT         40        /* Offset of transmit timestamp */

#define NTP_LI_FUDGE          0        /* The current 'status' */
#define NTP_VERSION           3        /* The current version */
#define NTP_VERSION_MAX       4        /* The maximum valid version */
#define NTP_STRATUM          15        /* The current stratum as a server */
#define NTP_STRATUM_MAX      15        /* The maximum valid stratum */
#define NTP_POLLING           8        /* The current 'polling interval' */
#define NTP_PRECISION         0        /* The current 'precision' - 1 sec. */

#define NTP_ACTIVE            1        /* NTP symmetric active request */
#define NTP_PASSIVE           2        /* NTP symmetric passive response */
#define NTP_CLIENT            3        /* NTP client request */
#define NTP_SERVER            4        /* NTP server response */
#define NTP_BROADCAST         5        /* NTP server broadcast */

#define NTP_INSANITY     3600.0        /* Errors beyond this are hopeless */
#define RESET_MIN            15        /* Minimum period between resets */
#define ABSCISSA            3.0        /* Scale factor for standard errors */

typedef struct
{
	unsigned char
		status,
		version,
		mode,
		stratum,
		polling,
		precision;
	double
		dispersion,
		reference,
		originate,
		receive,
		transmit,
		current;
}
ntp_data;

static void	make_packet(ntp_data *data)
{
	data->status	= NTP_LI_FUDGE << 6;
	data->stratum	= NTP_STRATUM;
	data->reference = data->dispersion = 0.0;

	data->version	= NTP_VERSION;
	data->mode	= 1;
	data->polling	= NTP_POLLING;
	data->precision	= NTP_PRECISION;
	data->receive	= data->originate = 0.0;
	data->current	= data->transmit = zbx_current_time();
}

static void	pack_ntp(unsigned char *packet, int length, ntp_data *data)
{
	/* Pack the essential data into an NTP packet, bypassing struct layout and
	endian problems.  Note that it ignores fields irrelevant to SNTP. */

	int	i, k;
	double	d;

	assert(length >= (NTP_TRANSMIT + 8));

	memset(packet, 0, (size_t)length);

	packet[0] = (data->status << 6) | (data->version << 3) | data->mode;
	packet[1] = data->stratum;
	packet[2] = data->polling;
	packet[3] = data->precision;

	d = data->originate / NTP_SCALE;
	for (i = 0; i < 8; i++)
	{
		if ((k = (int)(d *= 256.0)) >= 256)
			k = 255;
		packet[NTP_ORIGINATE + i] = k;
		d -= k;
	}

	d = data->receive / NTP_SCALE;
	for (i = 0; i < 8; i++)
	{
		if ((k = (int)(d *= 256.0)) >= 256)
			k = 255;
		packet[NTP_RECEIVE + i] = k;
		d -= k;
	}

	d = data->transmit / NTP_SCALE;
	for (i = 0; i < 8; i++)
	{
		if ((k = (int)(d *= 256.0)) >= 256)
			k = 255;
		packet[NTP_TRANSMIT + i] = k;
		d -= k;
	}
}

static void	unpack_ntp(ntp_data *data, unsigned char *packet, int length)
{
	/* Unpack the essential data from an NTP packet, bypassing struct layout and
	endian problems.  Note that it ignores fields irrelevant to SNTP. */

	int	i;
	double	d;

	memset(data, 0, sizeof(ntp_data));

	if (0 == length)
		return;

	assert(length >= (NTP_TRANSMIT + 8));

	data->current	= zbx_current_time();    /* best to come first */
	data->status	= (packet[0] >> 6);
	data->version	= (packet[0] >> 3) & 0x07;
	data->mode	= packet[0] & 0x07;
	data->stratum	= packet[1];
	data->polling	= packet[2];
	data->precision	= packet[3];

	d = 0.0;
	for (i = 0; i < 4; i++)
		d = 256.0 * d + packet[NTP_DISP_FIELD + i];
	data->dispersion = d / 65536.0;

	d = 0.0;
	for (i = 0; i < 8; i++)
		d = 256.0 * d + packet[NTP_REFERENCE + i];
	data->reference = d / NTP_SCALE;

	d = 0.0;
	for (i = 0; i < 8; i++)
		d = 256.0 * d + packet[NTP_ORIGINATE + i];
	data->originate = d / NTP_SCALE;

	d = 0.0;
	for (i = 0; i < 8; i++)
		d = 256.0 * d + packet[NTP_RECEIVE + i];
	data->receive = d / NTP_SCALE;

	d = 0.0;
	for (i = 0; i < 8; i++)
		d = 256.0 * d + packet[NTP_TRANSMIT + i];
	data->transmit = d / NTP_SCALE;
}

int	check_ntp(char *host, unsigned short port, int timeout, int *value_int)
{
	zbx_sock_t	s;
	int		ret;
	char		packet[NTP_PACKET_MIN];
	ntp_data	data;

	*value_int = 0;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout)))
	{
		make_packet(&data);

		pack_ntp((unsigned char *)packet, sizeof(packet), &data);

		if (SUCCEED == (ret = zbx_tcp_send_raw(&s, packet)))
		{
			if (SUCCEED == (ret = zbx_tcp_recv(&s)))
			{
				unpack_ntp(&data, (unsigned char *)s.buffer, (int)strlen(s.buffer));
				*value_int = (0 < data.receive ? (int)(data.receive - ZBX_JAN_1970_IN_SEC) : 0);
			}
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "NTP check error: %s", zbx_tcp_strerror());

	return SYSINFO_RET_OK;
}
