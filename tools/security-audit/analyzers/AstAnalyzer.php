<?php

declare(strict_types=1);

namespace CAS\SecurityAudit\Analyzers;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final class AstAnalyzer
{
    /**
     * Parse one PHP source file and return its structural inventory.
     *
     * @return array{
     *   classes: list<array{name:string,type:string,line:int}>,
     *   methods: list<array{name:string,class:?string,line:int}>,
     *   functions: list<array{name:string,line:int}>,
     *   calls: list<array{name:string,kind:string,line:int,category:string}>,
     *   sql_literals: list<array{value:string,line:int}>
     * }
     */
    public function analyze(string $source): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($source);

        if ($ast === null) {
            return $this->emptyResult();
        }

        $result = $this->emptyResult();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($result) extends NodeVisitorAbstract {
            /** @var array<string, mixed> */
            private array $result;

            private ?string $currentClass = null;

            public function __construct(array &$result)
            {
                $this->result = &$result;
            }

            public function enterNode(Node $node): ?Node
            {
                $line = $node->getStartLine();

                if ($node instanceof Node\Stmt\ClassLike) {
                    $name = $node->name?->toString() ?? '{anonymous}';

                    $type = match (true) {
                        $node instanceof Node\Stmt\Class_ => 'class',
                        $node instanceof Node\Stmt\Interface_ => 'interface',
                        $node instanceof Node\Stmt\Trait_ => 'trait',
                        $node instanceof Node\Stmt\Enum_ => 'enum',
                        default => 'class-like',
                    };

                    $this->result['classes'][] = [
                        'name' => $name,
                        'type' => $type,
                        'line' => $line,
                    ];

                    $this->currentClass = $name;
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    $this->result['methods'][] = [
                        'name' => $node->name->toString(),
                        'class' => $this->currentClass,
                        'line' => $line,
                    ];
                }

                if ($node instanceof Node\Stmt\Function_) {
                    $this->result['functions'][] = [
                        'name' => $node->name->toString(),
                        'line' => $line,
                    ];
                }

                if ($node instanceof Node\Expr\FuncCall) {
                    $name = $this->callName($node->name);
                    $this->recordCall($name, 'function', $line);
                    $this->extractSql($node->args, $line);
                }

                if ($node instanceof Node\Expr\MethodCall) {
                    $name = $this->callName($node->name);
                    $this->recordCall($name, 'method', $line);
                    $this->extractSql($node->args, $line);
                }

                if ($node instanceof Node\Expr\StaticCall) {
                    $name = $this->callName($node->name);
                    $this->recordCall($name, 'static', $line);
                    $this->extractSql($node->args, $line);
                }

                return null;
            }

            public function leaveNode(Node $node): ?Node
            {
                if ($node instanceof Node\Stmt\ClassLike) {
                    $this->currentClass = null;
                }

                return null;
            }

            private function callName(Node $name): string
            {
                if ($name instanceof Node\Identifier) {
                    return $name->toString();
                }

                if ($name instanceof Node\Name) {
                    return $name->toString();
                }

                return '{dynamic}';
            }

            private function recordCall(string $name, string $kind, int $line): void
            {
                $lower = strtolower($name);

                $category = match (true) {
                    in_array($lower, [
                        'query', 'select', 'selectone', 'scalarint',
                        'execute', 'insert', 'prepare', 'exec',
                    ], true) => 'database',

                    in_array($lower, [
                        'can', 'cannot', 'authorize', 'allows',
                        'requirepermission', 'isallowed',
                    ], true) => 'authorization',

                    in_array($lower, [
                        'dispatch', 'publish', 'processpending',
                        'handle', 'invoke',
                    ], true) => 'event',

                    in_array($lower, [
                        'move_uploaded_file', 'store', 'findbyuuid',
                        'absolutepath', 'file', 'download',
                    ], true) => 'file',

                    in_array($lower, [
                        'get', 'post', 'put', 'patch', 'delete',
                        'any', 'map',
                    ], true) => 'route-candidate',

                    default => 'other',
                };

                $this->result['calls'][] = [
                    'name' => $name,
                    'kind' => $kind,
                    'line' => $line,
                    'category' => $category,
                ];
            }

            /**
             * @param list<Node\Arg> $args
             */
            private function extractSql(array $args, int $line): void
            {
                foreach ($args as $arg) {
                    $this->extractSqlNode($arg->value, $line);
                }
            }

            private function extractSqlNode(Node $node, int $line): void
            {
                if ($node instanceof Node\Scalar\String_) {
                    $value = $node->value;

                    if (preg_match(
                        '/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i',
                        $value
                    )) {
                        $this->result['sql_literals'][] = [
                            'value' => $value,
                            'line' => $node->getStartLine() ?: $line,
                        ];
                    }

                    return;
                }

                if ($node instanceof Node\Scalar\Encapsed) {
                    foreach ($node->parts as $part) {
                        if ($part instanceof Node\InterpolatedStringPart) {
                            $value = $part->value;

                            if (preg_match(
                                '/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i',
                                $value
                            )) {
                                $this->result['sql_literals'][] = [
                                    'value' => $value,
                                    'line' => $part->getStartLine() ?: $line,
                                ];
                            }
                        }
                    }

                    return;
                }

                if ($node instanceof Node\Expr\BinaryOp\Concat) {
                    $this->extractSqlNode($node->left, $line);
                    $this->extractSqlNode($node->right, $line);
                }
            }
        });

        $traverser->traverse($ast);

        return $result;
    }

    /**
     * @return array{
     *   classes: list<array{name:string,type:string,line:int}>,
     *   methods: list<array{name:string,class:?string,line:int}>,
     *   functions: list<array{name:string,line:int}>,
     *   calls: list<array{name:string,kind:string,line:int,category:string}>,
     *   sql_literals: list<array{value:string,line:int}>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'classes' => [],
            'methods' => [],
            'functions' => [],
            'calls' => [],
            'sql_literals' => [],
        ];
    }
}
