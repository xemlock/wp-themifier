<?php

class WPThemifier_TagParser_Comment implements WPThemifier_TagParserInterface
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        // consume tokens until a closing tag is encountered
        $stream = $themifier->getStream();
        while ($token = $stream->next()) {
            if ($token['type'] === WPThemifier_Token::TYPE_TAG_END &&
                $token['tag'] === 'comment'
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
