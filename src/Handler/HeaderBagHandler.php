<?php

namespace Psalm\SymfonyPsalmPlugin\Handler;

use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\String_;
use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\Plugin\Hook\MethodReturnTypeProviderInterface;
use Psalm\StatementsSource;
use Psalm\Type;
use Psalm\Type\Atomic\TArray;
use Psalm\Type\Atomic\TInt;
use Psalm\Type\Atomic\TNull;
use Psalm\Type\Atomic\TString;
use Psalm\Type\TaintKindGroup;
use Psalm\Type\Union;
use Symfony\Component\HttpFoundation\HeaderBag;

class HeaderBagHandler implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [
            HeaderBag::class,
        ];
    }

    public static function getMethodReturnType(
        StatementsSource $source,
        string $fq_classlike_name,
        string $method_name_lowercase,
        array $call_args,
        Context $context,
        CodeLocation $code_location,
        ?array $template_type_parameters = null,
        ?string $called_fq_classlike_name = null,
        ?string $called_method_name_lowercase = null
    ): ?Type\Union {
        if (HeaderBag::class !== $fq_classlike_name) {
            return null;
        }

        if ('get' !== $method_name_lowercase) {
            return null;
        }

        $type = static::makeReturnType($call_args);

        if ($call_args[0]->value instanceof String_ && 'user-agent' === $call_args[0]->value->value) {
            $uniqId = $source->getFileName().':'.$code_location->getLineNumber().'-'.$code_location->getColumn();
            $source->getCodebase()->addTaintSource(
                $type,
                'tainted-'.$uniqId,
                TaintKindGroup::ALL_INPUT,
                $code_location
            );
        }

        return $type;
    }

    /**
     * @param array<\PhpParser\Node\Arg> $call_args
     */
    private static function makeReturnType(array $call_args): Union
    {
        if (3 === count($call_args) && (($arg = $call_args[2]->value) instanceof ConstFetch) && 'false' === $arg->name->parts[0]) {
            return new Union([new TArray([new Union([new TInt()]), new Union([new TString()])])]);
        }

        if (isset($call_args[1])) {
            if ($call_args[1]->value instanceof String_) {
                return new Union([new TString()]);
            }
        }

        return new Union([new TString(), new TNull()]);
    }
}
