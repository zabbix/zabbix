<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class PHP_Token_Stream implements ArrayAccess, Countable, SeekableIterator
{
    /**
     * @var array<string, class-string<PHP_Token>>
     */
    protected static $customTokens = [
        '(' => PHP_Token_OPEN_BRACKET::class,
        ')' => PHP_Token_CLOSE_BRACKET::class,
        '[' => PHP_Token_OPEN_SQUARE::class,
        ']' => PHP_Token_CLOSE_SQUARE::class,
        '{' => PHP_Token_OPEN_CURLY::class,
        '}' => PHP_Token_CLOSE_CURLY::class,
        ';' => PHP_Token_SEMICOLON::class,
        '.' => PHP_Token_DOT::class,
        ',' => PHP_Token_COMMA::class,
        '=' => PHP_Token_EQUAL::class,
        '<' => PHP_Token_LT::class,
        '>' => PHP_Token_GT::class,
        '+' => PHP_Token_PLUS::class,
        '-' => PHP_Token_MINUS::class,
        '*' => PHP_Token_MULT::class,
        '/' => PHP_Token_DIV::class,
        '?' => PHP_Token_QUESTION_MARK::class,
        '!' => PHP_Token_EXCLAMATION_MARK::class,
        ':' => PHP_Token_COLON::class,
        '"' => PHP_Token_DOUBLE_QUOTES::class,
        '@' => PHP_Token_AT::class,
        '&' => PHP_Token_AMPERSAND::class,
        '%' => PHP_Token_PERCENT::class,
        '|' => PHP_Token_PIPE::class,
        '$' => PHP_Token_DOLLAR::class,
        '^' => PHP_Token_CARET::class,
        '~' => PHP_Token_TILDE::class,
        '`' => PHP_Token_BACKTICK::class,
    ];

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var array
     */
    protected $tokens = [];

    /**
     * @var array
     */
    protected $tokensByLine = [];

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @var array
     */
    protected $linesOfCode = ['loc' => 0, 'cloc' => 0, 'ncloc' => 0];

    /**
     * @var array
     */
    protected $classes;

    /**
     * @var array
     */
    protected $functions;

    /**
     * @var array
     */
    protected $includes;

    /**
     * @var array
     */
    protected $interfaces;

    /**
     * @var array
     */
    protected $traits;

    /**
     * @var array
     */
    protected $lineToFunctionMap = [];

    /**
     * Constructor.
     *
     * @param string $sourceCode
     */
    public function __construct($sourceCode)
    {
        if (\is_file($sourceCode)) {
            $this->filename = $sourceCode;
            $sourceCode     = \file_get_contents($sourceCode);
        }

        $this->scan($sourceCode);
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        $this->tokens       = [];
        $this->tokensByLine = [];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $buffer = '';

        foreach ($this as $token) {
            $buffer .= $token;
        }

        return $buffer;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return int
     */
    public function count()
    {
        return \count($this->tokens);
    }

    /**
     * @return PHP_Token[]
     */
    public function tokens()
    {
        return $this->tokens;
    }

    /**
     * @return array
     */
    public function getClasses()
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $this->parse();

        return $this->classes;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        if ($this->functions !== null) {
            return $this->functions;
        }

        $this->parse();

        return $this->functions;
    }

    /**
     * @return array
     */
    public function getInterfaces()
    {
        if ($this->interfaces !== null) {
            return $this->interfaces;
        }

        $this->parse();

        return $this->interfaces;
    }

    /**
     * @return array
     */
    public function getTraits()
    {
        if ($this->traits !== null) {
            return $this->traits;
        }

        $this->parse();

        return $this->traits;
    }

    /**
     * Gets the names of all files that have been included
     * using include(), include_once(), require() or require_once().
     *
     * Parameter $categorize set to TRUE causing this function to return a
     * multi-dimensional array with categories in the keys of the first dimension
     * and constants and their values in the second dimension.
     *
     * Parameter $category allow to filter following specific inclusion type
     *
     * @param bool   $categorize OPTIONAL
     * @param string $category   OPTIONAL Either 'require_once', 'require',
     *                           'include_once', 'include'
     *
     * @return array
     */
    public function getIncludes($categorize = false, $category = null)
    {
        if ($this->includes === null) {
            $this->includes = [
                'require_once' => [],
                'require'      => [],
                'include_once' => [],
                'include'      => [],
            ];

            foreach ($this->tokens as $token) {
                switch (\get_class($token)) {
                    case PHP_Token_REQUIRE_ONCE::class:
                    case PHP_Token_REQUIRE::class:
                    case PHP_Token_INCLUDE_ONCE::class:
                    case PHP_Token_INCLUDE::class:
                        $this->includes[$token->getType()][] = $token->getName();

                        break;
                }
            }
        }

        if (isset($this->includes[$category])) {
            $includes = $this->includes[$category];
        } elseif ($categorize === false) {
            $includes = \array_merge(
                $this->includes['require_once'],
                $this->includes['require'],
                $this->includes['include_once'],
                $this->includes['include']
            );
        } else {
            $includes = $this->includes;
        }

        return $includes;
    }

    /**
     * Returns the name of the function or method a line belongs to.
     *
     * @return string or null if the line is not in a function or method
     */
    public function getFunctionForLine($line)
    {
        $this->parse();

        if (isset($this->lineToFunctionMap[$line])) {
            return $this->lineToFunctionMap[$line];
        }
    }

    /**
     * @return array
     */
    public function getLinesOfCode()
    {
        return $this->linesOfCode;
    }

    public function rewind()/*: void*/
    {
        $this->position = 0;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return isset($this->tokens[$this->position]);
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @return PHP_Token
     */
    public function current()
    {
        return $this->tokens[$this->position];
    }

    public function next()/*: void*/
    {
        $this->position++;
    }

    /**
     * @param int $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->tokens[$offset]);
    }

    /**
     * @param int $offset
     *
     * @throws OutOfBoundsException
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException(
                \sprintf(
                    'No token at position "%s"',
                    $offset
                )
            );
        }

        return $this->tokens[$offset];
    }

    /**
     * @param int $offset
     */
    public function offsetSet($offset, $value)/*: void*/
    {
        $this->tokens[$offset] = $value;
    }

    /**
     * @param int $offset
     *
     * @throws OutOfBoundsException
     */
    public function offsetUnset($offset)/*: void*/
    {
        if (!$this->offsetExists($offset)) {
            throw new OutOfBoundsException(
                \sprintf(
                    'No token at position "%s"',
                    $offset
                )
            );
        }

        unset($this->tokens[$offset]);
    }

    /**
     * Seek to an absolute position.
     *
     * @param int $position
     *
     * @throws OutOfBoundsException
     */
    public function seek($position)/*: void*/
    {
        $this->position = $position;

        if (!$this->valid()) {
            throw new OutOfBoundsException(
                \sprintf(
                    'No token at position "%s"',
                    $this->position
                )
            );
        }
    }

    /**
     * Scans the source for sequences of characters and converts them into a
     * stream of tokens.
     *
     * @param string $sourceCode
     */
    protected function scan($sourceCode)/*: void*/
    {
        $id        = 0;
        $line      = 1;
        $tokens    = \token_get_all($sourceCode);
        $numTokens = \count($tokens);

        $lastNonWhitespaceTokenWasDoubleColon = false;

        $name = null;

        for ($i = 0; $i < $numTokens; ++$i) {
            $token = $tokens[$i];
            $skip  = 0;

            if (\is_array($token)) {
                $name = \substr(\token_name($token[0]), 2);
                $text = $token[1];

                if ($lastNonWhitespaceTokenWasDoubleColon && $name == 'CLASS') {
                    $name = 'CLASS_NAME_CONSTANT';
                } elseif ($name == 'USE' && isset($tokens[$i + 2][0]) && $tokens[$i + 2][0] == \T_FUNCTION) {
                    $name = 'USE_FUNCTION';
                    $text .= $tokens[$i + 1][1] . $tokens[$i + 2][1];
                    $skip = 2;
                }

                /** @var class-string<PHP_Token> $tokenClass */
                $tokenClass = 'PHP_Token_' . $name;
            } else {
                $text       = $token;
                $tokenClass = self::$customTokens[$token];
            }

            /*
             * @see https://github.com/sebastianbergmann/php-token-stream/issues/95
             */
            if (PHP_MAJOR_VERSION >= 8 &&
                $name === 'WHITESPACE' &&                                  // Current token is T_WHITESPACE
                isset($this->tokens[$id - 1]) &&                           // Current token is not the first token
                $this->tokens[$id - 1] instanceof PHP_Token_COMMENT &&     // Previous token is T_COMMENT
                strpos((string) $this->tokens[$id - 1], '/*') === false && // Previous token is comment that starts with '#' or '//'
                strpos($text, "\n") === 0                                  // Text of current token begins with newline
            ) {
                $this->tokens[$id - 1] = new PHP_Token_COMMENT(
                    $this->tokens[$id - 1] . "\n",
                    $this->tokens[$id - 1]->getLine(),
                    $this,
                    $id - 1
                );

                $text = substr($text, 1);

                $line++;

                if (empty($text)) {
                    continue;
                }
            }

            if (!isset($this->tokensByLine[$line])) {
                $this->tokensByLine[$line] = [];
            }

            $token = new $tokenClass($text, $line, $this, $id++);

            $this->tokens[]              = $token;
            $this->tokensByLine[$line][] = $token;

            $line += \substr_count($text, "\n");

            if ($tokenClass == PHP_Token_HALT_COMPILER::class) {
                break;
            }

            if ($name == 'DOUBLE_COLON') {
                $lastNonWhitespaceTokenWasDoubleColon = true;
            } elseif ($name != 'WHITESPACE') {
                $lastNonWhitespaceTokenWasDoubleColon = false;
            }

            $i += $skip;
        }

        foreach ($this->tokens as $token) {
            if (!$token instanceof PHP_Token_COMMENT && !$token instanceof PHP_Token_DOC_COMMENT) {
                continue;
            }

            foreach ($this->tokensByLine[$token->getLine()] as $_token) {
                if (!$_token instanceof PHP_Token_COMMENT && !$_token instanceof PHP_Token_DOC_COMMENT && !$_token instanceof PHP_Token_WHITESPACE) {
                    continue 2;
                }
            }

            $this->linesOfCode['cloc'] += max(1, \substr_count((string) $token, "\n"));
        }

        $this->linesOfCode['loc']   = \substr_count($sourceCode, "\n");
        $this->linesOfCode['ncloc'] = $this->linesOfCode['loc'] -
                                      $this->linesOfCode['cloc'];
    }

    protected function parse()/*: void*/
    {
        $this->interfaces = [];
        $this->classes    = [];
        $this->traits     = [];
        $this->functions  = [];
        $class            = [];
        $classEndLine     = [];
        $trait            = false;
        $traitEndLine     = false;
        $interface        = false;
        $interfaceEndLine = false;

        foreach ($this->tokens as $token) {
            switch (\get_class($token)) {
                case PHP_Token_HALT_COMPILER::class:
                    return;

                case PHP_Token_INTERFACE::class:
                    $interface        = $token->getName();
                    $interfaceEndLine = $token->getEndLine();

                    $this->interfaces[$interface] = [
                        'methods'   => [],
                        'parent'    => $token->getParent(),
                        'keywords'  => $token->getKeywords(),
                        'docblock'  => $token->getDocblock(),
                        'startLine' => $token->getLine(),
                        'endLine'   => $interfaceEndLine,
                        'package'   => $token->getPackage(),
                        'file'      => $this->filename,
                    ];

                    break;

                case PHP_Token_CLASS::class:
                case PHP_Token_TRAIT::class:
                    $tmp = [
                        'methods'   => [],
                        'parent'    => $token->getParent(),
                        'interfaces'=> $token->getInterfaces(),
                        'keywords'  => $token->getKeywords(),
                        'docblock'  => $token->getDocblock(),
                        'startLine' => $token->getLine(),
                        'endLine'   => $token->getEndLine(),
                        'package'   => $token->getPackage(),
                        'file'      => $this->filename,
                    ];

                    if ($token instanceof PHP_Token_CLASS) {
                        $class[]        = $token->getName();
                        $classEndLine[] = $token->getEndLine();

                        if ($token->getName() !== null) {
                            $this->classes[$class[\count($class) - 1]] = $tmp;
                        }
                    } else {
                        $trait                = $token->getName();
                        $traitEndLine         = $token->getEndLine();
                        $this->traits[$trait] = $tmp;
                    }

                    break;

                case PHP_Token_FUNCTION::class:
                    $name = $token->getName();
                    $tmp  = [
                        'docblock'  => $token->getDocblock(),
                        'keywords'  => $token->getKeywords(),
                        'visibility'=> $token->getVisibility(),
                        'signature' => $token->getSignature(),
                        'startLine' => $token->getLine(),
                        'endLine'   => $token->getEndLine(),
                        'ccn'       => $token->getCCN(),
                        'file'      => $this->filename,
                    ];

                    if (empty($class) &&
                        $trait === false &&
                        $interface === false) {
                        $this->functions[$name] = $tmp;

                        $this->addFunctionToMap(
                            $name,
                            $tmp['startLine'],
                            $tmp['endLine']
                        );
                    } elseif (!empty($class)) {
                        if ($class[\count($class) - 1] !== null) {
                            $this->classes[$class[\count($class) - 1]]['methods'][$name] = $tmp;

                            $this->addFunctionToMap(
                                $class[\count($class) - 1] . '::' . $name,
                                $tmp['startLine'],
                                $tmp['endLine']
                            );
                        }
                    } elseif ($trait !== false) {
                        $this->traits[$trait]['methods'][$name] = $tmp;

                        $this->addFunctionToMap(
                            $trait . '::' . $name,
                            $tmp['startLine'],
                            $tmp['endLine']
                        );
                    } else {
                        $this->interfaces[$interface]['methods'][$name] = $tmp;
                    }

                    break;

                case PHP_Token_CLOSE_CURLY::class:
                    if (!empty($classEndLine) &&
                        $classEndLine[\count($classEndLine) - 1] == $token->getLine()) {
                        \array_pop($classEndLine);
                        \array_pop($class);
                    } elseif ($traitEndLine !== false &&
                        $traitEndLine == $token->getLine()) {
                        $trait        = false;
                        $traitEndLine = false;
                    } elseif ($interfaceEndLine !== false &&
                        $interfaceEndLine == $token->getLine()) {
                        $interface        = false;
                        $interfaceEndLine = false;
                    }

                    break;
            }
        }
    }

    /**
     * @param string $name
     * @param int    $startLine
     * @param int    $endLine
     */
    private function addFunctionToMap($name, $startLine, $endLine): void
    {
        for ($line = $startLine; $line <= $endLine; $line++) {
            $this->lineToFunctionMap[$line] = $name;
        }
    }
}
