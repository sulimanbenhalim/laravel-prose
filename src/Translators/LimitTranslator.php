<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

use SulimanBenhalim\Prose\Support\Inflector;

class LimitTranslator
{
    public function __construct(
        private Inflector $inflector,
        private array $config
    ) {}

    public function translate(?int $limit, ?int $offset): string
    {
        $parts = [];

        if ($limit !== null) {
            $limitConnector = $this->config['connectors']['limit'] ?? 'first';
            $parts[] = "{$limitConnector} {$limit} results";
        }

        if ($offset !== null && $offset > 0) {
            $offsetConnector = $this->config['connectors']['offset'] ?? 'starting from position';
            $parts[] = "{$offsetConnector} {$offset}";
        }

        return $this->inflector->joinWithConnector($parts, 'and');
    }
}
