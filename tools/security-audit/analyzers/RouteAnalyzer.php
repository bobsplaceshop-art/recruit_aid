<?php

declare(strict_types=1);

namespace CAS\SecurityAudit\Analyzers;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final class RouteAnalyzer
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'any', 'map'];

    public function analyzeFile(string $file): array
    {
        $source = file_get_contents($file);

        if ($source === false) {
            return [];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($source);
        } catch (\Throwable) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $routes = [];

        $visitor = new class($routes) extends NodeVisitorAbstract {
            private array $routes;

            public function __construct(array &$routes)
            {
                $this->routes =& $routes;
            }

            public function enterNode(Node $node)
            {
                if (!$node instanceof Expr\MethodCall) {
                    return null;
                }

                if (!$node->name instanceof Node\Identifier) {
                    return null;
                }

                $method = strtolower($node->name->toString());

                if (!in_array($method, RouteAnalyzer::methods(), true)) {
                    return null;
                }

                // Restrict matching to calls made on a variable named $router.
                if (!$node->var instanceof Expr\Variable
                    || $node->var->name !== 'router') {
                    return null;
                }

                $args = $node->getArgs();

                $path = isset($args[0])
                    ? RouteAnalyzer::stringValue($args[0]->value)
                    : null;

                $handler = isset($args[1])
                    ? RouteAnalyzer::handlerValue($args[1]->value)
                    : ['class' => null, 'method' => null];

                $middleware = isset($args[2])
                    ? RouteAnalyzer::middlewareValue($args[2]->value)
                    : ['items' => [], 'known' => true];

                $this->routes[] = [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'handler' => $handler,
                    'middleware' => $middleware['items'],
                    'middleware_known' => $middleware['known'],
                    'file' => '',
                    'line' => $node->getStartLine(),
                ];

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        foreach ($routes as &$route) {
            $route['file'] = $file;
        }
        unset($route);

        return $routes;
    }

    public static function methods(): array
    {
        return self::METHODS;
    }

    public static function stringValue(Expr $expr): ?string
    {
        if ($expr instanceof Scalar\String_) {
            return $expr->value;
        }

        return null;
    }

    public static function handlerValue(Expr $expr): array
    {
        if (!$expr instanceof Expr\Array_) {
            return ['class' => null, 'method' => null];
        }

        $values = [];

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            $value = $item->value;

            if ($value instanceof Expr\ClassConstFetch
                && strtolower($value->name->toString()) === 'class') {
                $values[] = $value->class->toString();
            } elseif ($value instanceof Scalar\String_) {
                $values[] = $value->value;
            } else {
                $values[] = null;
            }
        }

        return [
            'class' => $values[0] ?? null,
            'method' => $values[1] ?? null,
        ];
    }

    public static function middlewareValue(Expr $expr): array
    {
        if (!$expr instanceof Expr\Array_) {
            return ['items' => [], 'known' => false];
        }

        $items = [];
        $known = true;

        foreach ($expr->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->value instanceof Scalar\String_) {
                $items[] = $item->value->value;
            } else {
                $known = false;
            }
        }

        return ['items' => $items, 'known' => $known];
    }
}
