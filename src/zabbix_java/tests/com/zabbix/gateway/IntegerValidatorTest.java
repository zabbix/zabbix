/*
** Copyright (C) 2001-2025 Zabbix SIA
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

package com.zabbix.gateway;

import org.junit.*;
import static org.junit.Assert.*;

public class IntegerValidatorTest
{
	@Test
	public void testCorrectValidation()
	{
		InputValidator validator = new IntegerValidator(3, 7);

		assertFalse(validator.validate(Integer.valueOf(2)));
		assertTrue(validator.validate(Integer.valueOf(3)));
		assertTrue(validator.validate(Integer.valueOf(5)));
		assertTrue(validator.validate(Integer.valueOf(7)));
		assertFalse(validator.validate(Integer.valueOf(8)));
	}

	@Test
	public void testMinimumInterval()
	{
		new IntegerValidator(5, 5);
	}

	@Test(expected = IllegalArgumentException.class)
	public void testInvalidInterval()
	{
		new IntegerValidator(7, 3);
	}
}
