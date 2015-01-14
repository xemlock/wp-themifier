<?php

class WPThemifier_TokenStream
{
    protected $_tokens;
    protected $_pos;

    public function __construct(array $tokens)
    {
        $this->_tokens = array_values($tokens);
        $this->_pos = -1;
    }

    public function next()
    {
        if (isset($this->_tokens[$this->_pos + 1])) {
            return $this->_tokens[++$this->_pos];
        }
        return null;
    }

    public function peek($offset = 1)
    {
        if (isset($this->_tokens[$this->_pos + $offset])) {
            return $this->_tokens[$this->_pos + $offset];
        }
        return null;
    }

    public function nextIf($type, array $match = array())
    {
        if (null !== ($next = $this->peek())) {
            if ($next['type'] === $type &&
                (empty($match) || (array_intersect_key($next, $match) == $match))
            ) {
                return $this->next();
            }
        }
        return null;
    }

    public function hasNext()
    {
        return isset($this->_tokens[$this->_pos + 1]);
    }

    public function current()
    {
        return $this->_pos > 0 ? $this->_tokens[$this->_pos] : null;
    }
}
