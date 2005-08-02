#include "common.h"
#include "log.h"

int	comms_create_request(char *host, char *key, char *data, char *request,int maxlen)
{
	int ret = SUCCEED;
	char host_b64[MAX_STRING_LEN];
	char key_b64[MAX_STRING_LEN];
	char data_b64[MAX_STRING_LEN];

	memset(host_b64,0,sizeof(host_b64));
	memset(key_b64,0,sizeof(key_b64));
	memset(data_b64,0,sizeof(data_b64));

	str_base64_encode(host, host_b64, strlen(host));
	str_base64_encode(key, key_b64, strlen(key));
	str_base64_encode(data, data_b64, strlen(data));

	snprintf(request,maxlen,"<req><host>%s</host><key>%s</key><data>%s</data></req>",host_b64,key_b64,data_b64);

	return ret;
}

int	comms_parse_response(char *xml,char *host,char *key, char *data, int maxlen)
{
	int ret = SUCCEED;
	int i;

	char host_b64[MAX_STRING_LEN];
	char key_b64[MAX_STRING_LEN];
	char data_b64[MAX_STRING_LEN];

	memset(host_b64,0,sizeof(host_b64));
	memset(key_b64,0,sizeof(key_b64));
	memset(data_b64,0,sizeof(data_b64));

	xml_get_data(xml, "host", host_b64, sizeof(host_b64)-1);
	xml_get_data(xml, "key", key_b64, sizeof(key_b64)-1);
	xml_get_data(xml, "data", data_b64, sizeof(data_b64)-1);

	memset(key,0,maxlen);
	memset(host,0,maxlen);
	memset(data,0,maxlen);

	str_base64_decode(host_b64, host, &i);
	str_base64_decode(key_b64, key, &i);
	str_base64_decode(data_b64, data, &i);

	return ret;
}
