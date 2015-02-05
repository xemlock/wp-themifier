<?php

class WPThemifier_TagParser_Var extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $attrs = array_merge(array(
            'name'  => null,
            'value' => null,
        ), $token['attrs']);

        if (empty($attrs['name'])) {
            throw new Exception('name attribute is required');
        }

        $name = $this->checkName($token['attrs']['name']);

        if (isset($attrs['value'])) {
            $value = $attrs['value'];
        } else {
            $value = $themifier->read(array($this, 'tagStop'));
        }

        $expr = $themifier->getExprParser()->parse($value);

        $themifier->getStream()->nextIf(
            WPThemifier_Token::TYPE_TAG_END, array('tag' => $this->getTag())
        );

        return '<?php $' . $name . ' = ' . $expr . '; ?>';
    }

    public function getTag()
    {
        return 'var';
    }
}
