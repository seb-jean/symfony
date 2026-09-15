<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides locale-aware sorting using the ICU Collator.
 *
 * When a TranslatorInterface is available, values implementing
 * TranslatableInterface are automatically sorted by their translation.
 *
 * @author Sébastien Jean <sebastien.jean76@gmail.com>
 */
final class Collator
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Sorts an array of values using locale-aware comparison.
     *
     * When no $key callable is provided:
     *  - TranslatableInterface values are sorted by their translation (requires a TranslatorInterface)
     *  - Other values are sorted by their string cast
     *
     * @template T
     *
     * @param T[]                      $values The values to sort
     * @param (callable(T): string)|null $key    A callable that extracts the comparison string from each value
     *
     * @return list<T>
     */
    public function sort(array $values, ?callable $key = null, ?string $locale = null): array
    {
        if (!$values) {
            return [];
        }

        $collator = new \Collator($locale ?? \Locale::getDefault());

        if (null === $key && null !== $this->translator && reset($values) instanceof TranslatableInterface) {
            $translator = $this->translator;
            $key = static fn (TranslatableInterface $v): string => $v->trans($translator, $locale);
        }

        if (null === $key) {
            usort($values, static fn (string $a, string $b): int => $collator->compare($a, $b) ?: 0);

            return $values;
        }

        $decorated = [];
        foreach ($values as $i => $v) {
            $decorated[] = [$key($v), $i, $v];
        }

        usort($decorated, static fn (array $a, array $b): int => $collator->compare($a[0], $b[0]) ?: $a[1] <=> $b[1]);

        return array_column($decorated, 2);
    }

    /**
     * Sorts an associative array by its keys using locale-aware comparison.
     *
     * @param array<string, mixed> $values The associative array to sort
     *
     * @return array<string, mixed>
     */
    public function sortKeys(array $values, ?string $locale = null): array
    {
        if (!$values) {
            return [];
        }

        $collator = new \Collator($locale ?? \Locale::getDefault());
        uksort($values, static fn (string $a, string $b): int => $collator->compare($a, $b) ?: 0);

        return $values;
    }
}
