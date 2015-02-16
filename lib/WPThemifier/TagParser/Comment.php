<?php

class WPThemifier_TagParser_Comment extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $stream = $themifier->getStream();

        while ($token = $stream->next()) {
            // handle nested comments
            if ($token['type'] === WPThemifier_Token::TYPE_TAG_START &&
                $token['tag'] === $this->getTag()
            ) {
                $this->parse($token, $themifier);
            }

            if ($token['type'] === WPThemifier_Token::TYPE_TAG_END &&
                $token['tag'] === $this->getTag()
            ) {
                break;
            }
        }
    }

    public function getTag()
    {
        return 'comment';
    }
}
