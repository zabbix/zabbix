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

package mock

import (
	"fmt"
	"sync"
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/errs"
)

// MockController allows prepare and validate SmartController.Execute calls.
// Implements the SmartController interface.
type MockController struct {
	mu           sync.Mutex
	count        int
	expectations []*expectation
	t            *testing.T
}

type expectation struct {
	args []string
	out  []byte
	err  error
}

// NewMockController creates a new MockController.
func NewMockController(t *testing.T) *MockController {
	t.Helper()

	return &MockController{t: t}
}

// Execute mock call to the SmartController.Execute method.
func (c *MockController) Execute(args ...string) ([]byte, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if c.count >= len(c.expectations) {
		if c.t != nil {
			c.t.Fatalf("Unexpected call to Execute with args: %v", args)

			return nil, nil
		}

		panic(fmt.Sprintf("unexpected call to Execute with args: %v", args))
	}

	defer func() { c.count++ }()

	e := c.expectations[c.count]

	if e.err != nil {
		return nil, e.err
	}

	if e.args != nil {
		if diff := cmp.Diff(e.args, args); diff != "" {
			if c.t != nil {
				c.t.Fatalf("Execute args mismatch (-want +got):\n%s", diff)

				return nil, nil
			}

			panic("Execute args mismatch (-want +got):\n" + diff)
		}
	}

	return e.out, nil
}

// ExpectExecute adds a new expectation for the SmartController.Execute method.
func (c *MockController) ExpectExecute() *expectation { //nolint:revive
	e := &expectation{}
	c.expectations = append(c.expectations, e)

	return e
}

// ExpectationsWhereMet checks if all expectations defined (expected) where met.
func (c *MockController) ExpectationsWhereMet() error {
	if c.count != len(c.expectations) {
		return errs.Errorf(
			"not all expectations were met, expected %d, got %d",
			len(c.expectations), c.count,
		)
	}

	return nil
}

// WithArgs sets the expected arguments.
func (e *expectation) WithArgs(args ...string) *expectation {
	e.args = args

	return e
}

// WillReturnError sets the expected error return.
func (e *expectation) WillReturnError(err error) *expectation {
	e.err = err

	return e
}

// WillReturnOutput sets the expected output return.
func (e *expectation) WillReturnOutput(out []byte) *expectation {
	e.out = out

	return e
}
