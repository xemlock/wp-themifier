<?php

abstract class WPThemifier_TagParser_Abstract
    implements WPThemifier_TagParserInterface
{
    public function tagStop(array $token)
    {
        return $token['type'] === WPThemifier_Token::TYPE_TAG_END
            && $token['tag'] === $this->getTag();
    }

    public function checkName($name)
    {
        $name = (string) $name;
        if (!preg_match('#^[_a-z][_a-z0-9]*$#i', $name)) {
            throw new Exception('Invalid variable name: ', $name);
        }
        return $name;
    }
}
