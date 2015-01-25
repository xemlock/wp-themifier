<?php

abstract class WPThemifier_TagParser_FunctionCall
    extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $args = array();

        if (isset($token['attrs']['args'])) {
            $args = $token['attrs']['args'];
        } else {
            foreach ($token['attrs'] as $k => $val) {
                $k = preg_replace('/[^_0-9A-Za-z]/', '_', $k);
                $args[$k] = $val;
            }
        }

        // consume closing tag
        $themifier->getStream()->nextIf(
            WPThemifier_Token::TYPE_TAG_END, array('tag' => $this->getTag())
        );

        return sprintf(
            '<?php echo %s(%s); ?>', $this->getFunction(), var_export($args, true)
        );
    }

    abstract function getFunction();
}
