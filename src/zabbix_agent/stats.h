#ifndef ZABBIX_STATS_H
#define ZABBIX_STATS_H

#define	MAX_INTERFACE	8

#define INTERFACE struct interface_type
INTERFACE
{
	char    *interface;
	int	clock[60*15];
	float	sent[60*15];
	float	received[60*15];
/*	int	sent_load1;
	int	sent_load5;
	int	sent_load15;
	int	received_total;
	int	received_load1;
	int	received_load5;
	int	received_load15;*/
};

#endif
