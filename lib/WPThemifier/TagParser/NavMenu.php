<?php

class WPThemifier_TagParser_NavMenu implements WPThemifier_TagParserInterface
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $options = array();
        foreach ($token['attrs'] as $k => $val) {
            $k = preg_replace('/[^_0-9A-Za-z]/', '_', $k);
            $options[$k] = $val;
        }
        if (empty($options['theme_location'])) {
            throw new Exception('nav-menu tag requires theme location to be set');
        }
        $theme_location = $options['theme_location'];
        unset($options['theme_location']);

        // skip closing tag
        $themifier->getStream()->nextIf(WPThemifier_Token::TYPE_TAG_END, array('tag' => 'nav-menu'));

        return sprintf('<?php echo themifier_nav_menu(%s, %s); ?>',
            var_export($theme_location, true),
            var_export($options, true)
        );
    }

    public function getTag()
    {
        return 'nav-menu';
    }
}
