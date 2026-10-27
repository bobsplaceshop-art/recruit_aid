<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

/**
 * Contract every custom-field input type must fulfill.
 * The system uses this to render, validate, store, and display values.
 */
interface InputType
{
    /** Machine name, e.g. 'text', 'date', 'select' */
    public function key(): string;

    /** Human-readable name shown in the field builder, e.g. 'Text', 'Date' */
    public function label(): string;

    /** Does this type need an options list? (true for select/radio/multi-select) */
    public function supportsOptions(): bool;

    /**
     * Extra configuration inputs the field builder should show
     * for this type. E.g. Number might return ['min', 'max', 'decimals'].
     *
     * Return an array of ['key' => 'min', 'label' => 'Minimum value', 'type' => 'number'].
     *
     * @return array<int, array{key:string, label:string, type:string, help?:string}>
     */
    public function configFields(): array;

    /**
     * Validate a submitted value. Return an empty array if valid,
     * or an array of error messages.
     *
     * @return string[]
     */
    public function validate(mixed $value, array $constraints, bool $isRequired): array;

    /**
     * Convert the submitted value to the string we store in the DB.
     * E.g. checkbox => '1' or '0', multi-select => JSON array, everything else => string.
     */
    public function cast(mixed $value): ?string;

    /**
     * Display a stored value for read-only contexts (detail pages, lists, exports).
     */
    public function humanize(?string $value): string;

    /**
     * Return the HTML for the form input.
     *
     * @param string       $fieldKey      e.g. 'rwanda_work_permit_status'
     * @param string       $fieldLabel    e.g. 'Rwanda work permit status'
     * @param string|null  $currentValue  raw stored value (from custom_field_values)
     * @param array        $constraints   decoded from custom_fields.constraints
     * @param string[]     $options       decoded from custom_fields.options
     * @param string|null  $helpText
     * @param bool         $isRequired
     * @param string[]     $errors        validation messages (may be empty)
     */
    public function render(
        string $fieldKey,
        string $fieldLabel,
        ?string $currentValue,
        array $constraints,
        array $options,
        ?string $helpText,
        bool $isRequired,
        array $errors = []
    ): string;
}
