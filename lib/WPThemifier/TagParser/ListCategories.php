<?php

class WPThemifier_TagParser_ListCategories
    extends WPThemifier_TagParser_FunctionCall
{
    public function getFunction()
    {
        return 'wp_list_categories';
    }

    public function getTag()
    {
        return 'list-categories';
    }
}
