<?php

class WPThemifier_VarEnv
{
    /**
     * @var WPThemifier_VarScope[]
     */
    protected $_scopes = array();

    /**
     * @return WPThemifier_VarScope|null
     */
    public function getCurrentScope()
    {
        if (empty($this->_scopes)) {
            throw new Exception('No scope is present');
        }
        return end($this->_scopes);
    }

    /**
     * @param  array $vars OPTIONAL
     * @return WPThemifier_VarScope
     */
    public function pushScope(array $vars = array())
    {
        return $this->_scopes[] = new WPThemifier_VarScope($vars);
    }

    /**
     * @return WPThemifier_VarScope|null
     */
    public function popScope()
    {
        if (empty($this->_scopes)) {
            throw new Exception('No scope to pop');
        }
        return array_pop($this->_scopes);
    }
}
