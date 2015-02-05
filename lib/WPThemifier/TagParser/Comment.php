<?php

class WPThemifier_TagParser_Comment extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $themifier->read(array($this, 'tagStop'));
    }

    public function getTag()
    {
        return 'comment';
    }
}
