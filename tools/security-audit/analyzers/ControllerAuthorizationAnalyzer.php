<?php

declare(strict_types=1);

namespace CAS\SecurityAudit\Analyzers;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

final class ControllerAuthorizationAnalyzer
{
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

        $methods = [];
        $namespace = '';

        $visitor = new class($methods, $namespace, $file) extends NodeVisitorAbstract {
            private array $methods;
            private string $namespace;
            private string $file;
            private ?string $className = null;

            public function __construct(array &$methods, string &$namespace, string $file)
            {
                $this->methods =& $methods;
                $this->namespace =& $namespace;
                $this->file = $file;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Namespace_) {
                    $this->namespace = $node->name?->toString() ?? '';
                }

                if ($node instanceof Node\Stmt\Class_
                    || $node instanceof Node\Stmt\Trait_
                    || $node instanceof Node\Stmt\Enum_) {
                    if ($node->name !== null) {
                        $this->className = ltrim(
                            $this->namespace . '\\' . $node->name->toString(),
                            '\\'
                        );
                    }
                }

                if (!$node instanceof Node\Stmt\ClassMethod || $this->className === null) {
                    return null;
                }

                $calls = [];
                $callVisitor = new class($calls) extends NodeVisitorAbstract {
                    private array $calls;

                    public function __construct(array &$calls)
                    {
                        $this->calls =& $calls;
                    }

                    public function enterNode(Node $node)
                    {
                        $name = null;
                        $kind = null;

                        if ($node instanceof Expr\MethodCall
                            && $node->name instanceof Node\Identifier) {
                            $name = strtolower($node->name->toString());
                            $kind = 'method';
                        } elseif ($node instanceof Expr\StaticCall
                            && $node->name instanceof Node\Identifier) {
                            $name = strtolower($node->name->toString());
                            $kind = 'static';
                        } elseif ($node instanceof Expr\FuncCall
                            && $node->name instanceof Node\Name) {
                            $name = strtolower($node->name->toString());
                            $kind = 'function';
                        }

                        if ($name === null) {
                            return null;
                        }

                        $authorizationNames = [
                            'can', 'cannot', 'allows', 'isallowed',
                            'authorize', 'requirepermission',
                            'haspermission', 'checkpermission',
                        ];

                        $authorizationClasses = [
                            'authorizationservice',
                            'permissiongate',
                            'permissionmiddleware',
                        ];

                        $className = null;

                        if ($node instanceof Expr\StaticCall
                            && $node->class instanceof Node\Name) {
                            $className = strtolower($node->class->getLast());
                        }

                        $isAuthorizationCall =
                            in_array($name, $authorizationNames, true)
                            || (
                                $kind === 'static'
                                && in_array($className, $authorizationClasses, true)
                            );

                        if ($isAuthorizationCall) {
                            $this->calls[] = [
                                'name' => $name,
                                'kind' => $kind,
                                'class' => $className,
                                'line' => $node->getStartLine(),
                            ];
                        }

                        return null;
                    }
                };

                if ($node->stmts !== null) {
                    $traverser = new NodeTraverser();
                    $traverser->addVisitor($callVisitor);
                    $traverser->traverse($node->stmts);
                }

                $this->methods[] = [
                    'class' => $this->className,
                    'short_class' => substr(
                        $this->className,
                        strrpos('\\' . $this->className, '\\')
                    ),
                    'method' => $node->name->toString(),
                    'line' => $node->getStartLine(),
                    'authorization_calls' => $calls,
                    'has_explicit_authorization' => count($calls) > 0,
                    'file' => $this->file,
                ];

                return null;
            }

            public function leaveNode(Node $node)
            {
                if ($node instanceof Node\Stmt\Class_
                    || $node instanceof Node\Stmt\Trait_
                    || $node instanceof Node\Stmt\Enum_) {
                    $this->className = null;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $methods;
    }
}
