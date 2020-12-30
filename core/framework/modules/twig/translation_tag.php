<?php

class TranslationTag_Node extends Twig_Node
{
    public function __construct(Twig_Node_Expression $id, $line, $tag = null)
    {
        parent::__construct([DBField::ID => $id], [], $line, $tag);
    }

    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('echo i18n::translate_tag($context[\'i18n\'], ')
            ->subcompile($this->getNode('id'))
            ->write(')')
            ->raw(";\n")
        ;
    }
}

class TranslationTag_TokenParser extends Twig_TokenParser
{
    public function parse(Twig_Token $token)
    {
        $stream = $this->parser->getStream();
        $value = $this->parser->getExpressionParser()->parseExpression();

        $stream->expect(Twig_Token::BLOCK_END_TYPE);

        return new TranslationTag_Node($value, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 't';
    }
}
