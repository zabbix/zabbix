#include <string.h>
#include <stdio.h>

#include "common.h"
#include "log.h"

int	comms_create_request(char *host, char *key, char *data, char *lastlogsize, char *request,int maxlen)
{
	int ret = SUCCEED;
	char host_b64[MAX_STRING_LEN];
	char key_b64[MAX_STRING_LEN];
	char data_b64[ZBX_MAX_B64_LEN];
	char lastlogsize_b64[MAX_STRING_LEN];

	memset(request,0,maxlen);
	memset(host_b64,0,sizeof(host_b64));
	memset(key_b64,0,sizeof(key_b64));
	memset(data_b64,0,sizeof(data_b64));
	memset(lastlogsize_b64,0,sizeof(lastlogsize_b64));

	str_base64_encode(host, host_b64, strlen(host));
	str_base64_encode(key, key_b64, strlen(key));
	str_base64_encode(data, data_b64, strlen(data));
	if(lastlogsize[0]!=0)
	{
		str_base64_encode(lastlogsize, lastlogsize_b64, strlen(lastlogsize));
	}

/*	fprintf(stderr, "Data Base64 [%s]\n", data_b64);*/

	if(lastlogsize[0]==0)
	{
		snprintf(request,maxlen,"<req><host>%s</host><key>%s</key><data>%s</data></req>",host_b64,key_b64,data_b64);
	}
	else
	{
		snprintf(request,maxlen,"<req><host>%s</host><key>%s</key><data>%s</data><lastlogsize>%s</lastlogsize></req>",host_b64,key_b64,data_b64,lastlogsize_b64);
	}
/*	fprintf(stderr, "Max [%d] Result [%s][%d]\n", maxlen , request, strlen(request));*/

	return ret;
}
int	comms_parse_response(char *xml,char *host,char *key, char *data, char *lastlogsize, char *timestamp,
	       char *source, char *severity, int maxlen)
{
	int ret = SUCCEED;
	int i;

	char host_b64[MAX_STRING_LEN];
	char key_b64[MAX_STRING_LEN];
	char data_b64[MAX_STRING_LEN];
	char lastlogsize_b64[MAX_STRING_LEN];
	char timestamp_b64[MAX_STRING_LEN];
	char source_b64[ZBX_MAX_B64_LEN];
	char severity_b64[MAX_STRING_LEN];

	memset(host_b64,0,sizeof(host_b64));
	memset(key_b64,0,sizeof(key_b64));
	memset(data_b64,0,sizeof(data_b64));
	memset(lastlogsize_b64,0,sizeof(lastlogsize_b64));
	memset(timestamp_b64,0,sizeof(timestamp_b64));
	memset(source_b64,0,sizeof(source_b64));
	memset(severity_b64,0,sizeof(severity_b64));

	xml_get_data(xml, "host", host_b64, sizeof(host_b64)-1);
	xml_get_data(xml, "key", key_b64, sizeof(key_b64)-1);
	xml_get_data(xml, "data", data_b64, sizeof(data_b64)-1);
	xml_get_data(xml, "lastlogsize", lastlogsize_b64, sizeof(lastlogsize_b64)-1);
	xml_get_data(xml, "timestamp", timestamp_b64, sizeof(timestamp_b64)-1);
	xml_get_data(xml, "source", source_b64, sizeof(source_b64)-1);
	xml_get_data(xml, "severity", severity_b64, sizeof(severity_b64)-1);

	memset(key,0,maxlen);
	memset(host,0,maxlen);
	memset(data,0,maxlen);
	memset(lastlogsize,0,maxlen);
	memset(timestamp,0,maxlen);
	memset(source,0,maxlen);
	memset(severity,0,maxlen);

	str_base64_decode(host_b64, host, &i);
	str_base64_decode(key_b64, key, &i);
	str_base64_decode(data_b64, data, &i);
	str_base64_decode(lastlogsize_b64, lastlogsize, &i);
	str_base64_decode(timestamp_b64, timestamp, &i);
	str_base64_decode(source_b64, source, &i);
	str_base64_decode(severity_b64, severity, &i);

	return ret;
}

int	zbx_fork()
{
	fflush(stdout);
	fflush(stderr);
	return fork();
}

void	*zbx_malloc(size_t size)
{
	register int max_attempts;
	void *ptr = NULL;

	for(	max_attempts = 10, size = MAX(size, 1);
		max_attempts > 0 && !ptr;
		ptr = malloc(size),
		max_attempts--
	);

	if(ptr)	return ptr;

	zabbix_log(LOG_LEVEL_CRIT,"out of memory. requested '%i' bytes.", size);
	exit(FAIL);

	 /* Program will never reach this point. */
	return ptr;
}
