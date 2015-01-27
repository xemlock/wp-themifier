<?php

class WPThemifier_TagParser_Partial extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        if (empty($token['attrs']['slug'])) {
            throw new Exception('slug is required');
        }
        $slug = $token['attrs']['slug'];

        $name = isset($token['attrs']['name']) ? $token['attrs']['name'] : null;

        // consume closing tag
        $themifier->getStream()->nextIf(
            WPThemifier_Token::TYPE_TAG_END, array('tag' => $this->getTag())
        );

        return sprintf(
            '<?php get_template_part(%s, %s); ?>',
            var_export($slug, true),
            var_export($name, true)
        );
    }

    public function getTag()
    {
        return 'partial';
    }
}
