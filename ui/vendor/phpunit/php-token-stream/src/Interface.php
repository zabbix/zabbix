<?php declare(strict_types=1);
/*
 * This file is part of phpunit/php-token-stream.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class PHP_Token_INTERFACE extends PHP_TokenWithScopeAndVisibility
{
    /**
     * @var array
     */
    protected $interfaces;

    /**
     * @return string
     */
    public function getName()
    {
        return (string) $this->tokenStream[$this->id + 2];
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return $this->tokenStream[$this->id + 4] instanceof PHP_Token_EXTENDS;
    }

    /**
     * @return array
     */
    public function getPackage()
    {
        $result = [
            'namespace'   => '',
            'fullPackage' => '',
            'category'    => '',
            'package'     => '',
            'subpackage'  => '',
        ];

        $docComment = $this->getDocblock();
        $className  = $this->getName();

        for ($i = $this->id; $i; --$i) {
            if ($this->tokenStream[$i] instanceof PHP_Token_NAMESPACE) {
                $result['namespace'] = $this->tokenStream[$i]->getName();

                break;
            }
        }

        if ($docComment === null) {
            return $result;
        }

        if (\preg_match('/@category[\s]+([\.\w]+)/', $docComment, $matches)) {
            $result['category'] = $matches[1];
        }

        if (\preg_match('/@package[\s]+([\.\w]+)/', $docComment, $matches)) {
            $result['package']     = $matches[1];
            $result['fullPackage'] = $matches[1];
        }

        if (\preg_match('/@subpackage[\s]+([\.\w]+)/', $docComment, $matches)) {
            $result['subpackage']   = $matches[1];
            $result['fullPackage'] .= '.' . $matches[1];
        }

        if (empty($result['fullPackage'])) {
            $result['fullPackage'] = $this->arrayToName(
                \explode('_', \str_replace('\\', '_', $className)),
                '.'
            );
        }

        return $result;
    }

    /**
     * @return bool|string
     */
    public function getParent()
    {
        if (!$this->hasParent()) {
            return false;
        }

        $i         = $this->id + 6;
        $tokens    = $this->tokenStream->tokens();
        $className = (string) $tokens[$i];

        while (isset($tokens[$i + 1]) &&
            !$tokens[$i + 1] instanceof PHP_Token_WHITESPACE) {
            $className .= (string) $tokens[++$i];
        }

        return $className;
    }

    /**
     * @return bool
     */
    public function hasInterfaces()
    {
        return (isset($this->tokenStream[$this->id + 4]) &&
                $this->tokenStream[$this->id + 4] instanceof PHP_Token_IMPLEMENTS) ||
            (isset($this->tokenStream[$this->id + 8]) &&
                $this->tokenStream[$this->id + 8] instanceof PHP_Token_IMPLEMENTS);
    }

    /**
     * @return array|bool
     */
    public function getInterfaces()
    {
        if ($this->interfaces !== null) {
            return $this->interfaces;
        }

        if (!$this->hasInterfaces()) {
            return $this->interfaces = false;
        }

        if ($this->tokenStream[$this->id + 4] instanceof PHP_Token_IMPLEMENTS) {
            $i = $this->id + 3;
        } else {
            $i = $this->id + 7;
        }

        $tokens = $this->tokenStream->tokens();

        while (!$tokens[$i + 1] instanceof PHP_Token_OPEN_CURLY) {
            $i++;

            if ($tokens[$i] instanceof PHP_Token_STRING) {
                $this->interfaces[] = (string) $tokens[$i];
            }
        }

        return $this->interfaces;
    }

    /**
     * @param string $join
     *
     * @return string
     */
    protected function arrayToName(array $parts, $join = '\\')
    {
        $result = '';

        if (\count($parts) > 1) {
            \array_pop($parts);

            $result = \implode($join, $parts);
        }

        return $result;
    }
}
