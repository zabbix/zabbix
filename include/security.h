#ifndef ZABBIX_SECURITY_H
#define ZABBIX_SECURITY_H

int	check_security(int sockfd, char *ip_list, int allow_if_empty);

#endif
