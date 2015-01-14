<?php

interface WPThemifier_TagParserInterface
{
    public function parse(array $token, WPThemifier_Compiler $themifier);
    public function getTag();
}
