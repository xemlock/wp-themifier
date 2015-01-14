<?php

class WPThemifier_TagParser_If extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        if (empty($token['attrs']['test'])) {
            throw new Exception('test attribute missing');
        }

        $ifTrue = $themifier->parse(array($this, 'tagStop'));

        $stream = $themifier->getStream();
        $next = $stream->peek();

        if ($next['type'] === WPThemifier_Token::TYPE_STRING &&
            trim($next['value']) === ''
        ) {
            $stream->next(); // discard empty string
            $next = $stream->peek();
        }

        if ($next['type'] === WPThemifier_Token::TYPE_TAG_START &&
            $next['tag'] === 'else'
        ) {
            $stream->next(); // discard <wp:else> tag
            $ifFalse = $themifier->parse(array($this, 'elseTagStop'));
        }

        // FIXME test expression validation
        $code = '<?php if (' . $token['attrs']['test'] . '): ?>' . "\n" . rtrim($ifTrue) . "\n";

        if (isset($ifFalse)) {
            $code .= '<?php else: ?>' . rtrim($ifFalse) . "\n";
        }

        $code .= '<?php endif; ?>' . "\n";
        return $code;
    }

    public function elseTagStop()
    {
        return $token['type'] === WPThemifier_Token::TYPE_TAG_END
            && $token['tag'] === 'else';
    }

    public function getTag()
    {
        return 'if';
    }
}
