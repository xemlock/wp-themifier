<?php

abstract class WPThemifier_TagParser_Abstract implements WPThemifier_TagParserInterface
{
    public function tagStop(array $token)
    {
        return $token['type'] === WPThemifier_Token::TYPE_TAG_END
            && $token['tag'] === $this->getTag();
    }
}
