<?php

class WPThemifier_VarScope
{
    protected $_themifier;

    protected $_requireRuntime = false;
    protected $_ob = false;
    protected $_vars = array();

    public function setThemifier($themifier)
    {
        $this->_themifier = $themifier;
    }

    public function addToPreamble($code)
    {
        $this->_vars[] = $code;
        return $this;
    }

    public function renderPreamble()
    {
        $preamble = '    require_once ABSPATH . \'wp-admin/includes/plugin.php\';' . "\n";

        if ($this->_requireRuntime) {
            $preamble .=
                '    if (!is_plugin_active(\'wp-themifier-runtime/wp-themifier-runtime.php\')) {' . "\n" .
                '        echo \'WP Themifier Runtime plugin is required for this theme to work.\';' . "\n" .
                '        echo \'Please <a href="\' . admin_url(\'plugins.php\') . \'">enable</a> or <a href="http://github.com/xemlock/wp-themifier-runtime">install</a> it.\';' . "\n" .
                '        exit;' . "\n" .
                '    }' . "\n";
        }

        if ($this->_vars) {
            $preamble .= ($this->_ob ? '    ob_start();' . "\n" : '')
                      . '    ' . implode("\n    ", $this->_vars) . "\n"
                      . ($this->_ob ? '    ob_end_clean();' . "\n" : '');
        }

        return $preamble;
    }

    public function parseVar($varname, $token = null)
    {
        switch ($varname) {
            // document parts
            case 'charset':
                $this->_ob = true;
                $this->_vars['charset'] = 'ob_clean();'
                    . 'bloginfo(\'charset\');'
                    . '$charset = ob_get_contents();';
                return '<?php echo $charset; ?>';

            case 'wp_head':
                $this->_ob = true;
                $this->_vars['wp_head'] = 'ob_clean();'
                    . 'wp_head();'
                    . '$wp_head = ob_get_contents();'
                    . '$wp_head = preg_replace(\'/<meta\s+name="generator"\s+content="[^"]+"\s*\\/?>/i\', \'\', $wp_head);'
                    . '$wp_head = join("\n    ", preg_split(\'/[\n\r]\s*/\', trim($wp_head))) . "\n";';
                return '<?php echo $wp_head; ?>';

            case 'wp_footer': // wp admin bar is located here
                return '<?php wp_footer(); ?>'; 

            case 'search_query':
                return '<?php echo get_search_query(); ?>';

            // template parts
            case 'header':
                return '<?php get_header(); ?>';

            case 'footer':
                return '<?php get_footer(); ?>';

            case 'sidebar':
                return '<?php get_sidebar(); ?>';

            // page parts
            case 'ID':
            case 'id':
                return '<?php the_ID(); ?>';

            case 'title':
                return '<?php the_title(); ?>';

            case 'content':
                return '<?php the_content(); ?>';

            case 'content_main':
                $this->_requireRuntime = true;
                return '<?php echo themifier_get_content(null, \'main\'); ?>';

            case 'content_extended':
                $this->_requireRuntime = true;
                return '<?php echo themifier_get_content(null, \'extended\'); ?>';

            case 'content_more':
                $this->_requireRuntime = true;
                return '<?php echo themifier_get_content(null, \'more\'); ?>';

            case 'content_template':
                return '<?php get_template_part(\'content\', get_post_format()); ?>';

            case 'excerpt':
                return '<?php the_excerpt(); ?>';

            case 'post_class':
                return '<?php echo join(\' \', get_post_class()); ?>';

            case 'lang':
                $this->_requireRuntime = true;
                $this->_vars['lang'] = '$lang = themifier_lang_code();';
                return '<?php echo $lang; ?>';

            case 'post_thumbnail':
                // DON'T set image dimensions in markup!
                return '<?php echo preg_replace(\'/(width|height)=["\\\'][\d]+["\\\']/i\', \'\', get_the_post_thumbnail()); ?>';

            // variables than can be evaluated once per template

            case 'base_url':
                $this->_vars['base_url'] = '$base_url = preg_replace(\'|https?://[^/]+|i\', \'\', get_option(\'siteurl\'));';
                return '<?php echo $base_url; ?>';

            case 'request_uri':
                $this->_vars['request_uri'] = '$request_uri = $_SERVER[\'REQUEST_URI\'];';
                return '<?php echo esc_url($request_uri); ?>';

            case 'login_url':
                $this->parseVar('request_uri');
                $this->_vars['login_url'] = '$login_url = wp_login_url($request_uri);';
                return '<?php echo esc_url($login_url); ?>';

            case 'logout_url':
                $this->parseVar('request_uri');
                $this->_vars['logout_url'] = '$logout_url = wp_logout_url($request_uri);';
                return '<?php echo esc_url($logout_url); ?>';

            case 'lostpassword_url':
                $this->parseVar('request_uri');
                $this->_vars['lostpassword_url'] = '$lostpassword_url = wp_lostpassword_url($request_uri);';
                return '<?php echo esc_url($lostpassword_url); ?>';

            case 'registration_url':
                $this->_vars['registration_url'] = '$registration_url = wp_registration_url();';
                return '<?php echo esc_url($registration_url); ?>';

            case 'template_directory_uri':
                $this->_vars['template_directory_uri'] = '$template_directory_uri = get_template_directory_uri();';
                return '<?php echo esc_url($template_directory_uri); ?>';

            case 'language_attributes':
                $this->_ob = true;
                $this->_vars['language_attributes'] = 'ob_clean();'
                    . 'language_attributes();'
                    . '$language_attributes = ob_get_contents();';
                return '<?php echo $language_attributes; ?>';

            case 'home_url':
                $this->_requireRuntime = true;
                $this->_vars['home_url'] = '$home_url = themifier_home_url();';
                return '<?php echo $home_url; ?>';

            case 'pingback_url':
                $this->_vars['pingback_url'] = '$pingback_url = get_bloginfo(\'pingback_url\', \'display\');';
                return '<?php echo $pingback_url; ?>';

            case 'description':
                $this->_vars['description'] = '$description = get_bloginfo(\'description\', \'display\');';
                return '<?php echo $description; ?>';

            case 'body_class':
                $this->_vars['body_class'] = '$body_class = join(\' \', get_body_class());';

                if (empty($this->_themifier)) {
                    throw new Exception('Unable to determine theme props');
                }
                if (!$this->_themifier->getThemeProp('custom_background')) {
                    $this->_vars['body_class'] .= '$body_class = preg_replace(\'/\s*custom-background\s*/i\', \'\', $body_class);';
                }
                return '<?php echo $body_class; ?>';

            case 'cookie_path':
                $this->parseVar('base_url');
                $this->_vars['cookie_path'] = '$cookie_path = rtrim($base_url, \'/\') . \'/\';';
                return '<?php echo $cookie_path; ?>';

            case 'search_form':
                return '<?php get_search_form(); ?>;';

            default:
                if (preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/', $varname)) {
                    return '<?php echo $' . $varname . '; ?>';
                }
                throw new Exception('Invalid variable name: ' . $varname);
        }
    }
}
