<?php

class WPThemifier_TagParser_Template extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $attrs = array_merge(array(
            'name'         => null,
            'selectable'   => null,
            'display_name' => null,
        ), $token['attrs']);

        $name = trim($attrs['name']);
        if (empty($name)) {
            throw new Exception('Template name cannot be empty');
        }

        $prevTemplate = $themifier->getCurrentTemplate();
        if ($prevTemplate) {
            throw new Exception('Templates cannot be nested');
        }

        $themifier->setCurrentTemplate($name);

        $scope = $themifier->getVarEnv()->pushScope();
        $scope->setThemifier($themifier);

        $value = $themifier->parse(array($this, 'tagStop'));
        $themifier->getVarEnv()->popScope();

        if ($name === 'header') {
            $scope->parseVar('cookie_path');
            $scope->addToPreamble('!headers_sent() && empty($_COOKIE[TEST_COOKIE]) && setcookie(TEST_COOKIE, 1, 0, $cookie_path);');
        }

        $themifier->setCurrentTemplate($prevTemplate);

        if ($attrs['selectable']) {
            $displayName = trim($attrs['display_name']);
            if (empty($displayName)) {
                $displayName = $name;
            }
            $info = "    /*\n"
                  . "     Template name: $displayName\n"
                  . "     */\n";
        } else {
            $info = '';
        }

        $value = '<?php' . "\n"
            . $info
            . '    // Generated automatically by WP Themifier. Do not edit! (unless you know what you\'re doing)' . "\n"
            . $scope->renderPreamble()
            . '?' . '>' . "\n"
            . $value;

        return $value;
    }

    public function getTag()
    {
        return 'template';
    }
}
