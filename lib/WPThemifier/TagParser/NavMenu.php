<?php

class WPThemifier_TagParser_NavMenu extends WPThemifier_TagParser_FunctionCall
{
    public function getFunction()
    {
        return 'themifier_nav_menu';
    }

    public function getTag()
    {
        return 'nav-menu';
    }
}
