<?php

class WPThemifier_TagParser_EditLink extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        // FIXME eval...
        $content = $themifier->parse(array($this, 'tagStop'));
        return '<?php ob_start();eval(' . var_export('?>' . $content, true) . ');edit_post_link(ob_get_clean()); ?>';
    }

    public function getTag()
    {
        return 'edit-link';
    }
}
