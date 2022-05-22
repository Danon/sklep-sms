<?php

namespace App\Theme;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TRegx\CleanRegex\Match\Details\Detail;
use TRegx\CleanRegex\Pattern;

class ContentEvaluator
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp("__"));
        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp("url"));
        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp("versioned"));
        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp("htmlspecialchars"));
        $this->expressionLanguage->addFunction(ExpressionFunction::fromPhp("addSlashes"));
    }

    public function evaluate(string $text, array $data = []): string
    {
       return Pattern::of('({{(?<safe>.+?)}}|{!!(?<simple>.+?)!!})'. 's')
            ->replace($text)
            ->callback(function (Detail $match)  use ($data) {
                if ($match->matched('simple')) {
                    return $this->evalMatch($match->get("simple"), $data);
                }

                if ($match->matched('safe')) {
                    return $this->evalMatchSafely($match->get("safe"), $data);
                }

                return "[#ERROR_MATCH]";
            });
    }

    private function evalMatchSafely(string $match, array $data): string
    {
        return htmlspecialchars($this->evalMatch($match, $data));
    }

    private function evalMatch(string $match, array $data): string
    {
        $result = trim($match);
        $result = ltrim($result, "$");
        $result = str_replace(["->"], ["."], $result);

        try {
            return $this->expressionLanguage->evaluate($result, $data) ?? "";
        } catch (SyntaxError $e) {
            return "[#ERROR_SYNTAX]";
        }
    }
}
