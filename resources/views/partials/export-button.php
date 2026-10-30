<?php
/**
 * Reusable "Export CSV" button for list toolbars.
 *
 * $entity  — e.g. 'candidates'
 * $filters — associative array of current filters (e.g. ['q'=>'acme','status'=>'active'])
 *
 * Any empty/null/'all'/0 value is dropped from the query string.
 */
$filters = $filters ?? [];
$query   = array_filter($filters, static function ($v) {
    return $v !== '' && $v !== null && $v !== 0 && $v !== '0' && $v !== 'all';
});
$qs  = http_build_query($query);
$url = '/' . rawurlencode($entity) . '/export.csv' . ($qs !== '' ? '?' . $qs : '');
?>
<a href="<?= e($url) ?>" class="ra-btn ra-btn-outline ra-btn-sm" title="Export filtered rows as CSV">
    <i class="bi bi-download"></i>
    <span>Export CSV</span>
</a>
