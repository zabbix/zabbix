#ifndef ZABBIX_EXPRESSIONS_EVALUATE_H
#define ZABBIX_EXPRESSIONS_EVALUATE_H

#include "common.h"
#include "module.h"

typedef struct
{
	char		*expression;
  	zbx_variant_t	value;
	char		*error;
}
zbx_expressions_evaluate_result_t;


#endif /* ZABBIX_EXPRESSIONS_EVALUATE_H */

