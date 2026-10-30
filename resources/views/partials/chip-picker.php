<?php
/**
 * Reusable chip-picker partial.
 *
 * Renders a searchable chip-picker with:
 *   - Chips of currently selected items (each with a × remove button)
 *   - A search input that filters a dropdown of available items
 *   - Hidden inputs for the selected item IDs (name given by $name)
 *   - Inline JS to wire filter / add / remove
 *
 * Params:
 *   $id           — DOM id for this picker (must be unique on the page)
 *   $name         — hidden input name, e.g. 'recruiter_ids[]'
 *   $items        — array<int, array{id:int, label:string, sublabel?:string}>
 *   $selected     — int[] currently selected IDs
 *   $mode         — 'single' (only one) or 'multi' (many)
 *   $placeholder  — search input placeholder
 *   $required     — bool; if true, at least one hidden input must exist
 */
$id          = $id          ?? 'picker-' . uniqid();
$name        = $name        ?? 'picker_ids[]';
$items       = $items       ?? [];
$selected    = $selected    ?? [];
$mode        = $mode        ?? 'multi';
$placeholder = $placeholder ?? 'Search…';
$required    = $required    ?? false;

$selectedMap = [];
foreach ($selected as $sid) {
    $selectedMap[(int) $sid] = true;
}
?>
<div class="ra-chip-picker" data-picker-id="<?= e($id) ?>" data-mode="<?= e($mode) ?>" data-name="<?= e($name) ?>"<?= $required ? ' data-required="1"' : '' ?>>
    <div class="ra-chip-area mb-2" data-chips>
        <?php foreach ($items as $item): ?>
            <?php if (isset($selectedMap[(int) $item['id']])): ?>
                <span class="ra-chip" data-id="<?= (int) $item['id'] ?>">
                    <?= e($item['label']) ?>
                    <button type="button" class="ra-chip-remove" aria-label="Remove">&times;</button>
                </span>
                <input type="hidden" name="<?= e($name) ?>" value="<?= (int) $item['id'] ?>" data-for-id="<?= (int) $item['id'] ?>">
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="ra-participant-picker">
        <input
            type="text"
            class="ra-input"
            placeholder="<?= e($placeholder) ?>"
            autocomplete="off"
            data-search
        >
        <div class="ra-participant-dropdown" hidden data-dropdown>
            <?php foreach ($items as $item): ?>
                <?php $isSel = isset($selectedMap[(int) $item['id']]); ?>
                <button
                    type="button"
                    class="ra-participant-option <?= $isSel ? 'is-disabled' : '' ?>"
                    data-id="<?= (int) $item['id'] ?>"
                    data-label="<?= e($item['label']) ?>"
                    <?= $isSel ? 'disabled' : '' ?>
                >
                    <span class="ra-participant-name"><?= e($item['label']) ?></span>
                    <?php if (!empty($item['sublabel'])): ?>
                        <span class="ra-participant-email muted small"><?= e($item['sublabel']) ?></span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
