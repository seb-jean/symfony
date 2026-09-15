<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Collator;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CollatorTest extends TestCase
{
    #[DataProvider('provideSortData')]
    public function testSort(array $expected, array $values, ?string $locale = 'en')
    {
        $this->assertSame($expected, (new Collator())->sort($values, locale: $locale));
    }

    public static function provideSortData(): iterable
    {
        yield 'French names' => [
            ['Benoît', 'Éric', 'François', 'Jérôme'],
            ['François', 'Éric', 'Jérôme', 'Benoît'],
            'fr_FR',
        ];

        yield 'German umlauts' => [
            ['Ärger', 'Öl', 'Über'],
            ['Über', 'Ärger', 'Öl'],
            'de_DE',
        ];

        yield 'accented words with common prefix' => [
            ['cote', 'coté', 'côte', 'côté'],
            ['côté', 'cote', 'côte', 'coté'],
            'fr_FR',
        ];

        yield 'empty array' => [[], []];

        yield 'single element' => [['Strasbourg'], ['Strasbourg']];
    }

    public function testSortWithKeyExtractor()
    {
        $items = [
            ['city' => 'Zürich'],
            ['city' => 'Ärau'],
            ['city' => 'Bern'],
        ];

        $sorted = (new Collator())->sort($items, static fn (array $item): string => $item['city'], 'de_CH');

        $this->assertSame('Ärau', $sorted[0]['city']);
        $this->assertSame('Bern', $sorted[1]['city']);
        $this->assertSame('Zürich', $sorted[2]['city']);
    }

    public function testSortIsStable()
    {
        $paris = ['city' => 'Paris', 'zip' => '75001'];
        $lyon = ['city' => 'Paris', 'zip' => '69001'];
        $marseille = ['city' => 'Marseille', 'zip' => '13001'];

        $sorted = (new Collator())->sort([$paris, $lyon, $marseille], static fn (array $item): string => $item['city'], 'fr_FR');

        $this->assertSame([$marseille, $paris, $lyon], $sorted);
    }

    public function testSortUsesDefaultLocale()
    {
        $previous = \Locale::getDefault();

        try {
            \Locale::setDefault('fr_FR');
            $this->assertSame(['àbord', 'accent', 'élan'], (new Collator())->sort(['élan', 'àbord', 'accent']));
        } finally {
            \Locale::setDefault($previous);
        }
    }

    public function testSortTranslatableValues()
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnMap([
                ['color.red', [], null, 'fr', 'Rouge'],
                ['color.green', [], null, 'fr', 'Vert'],
                ['color.blue', [], null, 'fr', 'Bleu'],
            ]);

        $red = self::createTranslatable('color.red');
        $green = self::createTranslatable('color.green');
        $blue = self::createTranslatable('color.blue');

        $sorted = (new Collator($translator))->sort([$red, $green, $blue], locale: 'fr');

        $this->assertSame([$blue, $red, $green], $sorted);
    }

    public function testSortTranslatableValuesWithoutTranslatorUsesDirectComparison()
    {
        $result = (new Collator())->sort(['Genève', 'Bâle', 'Lausanne'], locale: 'fr_CH');

        $this->assertSame(['Bâle', 'Genève', 'Lausanne'], $result);
    }

    #[DataProvider('provideSortKeysData')]
    public function testSortKeys(array $expected, array $values, ?string $locale = 'en')
    {
        $this->assertSame($expected, (new Collator())->sortKeys($values, $locale));
    }

    public static function provideSortKeysData(): iterable
    {
        yield 'French city labels' => [
            ['Bâle' => 'BS', 'Genève' => 'GE', 'Zürich' => 'ZH'],
            ['Zürich' => 'ZH', 'Bâle' => 'BS', 'Genève' => 'GE'],
            'fr_CH',
        ];

        yield 'preserves values' => [
            ['Ärau' => 1, 'Bern' => 2, 'Zürich' => 3],
            ['Zürich' => 3, 'Ärau' => 1, 'Bern' => 2],
            'de_CH',
        ];

        yield 'empty array' => [[], []];
    }

    public function testSortWithKeyExtractorTakesPrecedenceOverTranslatableDetection()
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static fn (): string => throw new \LogicException('Translator should not be called.'));

        $a = self::createTranslatable('z_last');
        $b = self::createTranslatable('a_first');

        $sorted = (new Collator($translator))->sort([$a, $b], static fn (TranslatableInterface $v): string => 'same', 'en');

        $this->assertSame([$a, $b], $sorted);
    }

    private static function createTranslatable(string $id): TranslatableInterface
    {
        return new class($id) implements TranslatableInterface {
            public function __construct(private readonly string $id)
            {
            }

            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return $translator->trans($this->id, [], null, $locale);
            }
        };
    }
}
