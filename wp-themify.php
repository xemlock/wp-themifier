<?php

// This is a quick'n'dirty tool for building WP theme files from a single
// HTML file.

class WPThemifier_TokenStream
{
    protected $_tokens;
    protected $_pos;

    public function __construct(array $tokens)
    {
        $this->_tokens = array_values($tokens);
        $this->_pos = -1;
    }

    public function next()
    {
        if (isset($this->_tokens[$this->_pos + 1])) {
            return $this->_tokens[++$this->_pos];
        }
        return null;
    }

    public function peek($offset = 1)
    {
        if (isset($this->_tokens[$this->_pos + $offset])) {
            return $this->_tokens[$this->_pos + $offset];
        }
        return null;
    }

    public function hasNext()
    {
        return isset($this->_tokens[$this->_pos + 1]);
    }

    public function current()
    {
        return $this->_pos > 0 ? $this->_tokens[$this->_pos] : null;
    }
}

class WPThemifier
{
    protected $_file;
    protected $_lexer;
    protected $_theme;
    protected $_themeProps;
    protected $_currentTemplate;
    protected $_ob;
    protected $_vars;

    public function __construct($file)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException(sprintf('Unable to open file: %s', $file));
        }
        $this->_file = realpath($file);
    }

    const ATTRS_RE = '(?P<attrs>(\s+([-:_a-zA-Z0-9]+)=\"([^\"]*)\")*)';

    const TYPE_STRING    = 'STRING';
    const TYPE_TAG_START = 'TAG_START';
    const TYPE_TAG_END   = 'TAG_END';
    const TYPE_VAR       = 'VAR';

    /**
     * @param  string $input
     * @return array
     */
    public function parseAttrs($input) // {{{
    {
        // \G current offset
        $regex = '/\G\s*(?P<key>[-:_a-zA-Z0-9]+)=\"(?P<value>[^\"]*)\"/';
        $attrs = array();
        $offset = 0;
        while (preg_match($regex, $input, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $key = $match['key'][0];
            $value = $match['value'][0];

            $attrs[$key] = $this->coerce($value);
            $offset = $match[0][1] + strlen($match[0][0]);
        }
        return $attrs;
    } // }}}

    public function tokenize($input)
    {
        // trailing newlines are consumed with tags
        $regexes = array(
            self::TYPE_TAG_START => '/<\s*wp:(?P<tag>[-_a-zA-Z0-9]+)' . self::ATTRS_RE . '\s*\/?[>]\s*/',
            self::TYPE_TAG_END   => '/<\s*\/wp:(?P<tag>[-_a-zA-Z0-9]+)\s*>\s*/',
            self::TYPE_VAR       => '/\$\{\s*(?P<var>[_a-zA-Z][_a-zA-Z0-9]*)\s*\}/',
        );

        // table of line numbers
        $nls = array();
        $offset = 0;
        $count = 0;
        while (false !== ($pos = strpos($input, "\n", $offset))) {
            $offset = $pos + 1;
            $nls[$pos] = ++$count;
        }

        $tokens = array();

        foreach ($regexes as $type => $re) {
            if (preg_match_all($re, $input, $match, PREG_OFFSET_CAPTURE)) {
                foreach ($match[0] as $index => $m) {
                    // m[0] -> matched regex
                    // m[1] -> token offset
                    $prev = -1;
                    foreach ($nls as $p => $lineno) { // should do binary search
                        if ($p >= $m[1]) {
                            break;
                        }
                        $prev = $lineno;
                    }
                    $token = array(
                        'start' => $m[1],
                        'end'   => $m[1] + strlen($m[0]),
                        'type'  => $type,
                        'match' => $m[0],
                        'lineno' => $prev,
                    );
                    switch ($type) {
                        case self::TYPE_TAG_START:
                            $token['tag'] = strtolower($match['tag'][$index][0]);
                            $token['attrs'] = $this->parseAttrs($match['attrs'][$index][0]);
                            break;

                        case self::TYPE_TAG_END:
                            $token['tag'] = strtolower($match['tag'][$index][0]);
                            break;

                        case self::TYPE_VAR:
                            $token['var'] = $match['var'][$index][0];
                            break;
                    }
                    $tokens[$token['start']] = $token;
                }
            }
        }

        // strings between already matched tokens are strings
        ksort($tokens);

        $stream = array();
        $offset = 0;
        foreach ($tokens as $token) {
            if ($token['start'] < $offset) {
                echo 'Misplaced token: ', print_r($token, 1), "\n";
                continue;
            }
            $tok_off = $token['start'];
            if ($tok_off - $offset > 0) {
                $str = substr($input, $offset, $tok_off - $offset);
                $stream[] = array(
                    'start' => $offset,
                    'end'   => $tok_off,
                    'type'  => self::TYPE_STRING,
                    'value' => $str,
                );
            }
            $stream[] = $token;
            $offset = $token['end'];
        }
        if ($offset < strlen($input)) {
            $stream[] = array(
                'start' => $offset,
                'end' => strlen($input),
                'type' => self::TYPE_STRING,
                'value' => substr($input, $offset),
            );
        }

        return new WPThemifier_TokenStream($stream);
    }


    public function setTheme($theme, array $props = array())
    {
        if (strlen($this->_theme)) {
            throw new Exception('Theme is already defined as: ' . $this->_theme);
        }
        $this->_theme = trim($theme);

        foreach ($props as $key => $value) {
            $key = str_replace(array('-', ':'), '_', strtolower($key));
            $props[$key] = $value;
        }
        $this->_themeProps = array_merge(array(
            'theme_uri'   => null,
            'author'      => null,
            'author_uri'  => null,
            'description' => null,
            'version'     => date('Y-m-d'),
            'text_domain' => $this->_theme,
            'custom_background' => true,
        ), $props);
        return $this;
    }

    public function getTheme()
    {
        return $this->_theme;
    }

    public function setCurrentTemplate($template)
    {
        $template = trim($template);
        if ($template) {
            if ($this->_currentTemplate) {
                throw new Exception('Templates cannot be nested');
            }
        }
        $this->_currentTemplate = $template;
        return $this;
    }

    public function getCurrentTemplate()
    {
        return $this->_currentTemplate;
    }



    protected function _prepareInput($input)
    {
        $input = str_replace(array("\r\n", "\r"), "\n", $input);

        // replace all wp: commentags with tags, i.e.
        // <!-- wp:tag param="value" --> -> <wp:tag param="value">
        $input = preg_replace(array(
            '/<!--\s*(wp:[-_a-zA-Z0-9]+' . self::ATTRS_RE . ')\s*-->/i',
            '/<!--\s*(\/wp:[-_a-zA-Z0-9]+)\s*-->/i',
        ), '<$1>', $input);

        // add language_attributes to HTML, append wp_head to head if necessary
        $input = preg_replace('/<html([\s>])/', '<html ${language_attributes}$1', $input);

        if ((stripos($input, '</head>') !== false) && !preg_match('/\$\{\s*wp_head\s*\}/', $input)) {
            $input = preg_replace('/([ \t]*)(<\\/head>)/i', '$1$1${ wp_head }' . "\n" . '$1$2', $input);
        }

        if ((stripos($input, '</body>') !== false) && !preg_match('/\$\{\s*wp_footer\s*\}/', $input)) {
            $input = preg_replace('/([ \t]*)(<\\/body>)/i', '$1$1${ wp_footer }' . "\n" . '$1$2', $input);
        }

        // apply proper charset
        $charset_re = '[-_:.()0-9A-Za-z]+';

        $input = preg_replace('/charset\s*=\s*([\'"])(' . $charset_re . ')([\'"])/i', 'charset=$1${ charset }$3', $input);
        $input = preg_replace('/charset\s*=\s*(' . $charset_re . ')/i', 'charset=${ charset }', $input);

        // replace all src= and href= that does not start with / or (ht|f)tp(s)://
        // prepend {{ template_directory_uri }}/
        $input = preg_replace_callback('/(?P<attr>(src|href))\s*=\s*[\'"](?P<url>[^\'"]+)[\'"]/', array($this, '_checkUrls'), $input);

        $lineno = 1;
        $lines = explode("\n", $input);
        $ndigits = floor(log10(count($lines)) + 1);

        $src = '';
        foreach ($lines as $line) {
            $src .= sprintf("%{$ndigits}d %s\n", $lineno++, $line);
        }
        file_put_contents($this->_file . '-source.txt', $src);

        return $input;
    }

    protected function _parse($stop = null)
    {
        $output = array();
        while ($token = $this->_stream->next()) {
            if ($stop && call_user_func($stop, $token, $this->_stream)) {
                break;
            }
            switch ($token['type']) {
                case self::TYPE_TAG_START:
                    $o = $this->parseTag($token);
                    break;

                case self::TYPE_STRING:
                    $o = $token['value'];
                    break;

                case self::TYPE_VAR:
                    $o = $this->_parseVar($token['var'], $token);
                    break;

                default:
                    throw new Exception('Unexpected token: ' . print_r($token, 1));

            }
            if ($o !== null) {
                $output[] = $o;
            }
        }
        return implode('', $output);
    }

    public function run()
    {
        $input = file_get_contents($this->_file);
        $input = $this->_prepareInput($input);

        $this->_stream = $this->tokenize($input);
        $this->_parse();
    }

    public function parseTag($token)
    {
        switch ($token['tag']) {
            case 'theme':
                $name = trim($token['attrs']['name']);
                $this->setTheme($name, $token['attrs']);
                echo '[THEME] ', $name, "\n";
                file_put_contents('style.css', 
"/*
    Theme Name:   {$this->_theme}
    Theme URI:    {$this->_themeProps['theme_uri']}
    Author:       {$this->_themeProps['author']}
    Author URI:   {$this->_themeProps['author_uri']}
    Description:  {$this->_themeProps['description']}
    Version:      {$this->_themeProps['version']}
    Text Domain:  {$this->_themeProps['text_domain']}
*/\n");
                echo '[  OK  ] Theme info written to ', 'style.css', "\n";
                break;

            case 'template':
                $value = $this->parseTemplate($token);
                $name = trim($token['attrs']['name']);
                $file = basename($name) . '.php';

                // rtrim each line
                $value = join("\n", array_map('rtrim', explode("\n", $value)));

                file_put_contents($file, $value);
                echo '[  OK  ] Template ', $name, ' written to ', $file, "\n";
                break;

            case 'comment':
                // consume comments
                while ($token = $this->_stream->next()) {
                    if ($token['type'] === WPThemifier::TYPE_TAG_END &&
                        $token['tag'] === 'comment'
                    ) {
                        break;    
                    }
                }
                break;

            case 'test':
                return $this->parseTest($token);

            case 'trans':
            case 'translate':
                if (!$this->_theme) {
                    throw new Exception('Translation must be only in theme, Line: ' . $token['lineno']);
                }
                $string = $this->_parse(function ($token) {
                    return $token['type'] === WPThemifier::TYPE_TAG_END &&
                           in_array($token['tag'], array('trans', 'translate'));
                });
                $string = str_replace(array("\r\n", "\r"), "\n", trim($string));
                $string = implode("\n", array_map('trim', explode("\n", $string)));
                return '<?php _e(' . str_replace(array("\n", "\t"), array('\\n', '\\t'), var_export($string, true)) . ', ' . var_export($this->_themeProps['text_domain'], true) . '); ?'. '>';
                break;

            default:
                throw new Exception('Unrecognized tag: ' . $token['tag'] . ' at line: ' . $token['lineno']);
        }
    }

    public function parseTemplate($token)
    {
        if (empty($this->_theme)) {
            throw new Exception('Template tag requires theme to be set');
        }
        if ($this->_currentTemplate) {
            throw new Exception('Templates cannot be nested');
        }

        $this->_requireRuntime = false;
        $this->_ob = false;
        $this->_vars = array();

        $name = trim($token['attrs']['name']);

        $this->_currentTemplate = $name;
        $value = $this->_parse(function ($token) {
            return $token['type'] === WPThemifier::TYPE_TAG_END &&
                   $token['tag'] === 'template';
        }, true);

        if ($name === 'header') {
            // make sure login form works
            $this->_parseVar('cookie_path');
            $this->_vars[] = '!headers_sent() && empty($_COOKIE[TEST_COOKIE]) && setcookie(TEST_COOKIE, 1, 0, $cookie_path);';
        }

        $this->_currentTemplate = null;

        $preamble =
            '    require_once ABSPATH . \'wp-admin/includes/plugin.php\';' . "\n" .
            '    if (!is_plugin_active(\'wp-themifier-runtime/wp-themifier-runtime.php\')) {' . "\n" .
            '        echo \'WP Themifier Runtime plugin is required for this theme to work.\';' . "\n" .
            '        echo \'Please <a href="\' . admin_url(\'plugins.php\') . \'">enable</a> or <a href="http://github.com/xemlock/wp-themifier-runtime">install</a> it.\';' . "\n" .
            '        exit;' . "\n" .
            '    }' . "\n";

        if ($this->_vars) {
            $preamble .= ($this->_ob ? '    ob_start();' . "\n" : '')
                      . '    ' . implode("\n    ", $this->_vars) . "\n"
                      . ($this->_ob ? '    ob_end_clean();' . "\n" : '');
        }

        $value = '<?php' . "\n"
            . '    // Generated automatically by WP Themifier. Do not edit! (unless you know what you\'re doing)' . "\n"
            . $preamble
            . '?' . '>' . "\n"
            . $value;

        return $value;
    }

    public function parseTest($token)
    {
        $var = trim($token['attrs']['var']);
        if (empty($var)) {
            throw new Exception('wp:test tag reqiures var attribute to be provided');
        }

        $this->_parseVar($var);

        $value = null;

        if (isset($token['attrs']['value'])) {
            $value = $this->coerce($token['attrs']['value']);
        }

        $content = $this->_parse(function ($token, $stream) {
            return $token['type'] === WPThemifier::TYPE_TAG_END
                && $token['tag'] === 'test';
        });

        // check if defined, then perform any value check
        $cond = 'isset($' . $var . ')';

        if ($value !== null) {
            $eq = $this->coerce(@$token['attrs']['strict']) ? '===' : '==';
            $val = var_export($value, true);
            $cond .= " && ($${var} ${eq} ${val})";
        } else {
            $cond .= " && $${var}";
        }

        return '<?php if (' . $cond . '): ?>' . "\n" . $content . "\n" . '<?php endif; ?>' . "\n";
    }

    public function coerce($value)
    {
        if (in_array(strtolower($value), array('true', 'yes', 'on'), true)) {
            return true;
        }
        if (in_array(strtolower($value), array('false', 'no', 'off'), true)) {
            return false;
        }
        if (strtolower($value) === 'null') {
            return null;
        }
        if (ctype_digit($value)) {
            return intval($value);
        }
        return $value;
    }

    public function _checkUrls(array $match)
    {
        $url = $match['url'];
        if (!preg_match('/^([#\/]|(javascript|mailto):|((f|ht)tp(s)?):\/\/)/i', $url)) {
            if (substr($url, 0, 2) !== '${' &&
                substr($url, 0, 5) !== '<?php'
            ) {
                $url = '${ template_directory_uri }/' . $url;
                return $match['attr'] . '="' . $url . '"';
            }
        }
        return $match[0];
    }

    public function _parseVar($varname, $token = null)
    {
        if (!$this->_currentTemplate) {
            throw new Exception('Variables may only appear inside templates, var: ' . $varname . ', line: ' . ($token ? $token['lineno'] : -1));
        }
        if (!$this->_theme) {
            throw new Exception('Variables may only appear after the theme is defined, var: ' . $varname . ', line: ' . ($token ? $token['lineno'] : -1));
        }
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
                    . '$head = ob_get_contents();'
                    . '$head = preg_replace(\'/<meta\s+name="generator"\s+content="[^"]+"\s*\\/?>/i\', \'\', $head);'
                    . '$head = join("\n    ", preg_split(\'/[\n\r]\s*/\', trim($head))) . "\n";';
                return '<?php echo $wp_head; ?>';

            case 'wp_footer': // wp admin bar is located here
                return '<?php wp_footer(); ?>'; 

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

            case 'content_template':
                return '<?php get_template_part(\'content\', get_post_format()); ?>';

            case 'excerpt':
                return '<?php the_excerpt(); ?>';

            case 'post_class':
                return '<?php echo join(\' \', get_post_class()); ?>';

            case 'lang':
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
                return '<?php echo $_SERVER[\'REQUEST_URI\']; ?>';

            case 'login_url':
                $this->_parseVar('request_uri');
                $this->_vars['login_url'] = '$login_url = esc_url(wp_login_url($request_uri));';
                return '<?php echo $login_url; ?>';

            case 'logout_url':
                $this->_parseVar('request_uri');
                $this->_vars['logout_url'] = '$logout_url = esc_url(wp_logout_url($request_uri));';
                return '<?php echo $logout_url; ?>';

            case 'lostpassword_url':
                $this->_parseVar('request_uri');
                $this->_vars['lostpassword_url'] = '$lostpassword_url = esc_url(wp_lostpassword_url($request_uri));';
                return '<?php echo $lostpassword_url; ?>';

            case 'registration_url':
                $this->_parseVar('request_uri');
                $this->_vars['registration_url'] = '$registration_url = esc_url(wp_registration_url());';
                return '<?php echo $registration_url; ?>';

            case 'template_directory_uri':
                $this->_vars['template_directory_uri'] = '$template_directory_uri = get_template_directory_uri();';
                return '<?php echo $template_directory_uri; ?>';

            case 'language_attributes':
                $this->_ob = true;
                $this->_vars['language_attributes'] = 'ob_clean();'
                    . 'language_attributes();'
                    . '$language_attributes = ob_get_contents();';
                return '<?php echo $language_attributes; ?>';

            case 'home_url':
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

                if (!$this->_themeProps['custom_background']) {
                    $this->_vars['body_class'] .= '$body_class = preg_replace(\'/\s*custom-background\s*/i\', \'\', $body_class);';
                }
                return '<?php echo $body_class; ?>';

            case 'cookie_path':
                $this->_parseVar('base_url');
                $this->_vars['cookie_path'] = '$cookie_path = rtrim($base_url, \'/\') . \'/\';';
                return '<?php echo $cookie_path; ?>';

            default:
                if (preg_match('/^[_a-zA-Z][_a-zA-Z0-9]*$/', $varname)) {
                    return '<?php echo ' . $varname . '; ?>';
                }
                throw new Exception('Invalid variable name: ' . $varname);
        }
    }
}

$input = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'index.html';

try {
    $themifier = new WPThemifier($input);
    $themifier->run();
} catch (Exception $e) {
    echo $e->getMessage(), "\n\n";
    exit(1);
}


