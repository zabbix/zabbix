#ifndef MON_FUNCTIONS_H
#define MON_FUNCTIONS_H

#include "db.h"

void    update_triggers (int flag,int sucker_num,int lastclock);
int	get_lastvalue(float *Result,char *host,char *key,char *function,char *parameter);
int	process_data(char *server,char *key, double value);
void	process_new_value(DB_ITEM *item,double value);

#endif
