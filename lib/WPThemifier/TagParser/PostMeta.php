<?php

/**
 * <wp:post-meta post_id="" name="" var="" echo="true" date_format="" />
 */
class WPThemifier_TagParser_PostMeta extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $attrs = array_merge(array(
            'name'        => null,
            'post_id'     => null,
            'var'         => null,
            'date_format' => null,
        ), $token['attrs']);

        $name = (string) $attrs['name'];
        $post_id = (int) $attrs['post_id'];

        $themifier->getStream()->nextIf(
            WPThemifier_Token::TYPE_TAG_END, array('tag' => $this->getTag())
        );

        if (isset($attrs['var'])) {
            $var = '$' . $this->checkName($attrs['var']);
            $echo = false;
        } else {
            $var = '$__post_meta';
            $echo = true;
        }

        $php = '<?php %{var} = get_post_meta(%{id}, %{name}, true); ';

        if (isset($attrs['date_format'])) {
            $php .= '%{var} = date_i18n(%{date_format}, is_int(%{var}) || ctype_digit(%{var}) ? %{var} : (int) strtotime(%{var})); ';
        }

        if ($echo) {
            $php .= 'echo %{var}; ';
        }

        $php .= '?>';

        return strtr($php, array(
            '%{var}'  => $var,
            '%{id}'   => $post_id ? var_export($post_id, true) : 'get_the_ID()',
            '%{name}' => var_export($name, true),
            '%{date_format}' => var_export($attrs['date_format'], true),
        ));
    }

    public function getTag()
    {
        return 'post-meta';
    }
}

