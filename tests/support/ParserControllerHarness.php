<?php

declare(strict_types=1);

use app\home\controller\ParserController;

/**
 * ParserController 测试夹具（Reflection，跳过 DB 依赖）
 */
final class ParserControllerHarness
{
    /** @var ParserController */
    public $parser;

    /** @var ReflectionMethod */
    private $validateIfCondition;

    public static function create(): self
    {
        $h = new self();
        $refClass = new ReflectionClass(ParserController::class);
        $h->parser = $refClass->newInstanceWithoutConstructor();

        $preProp = $refClass->getProperty('pre');
        if (PHP_VERSION_ID < 80100) {
            $preProp->setAccessible(true);
        }
        $preProp->setValue($h->parser, array());

        $h->validateIfCondition = $refClass->getMethod('validateIfCondition');
        if (PHP_VERSION_ID < 80100) {
            $h->validateIfCondition->setAccessible(true);
        }

        return $h;
    }

    public function isValidIfCondition(string $condition): bool
    {
        return (bool) $this->validateIfCondition->invoke($this->parser, $condition);
    }

    /** 是否存在可被 parserIfLabel 匹配的活跃 pboot:if（排除 pboot@if） */
    public static function hasActivePbootIf(string $content): bool
    {
        return (bool) preg_match('/\{pboot:if\(/i', $content);
    }

    /** 与 parserAfter 尾部一致：parserIfLabel → parserLoopLabel → restorePreLabel */
    public function runIfPipeline(string $content): string
    {
        $content = $this->parser->parserIfLabel($content);
        $content = $this->parser->parserLoopLabel($content);
        $content = $this->parser->restorePreLabel($content);

        return $content;
    }
}
