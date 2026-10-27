<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

use CorporateAid\Platform\CustomFields\InputTypes\CheckboxType;
use CorporateAid\Platform\CustomFields\InputTypes\DateType;
use CorporateAid\Platform\CustomFields\InputTypes\EmailType;
use CorporateAid\Platform\CustomFields\InputTypes\FileType;
use CorporateAid\Platform\CustomFields\InputTypes\LongTextType;
use CorporateAid\Platform\CustomFields\InputTypes\MultiSelectType;
use CorporateAid\Platform\CustomFields\InputTypes\NumberType;
use CorporateAid\Platform\CustomFields\InputTypes\PhoneType;
use CorporateAid\Platform\CustomFields\InputTypes\SelectType;
use CorporateAid\Platform\CustomFields\InputTypes\TextType;
use CorporateAid\Platform\CustomFields\InputTypes\UrlType;
use CorporateAid\Platform\CustomFields\InputTypes\YesNoType;

/**
 * Central lookup for input types.
 * Add a new input type here and it becomes available everywhere
 * (field builder, validation, rendering) automatically.
 */
final class InputTypeRegistry
{
    /** @var array<string, class-string<InputType>> */
    private const TYPES = [
        'text'         => TextType::class,
        'long_text'    => LongTextType::class,
        'number'       => NumberType::class,
        'date'         => DateType::class,
        'email'        => EmailType::class,
        'phone'        => PhoneType::class,
        'url'          => UrlType::class,
        'select'       => SelectType::class,
        'multi_select' => MultiSelectType::class,
        'checkbox'     => CheckboxType::class,
        'yes_no'       => YesNoType::class,
        'file'         => FileType::class,
    ];

    /** @var array<string, InputType> */
    private static array $instances = [];

    public static function has(string $key): bool
    {
        return isset(self::TYPES[$key]);
    }

    public static function get(string $key): InputType
    {
        if (!isset(self::TYPES[$key])) {
            throw new \InvalidArgumentException("Unknown input type: {$key}");
        }
        if (!isset(self::$instances[$key])) {
            $class = self::TYPES[$key];
            self::$instances[$key] = new $class();
        }
        return self::$instances[$key];
    }

    /** @return array<string, InputType> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::TYPES) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    /**
     * For the field builder's dropdown.
     *
     * @return array<int, array{key:string, label:string}>
     */
    public static function optionsForSelect(): array
    {
        $out = [];
        foreach (self::all() as $key => $type) {
            $out[] = ['key' => $key, 'label' => $type->label()];
        }
        return $out;
    }
}
