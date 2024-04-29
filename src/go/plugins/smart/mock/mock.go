package mock

import (
	"fmt"
	"sync"

	"github.com/google/go-cmp/cmp"
)

// MockController allows prepare and validate SmartController.Execute calls.
// Implements the SmartController interface.
type MockController struct {
	mu           sync.Mutex
	count        int
	expectations []*expectation
}

type expectation struct {
	args []string
	out  []byte
	err  error
}

// Execute mock call to the SmartController.Execute method.
func (c *MockController) Execute(args ...string) ([]byte, error) {
	c.mu.Lock()

	defer func() {
		c.count++
		c.mu.Unlock()
	}()

	if c.count >= len(c.expectations) {
		panic("unexpected call to Execute")
	}

	e := c.expectations[c.count]

	if e.err != nil {
		return nil, e.err
	}

	if e.args != nil {
		if diff := cmp.Diff(e.args, args); diff != "" {
			panic(fmt.Errorf("Execute args mismatch (-want +got):\n%s", diff))
		}
	}

	return e.out, nil
}

// ExpectExecute adds a new expectation for the SmartController.Execute method.
func (c *MockController) ExpectExecute() *expectation {
	e := &expectation{}
	c.expectations = append(c.expectations, e)

	return e
}

// ExpectationsWhereMet checks if all expectations defined (expected) where met.
func (c *MockController) ExpectationsWhereMet() error {
	if c.count != len(c.expectations) {
		return fmt.Errorf(
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
