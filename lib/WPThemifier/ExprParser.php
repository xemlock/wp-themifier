<?php

class WPThemifier_ExprParser
{
    protected $_themifier;

    public function __construct($themifier)
    {
        $this->_themifier = $themifier;
    }

    public function parse($expr)
    {
        $stream = token_get_all('<?php ' . $expr);
        array_shift($stream);

        $php = '';

        for ($i = 0; $i < count($stream); ++$i) {
            $token = $stream[$i];
            if (is_string($token)) {
                switch ($token) {
                    case ')':
                    case '(':
                    case '+':
                    case '-':
                    case '.':
                    case ':':
                    case '?':
                    case ',':
                    case '{':
                    case '}':
                        $php .= $token;
                        break;

                    default:
                        throw new Exception('Unexpected token: ' . $token);
                }
            } else switch ($token[0]) {
                case T_LOGICAL_AND:
                case T_LOGICAL_OR:
                case T_LOGICAL_XOR:
                case T_BOOLEAN_AND:
                case T_BOOLEAN_OR:
                case T_CONSTANT_ENCAPSED_STRING:
                    $php .= $token[1];
                    break;

                case T_STRING:
                    if (isset($stream[$i + 1]) && $stream[$i + 1] ==='(') { // function call
                        $php .= $token[1];
                    } else {
                        $this->_themifier->getVarEnv()->getCurrentScope()->parseVar($token[1]);
                        $php .= '$' . $token[1];
                    }
                    break;                    

                case T_WHITESPACE:
                    $php .= ' ';
                    break;

                default:
                    throw new Exception('Unexpected token: ' . token_name($token[0]) . ': ' . $token[1]);
            }
        }

        return $php;
    }
}
