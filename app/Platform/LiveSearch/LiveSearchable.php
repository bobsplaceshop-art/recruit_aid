<?php
declare(strict_types=1);

namespace CorporateAid\Platform\LiveSearch;

/**
 * Contract for entities that support reusable live search.
 * Each repository implements this and provides a config array.
 */
interface LiveSearchable
{
    /**
     * @return array{
     *   table: string,
     *   alias: string,
     *   columns: string[],
     *   order: string,
     *   permission: string,
     *   baseFilters: string[],
     *   joins: string[],
     *   rowView: string,
     *   extraColumns: string[]
     * }
     */
    public function liveSearchConfig(): array;
}
