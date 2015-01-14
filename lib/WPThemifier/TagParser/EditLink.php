<?php

class WPThemifier_TagParser_EditLink implements WPThemifier_TagParserInterface
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        // FIXME eval...
        $content = $themifier->parse(array($this, 'tagStop'));
        return '<?php ob_start();eval(' . var_export('?>' . $content, true) . ');edit_post_link(ob_get_clean()); ?>';
    }

    public function tagStop(array $token)
    {
        return $token['type'] === WPThemifier_Token::TYPE_TAG_END
            && $token['tag'] === $this->getTag();
    }

    public function getTag()
    {
        return 'edit-link';
    }
}
