#ifndef MON_EXPRESSION_H
#define MON_EXPRESSION_H

int	find_char(char *str,char c);
int	substitute_functions(char *exp);
int	substitute_macros(char *exp);
int     evaluate_expression (int *result,char *expression);

#endif
