/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "telemetry.h"
#include "zbxtypes.h"
#include "zbxalgo.h"

ZBX_VECTOR_IMPL(tq_formula_node_ptr, tq_formula_node_t *);

typedef struct
{
	const char	*p;
	const char	*err_pos;
}
tq_formula_parse_ctx_t;

static tq_formula_node_t	*tq_formula_node_init(tq_formula_node_type_t	type)
{
	tq_formula_node_t	*node = zbx_malloc(NULL, sizeof(tq_formula_node_t));

	node->type = type;

	if (TQ_FORMULA_NODE_TYPE_LEAF != type)
		zbx_vector_tq_formula_node_ptr_create(&node->children);

	return node;
}

void	tq_formula_node_free(tq_formula_node_t *node)
{
	if (TQ_FORMULA_NODE_TYPE_LEAF != node->type)
	{
		for (int i = 0; i < node->children.values_num; i++)
			tq_formula_node_free(node->children.values[i]);

		zbx_vector_tq_formula_node_ptr_destroy(&node->children);
	}

	zbx_free(node);
}

static int	is_op_delim(char c)
{
	return ' ' == c || '(' == c || '\r' == c || '\n' == c || '\t' == c || ')' == c || '\0' == c ? SUCCEED : FAIL;
}

static int	is_whitespace(char c)
{
	return ' ' == c || '\r' == c || '\n' == c || '\t' == c ? SUCCEED : FAIL;
}

static void	skip_whitespace(tq_formula_parse_ctx_t *ctx)
{
	while (SUCCEED == is_whitespace(*ctx->p))
		ctx->p++;
}

static tq_formula_node_t	*parse_or(tq_formula_parse_ctx_t *ctx);

static tq_formula_node_t	*parse_leaf(tq_formula_parse_ctx_t *ctx)
{
	tq_formula_node_t	*node;
	int			len;

	skip_whitespace(ctx);

	if (!isupper((unsigned char)*ctx->p))
	{
		ctx->err_pos = ctx->p;
		return NULL;
	}

	len = 0;

	while (isupper((unsigned char)ctx->p[len]))
		len++;

	node = tq_formula_node_init(TQ_FORMULA_NODE_TYPE_LEAF);
	node->condition_idx = tq_formula_constant_to_condition_idx(ctx->p, len);

	ctx->p += len;

	return node;
}

static tq_formula_node_t	*parse_atom(tq_formula_parse_ctx_t *ctx)
{
	skip_whitespace(ctx);

	if ('(' == *ctx->p)
	{
		tq_formula_node_t	*node;

		ctx->p++;

		if (NULL == (node = parse_or(ctx)))
			return NULL;

		skip_whitespace(ctx);

		if (')' != *ctx->p)
		{
			ctx->err_pos = ctx->p;
			tq_formula_node_free(node);
			return NULL;
		}

		ctx->p++;

		return node;
	}
	else
		return parse_leaf(ctx);
}

static tq_formula_node_t	*parse_not(tq_formula_parse_ctx_t *ctx)
{
	skip_whitespace(ctx);

	if ('n' == ctx->p[0] && 'o' == ctx->p[1] && 't' == ctx->p[2] && SUCCEED == is_op_delim(ctx->p[3]))
	{
		tq_formula_node_t	*node, *atom;

		ctx->p += 3;

		if (NULL == (atom = parse_atom(ctx)))
			return NULL;

		node = tq_formula_node_init(TQ_FORMULA_NODE_TYPE_NOT);

		zbx_vector_tq_formula_node_ptr_append(&node->children, atom);

		return node;
	}
	else
	{
		tq_formula_node_t	*atom;

		if (NULL == (atom = parse_atom(ctx)))
			return NULL;

		return atom;
	}
}

static tq_formula_node_t	*parse_and(tq_formula_parse_ctx_t *ctx)
{
	tq_formula_node_t	*node, *child;

	node = tq_formula_node_init(TQ_FORMULA_NODE_TYPE_AND);

	if (NULL == (child = parse_not(ctx)))
		goto fail;

	zbx_vector_tq_formula_node_ptr_append(&node->children, child);

	skip_whitespace(ctx);

	while ('a' == ctx->p[0] && 'n' == ctx->p[1] && 'd' == ctx->p[2] && SUCCEED == is_op_delim(ctx->p[3]))
	{
		ctx->p += 3;

		if (NULL == (child = parse_not(ctx)))
			goto fail;

		zbx_vector_tq_formula_node_ptr_append(&node->children, child);

		skip_whitespace(ctx);
	}

	/* collapse single-child and-node */
	if (1 == node->children.values_num)
	{
		node->children.values_num--;
		tq_formula_node_free(node);
		node = child; /* child is already set */
	}

	return node;
fail:
	tq_formula_node_free(node);

	return NULL;
}

static tq_formula_node_t	*parse_or(tq_formula_parse_ctx_t *ctx)
{
	tq_formula_node_t	*node, *child;

	node = tq_formula_node_init(TQ_FORMULA_NODE_TYPE_OR);

	if (NULL == (child = parse_and(ctx)))
		goto fail;

	zbx_vector_tq_formula_node_ptr_append(&node->children, child);

	skip_whitespace(ctx);

	while ('o' == ctx->p[0] && 'r' == ctx->p[1] && SUCCEED == is_op_delim(ctx->p[2]))
	{
		ctx->p += 2;

		if (NULL == (child = parse_and(ctx)))
			goto fail;

		zbx_vector_tq_formula_node_ptr_append(&node->children, child);

		skip_whitespace(ctx);
	}

	/* collapse single-child or-node */
	if (1 == node->children.values_num)
	{
		node->children.values_num--;
		tq_formula_node_free(node);
		node = child; /* child is already set */
	}

	return node;
fail:
	tq_formula_node_free(node);

	return NULL;
}

tq_formula_node_t	*tq_formula_parse(const char *formula, const char **err_pos)
{
	tq_formula_node_t	*node;

	tq_formula_parse_ctx_t	ctx = {
		.p 		= formula,
		.err_pos	= NULL,
	};

	if (NULL == (node = parse_or(&ctx)))
	{
		*err_pos = ctx.err_pos;
		return NULL;
	}

	skip_whitespace(&ctx);

	if ('\0' != *ctx.p)
	{
		tq_formula_node_free(node);
		*err_pos = ctx.p;
		return NULL;
	}

	return node;
}
