package com.zabbix.proxy;

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
