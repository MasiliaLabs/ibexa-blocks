<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Form;

/** The valid email addresses in the value of a field: a string, a list, or authors as [name => address]. */
final class ContactAddresses
{
    /**
     * @return list<string>
     */
    public static function from(mixed $value): array
    {
        $found = [];
        foreach (is_array($value) ? [...array_keys($value), ...array_values($value)] : [$value] as $candidate) {
            if (is_string($candidate) && filter_var(trim($candidate), FILTER_VALIDATE_EMAIL) !== false) {
                $found[] = trim($candidate);
            }
        }

        return array_values(array_unique($found));
    }
}
