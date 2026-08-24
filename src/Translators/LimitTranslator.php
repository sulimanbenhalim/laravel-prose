<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Translators;

class LimitTranslator
{
    public function __construct(
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
            $parts[] = "skipping the first {$offset}";
        }

        return implode(', ', $parts);
    }
}
