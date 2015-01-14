<?php

class WPThemifier_TagParser_Trans extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        if (!$themifier->getTheme()) {
            throw new Exception('Translation must be inside theme, Line: ' . $token['lineno']);
        }

        $string = $themifier->parse(array($this, 'tagStop'));
        $string = str_replace(array("\r\n", "\r"), "\n", trim($string));
        $string = implode("\n", array_map('trim', explode("\n", $string)));

        if (preg_match('/\{[^\}]+\}/', $string, $match)) {
            print_r($match);
            exit;
        }

        return sprintf('<?php _e(%s, %s); ?>',
            str_replace(array("\n", "\t"), array('\\n', '\\t'), var_export($string, true)),
            var_export($themifier->getThemeProp('text_domain'), true)
        );
    }

    public function getTag()
    {
        return 'trans';
    }
}

