<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Services;

use CorporateAid\Platform\Repositories\LookupRepository;

/**
 * Provides cached access to lookup values for the request lifecycle.
 * Forms and validators read from here instead of hardcoded arrays.
 */
final class LookupService
{
    /** @var array<string, array<string, string>> */
    private array $cache = [];

    public function __construct(private LookupRepository $repo) {}

    /**
     * All active items in a group as [slug => label] for use in
     * <select> dropdowns and validation whitelists.
     *
     * @return array<string, string>
     */
    public function allFor(string $groupSlug): array
    {
        if (isset($this->cache[$groupSlug])) {
            return $this->cache[$groupSlug];
        }

        $items = $this->repo->activeFor($groupSlug);
        $out = [];
        foreach ($items as $item) {
            $out[$item['slug']] = $item['label'];
        }
        $this->cache[$groupSlug] = $out;
        return $out;
    }

    /** Check if a slug is a valid active value in a group. */
    public function isValid(string $groupSlug, string $slug): bool
    {
        return array_key_exists($slug, $this->allFor($groupSlug));
    }

    /**
     * Full rows for admin UI.
     *
     * @return array<int, array{id:int, group_slug:string, slug:string, label:string, description:?string, sort_order:int, is_active:bool, is_system:bool}>
     */
    public function itemsFor(string $groupSlug): array
    {
        return $this->repo->activeFor($groupSlug);
    }

    /**
     * All groups with counts, for /admin/lookups.
     *
     * @return array<int, array{group_slug:string, item_count:int}>
     */
    public function allGroups(): array
    {
        return $this->repo->allActiveGroups();
    }

    /**
     * Human-readable label for a group slug.
     * Falls back to Title Case of the slug if no explicit label.
     */
    public function groupLabel(string $groupSlug): string
    {
        $labels = [
            'work_mode'       => 'Work Mode',
            'employment_type' => 'Employment Type',
            'seniority'       => 'Seniority Level',
        ];
        return $labels[$groupSlug] ?? ucwords(str_replace('_', ' ', $groupSlug));
    }
}
