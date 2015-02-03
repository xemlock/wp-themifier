<?php

/**
 * <wp:post-meta post_id="" name="" />
 */
class WPThemifier_TagParser_PostMeta extends WPThemifier_TagParser_Abstract
{
    public function parse(array $token, WPThemifier_Compiler $themifier)
    {
        $name = (string) $token['attrs']['name'];
        $post_id = isset($token['attrs']['post_id']) ? (int) $token['attrs']['post_id'] : null;

        $themifier->getStream()->nextIf(
            WPThemifier_Token::TYPE_TAG_END, array('tag' => $this->getTag())
        );

        $php = sprintf(
            '<?php $__meta = get_post_meta(%s, %s, true);',
            $post_id ? var_export($post_id, true) : 'get_the_ID()',
            var_export($name, true)
        );

        if (isset($token['attrs']['date_format'])) {
            $php .= sprintf(
                '$__meta = date_i18n(%s, is_int($__meta) || ctype_digit($__meta) ? $__meta : (int) strtotime($__meta));',
                var_export($token['attrs']['date_format'], true)
            );
        }

        $php .= 'echo $__meta; ?>';

        return $php;
    }

    public function getTag()
    {
        return 'post-meta';
    }
}

