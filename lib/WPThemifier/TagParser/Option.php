<?php

class WPThemifier_TagParser_Option extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $name = (string) $token['attrs']['name'];
        $default = isset($token['attrs']['default']) ? $token['attrs']['default'] : false;

        $themifier->getStream()->nextIf(
            WPThemifier_Token::TYPE_TAG_END, array('tag' => $this->getTag())
        );

        return sprintf(
            '<?php echo get_option(%s, %s); ?>',
            var_export($name, true),
            var_export($default, true)
        );
    }

    public function getTag()
    {
        return 'option';
    }
}

