<?php

class WPThemifier_Compiler
{
    protected $_file;
    protected $_theme;
    protected $_themeProps;
    protected $_currentTemplate;
    protected $_tagParsers = array();
    protected $_varEnv;
    public $templateRegistry = array();

    public function __construct($file)
    {
        $this->_varEnv = new WPThemifier_VarEnv();

        $this->addTagParser(new WPThemifier_TagParser_Comment());
        $this->addTagParser(new WPThemifier_TagParser_If());
        $this->addTagParser(new WPThemifier_TagParser_Trans());
        $this->addTagParser(new WPThemifier_TagParser_NavMenu());
        $this->addTagParser(new WPThemifier_TagParser_EditLink());
        $this->addTagParser(new WPThemifier_TagParser_Option());
    }

    public function getVarEnv()
    {
        return $this->_varEnv;
    }

    public function compile($file)
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException(sprintf('Unable to open file: %s', $file));
        }
        $this->_file = realpath($file);

        $input = file_get_contents($this->_file);
        $input = $this->_prepareInput($input);

        $this->_stream = $this->tokenize($input);
        $this->parse();

        foreach ($this->templateRegistry as $name => $contents) {
            $file = basename($name) . '.php';

            // rtrim each line
            $contents = join("\n", array_map('rtrim', explode("\n", $contents)));

            file_put_contents($file, $contents);
            echo '[  OK  ] Template ', $name, ' written to ', $file, "\n";
        }
    }

    public function addTagParser(WPThemifier_TagParserInterface $tagParser)
    {
        $this->_tagParsers[$tagParser->getTag()] = $tagParser;
        return $this;
    }

    const ATTRS_RE = '(?P<attrs>(\s+([-:_a-zA-Z0-9]+)=\"([^\"]*)\")*)';

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
            // normalize attribute name, corece attribute value
            $key = $match['key'][0];
            $key = str_replace(array('-', ':'), '_', strtolower($key));

            $value = $match['value'][0];
            $value = $this->coerce($value);

            $attrs[$key] = $value;

            $offset = $match[0][1] + strlen($match[0][0]);
        }
        return $attrs;
    } // }}}

    public function tokenize($input)
    {
        // trailing newlines are consumed with tags
        $regexes = array(
            WPThemifier_Token::TYPE_TAG_START => '/<\s*wp-(?P<tag>[-_a-zA-Z0-9]+)' . self::ATTRS_RE . '\s*\/?[>]\s*/',
            WPThemifier_Token::TYPE_TAG_END   => '/<\s*\/wp-(?P<tag>[-_a-zA-Z0-9]+)\s*>\s*/',
            WPThemifier_Token::TYPE_VAR       => '/\$\{\s*(?P<var>[_a-zA-Z][_a-zA-Z0-9]*)\s*\}/',
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
                        case WPThemifier_Token::TYPE_TAG_START:
                            $token['tag'] = strtolower($match['tag'][$index][0]);
                            $token['attrs'] = $this->parseAttrs($match['attrs'][$index][0]);
                            break;

                        case WPThemifier_Token::TYPE_TAG_END:
                            $token['tag'] = strtolower($match['tag'][$index][0]);
                            break;

                        case WPThemifier_Token::TYPE_VAR:
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
                    'type'  => WPThemifier_Token::TYPE_STRING,
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
                'type' => WPThemifier_Token::TYPE_STRING,
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
        $this->_currentTemplate = trim($template);
        return $this;
    }

    public function getCurrentTemplate()
    {
        return $this->_currentTemplate;
    }

    public function getThemeProp($property)
    {
        return isset($this->_themeProps[$property]) ? $this->_themeProps[$property] : null;
    }

    protected function _prepareInput($input)
    {
        $input = str_replace(array("\r\n", "\r"), "\n", $input);

        // replace all wp- commentags with tags, i.e.
        // <!-- wp-tag param="value" --> -> <wp-tag param="value">
        $input = preg_replace(array(
            '/<!--\s*(wp-[-_a-zA-Z0-9]+' . self::ATTRS_RE . '(\s*\/)?)\s*-->/i',
            '/<!--\s*(\/wp-[-_a-zA-Z0-9]+)\s*-->/i',
            // backward compatibility
            '/<!--\s*(wp:[-_a-zA-Z0-9]+' . self::ATTRS_RE . '(\s*\/)?)\s*-->/i',
            '/<!--\s*(\/wp:[-_a-zA-Z0-9]+)\s*-->/i',
        ), '<$1>', $input);

        // rename all wp: tags to wp-
        $input = preg_replace('/<(\/)?(wp):/i', '<\1\2-', $input);

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

    public function parse($stop = null)
    {
        $output = array();
        while ($token = $this->_stream->next()) {
            if ($stop && call_user_func($stop, $token, $this->_stream)) {
                break;
            }
            switch ($token['type']) {
                case WPThemifier_Token::TYPE_TAG_START:
                    $o = $this->parseTag($token);
                    break;

                case WPThemifier_Token::TYPE_STRING:
                    $o = $token['value'];
                    break;

                case WPThemifier_Token::TYPE_VAR:
                    $o = $this->getVarEnv()->getCurrentScope()->parseVar($token['var']);
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
                if (empty($this->_tagParsers['template'])) {
                    $this->addTagParser(new WPThemifier_TagParser_Template());
                }
                $parser = $this->_tagParsers['template'];
                $name = trim($token['attrs']['name']);
                $contents = $parser->parse($token, $this);
                if (strlen($contents)) {
                    $this->templateRegistry[$name] = $contents;
                }
                break;

            case 'test':
                return $this->parseTest($token);

            default:
                if (isset($this->_tagParsers[$token['tag']])) {
                    return $this->_tagParsers[$token['tag']]->parse($token, $this);
                }
                throw new Exception('Mismatched tag: ' . $token['tag'] . ' at line: ' . $token['lineno']);
        }
    }

    public function getStream()
    {
        return $this->_stream;
    }

    public function parseTest($token)
    {
        $var = trim($token['attrs']['var']);
        if (empty($var)) {
            throw new Exception('test tag reqiures var attribute to be provided');
        }

        $this->_parseVar($var);

        $value = null;

        if (isset($token['attrs']['value'])) {
            $value = $this->coerce($token['attrs']['value']);
        }

        $content = $this->parse(function ($token, $stream) {
            return $token['type'] === WPThemifier_Token::TYPE_TAG_END
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


}
