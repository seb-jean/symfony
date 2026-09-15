<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Tests\Fixtures\TranslatableTextAlign;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The French collation used here orders the translated labels differently than
 * a byte comparison would, which is what makes the sorting observable.
 */
#[RequiresPhpExtension('intl')]
class ChoiceTypeLocalizedSortTest extends TypeTestCase
{
    public const TESTED_TYPE = ChoiceType::class;

    private const TRANSLATIONS = [
        'zebra' => 'Zèbre',
        'eclair' => 'Éclair',
        'emu' => 'Emu',
        'Left' => 'Zèbre',
        'Center' => 'Âne',
        'Right' => 'Ours',
        'animals' => 'Zoo',
        'food' => 'Épicerie',
    ];

    private array $choices = [
        'zebra' => 'z',
        'emu' => 'm',
        'eclair' => 'e',
    ];

    private string $defaultLocale;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('fr');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);

        parent::tearDown();
    }

    protected function getExtensions(?ViolationMapperInterface $violationMapper = null): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(static fn (string $id): string => self::TRANSLATIONS[$id] ?? $id);

        return array_merge(parent::getExtensions($violationMapper), [new CoreExtension(null, null, $translator)]);
    }

    public function testChoicesAreNotSortedByDefault()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
        ])->createView();

        $this->assertSame(['zebra', 'emu', 'eclair'], self::labels($view->vars['choices']));
    }

    public function testChoicesAreSortedByTranslatedLabel()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'sort_localized' => true,
        ])->createView();

        // "Éclair" < "Emu" < "Zèbre" in French, while a byte comparison would
        // put "Éclair" last
        $this->assertSame(['eclair', 'emu', 'zebra'], self::labels($view->vars['choices']));
    }

    public function testChoiceKeysArePreserved()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'sort_localized' => true,
        ])->createView();

        // The keys are the child names of expanded forms, so they must follow
        // their choice instead of being renumbered
        $this->assertSame([2, 1, 0], array_keys($view->vars['choices']));
    }

    public function testEnumChoicesAreSortedByTranslatedLabel()
    {
        $view = $this->factory->create(EnumType::class, null, [
            'class' => TranslatableTextAlign::class,
            'sort_localized' => true,
        ])->createView();

        $data = array_map(static fn (ChoiceView $choiceView) => $choiceView->data, $view->vars['choices']);

        $this->assertSame([
            TranslatableTextAlign::Center,  // Âne
            TranslatableTextAlign::Right,   // Ours
            TranslatableTextAlign::Left,    // Zèbre
        ], array_values($data));
    }

    public function testExpandedChildrenAreSorted()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'expanded' => true,
            'sort_localized' => true,
        ])->createView();

        // Expanded choices are rendered by iterating over the children, not
        // over the "choices" variable
        $this->assertSame([2, 1, 0], array_keys($view->children));
    }

    public function testPlaceholderStaysFirstWhenExpanded()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'expanded' => true,
            'required' => false,
            'placeholder' => 'Choose',
            'sort_localized' => true,
        ])->createView();

        $this->assertSame(['placeholder', 2, 1, 0], array_keys($view->children));
    }

    public function testEmptyValuedChoiceStaysFirst()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => ['zebra' => '', 'emu' => 'm', 'eclair' => 'e'],
            'sort_localized' => true,
        ])->createView();

        // The choice acts as the placeholder, which ChoiceListView::hasPlaceholder()
        // only detects on the first element
        $this->assertSame(['zebra', 'eclair', 'emu'], self::labels($view->vars['choices']));
        $this->assertTrue($view->vars['placeholder_in_choices']);
    }

    public function testPreferredChoicesAreSortedSeparately()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'preferred_choices' => ['z', 'e'],
            'sort_localized' => true,
        ])->createView();

        $this->assertSame(['eclair', 'zebra'], self::labels($view->vars['preferred_choices']));
        $this->assertSame(['eclair', 'emu', 'zebra'], self::labels($view->vars['choices']));
    }

    public function testGroupsAndTheirChoicesAreSorted()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => [
                'animals' => ['zebra' => 'z', 'emu' => 'm'],
                'food' => ['eclair' => 'e'],
            ],
            'sort_localized' => true,
        ])->createView();

        // "Épicerie" < "Zoo"
        $this->assertSame(['food', 'animals'], array_keys($view->vars['choices']));

        $animals = $view->vars['choices']['animals'];
        $this->assertInstanceOf(ChoiceGroupView::class, $animals);
        $this->assertSame(['emu', 'zebra'], self::labels($animals->choices));
    }

    public function testLabelsAreNotTranslatedWhenTranslationDomainIsFalse()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'choice_translation_domain' => false,
            'sort_localized' => true,
        ])->createView();

        // Sorted on the raw labels, as those are what gets rendered
        $this->assertSame(['eclair', 'emu', 'zebra'], self::labels($view->vars['choices']));
    }

    public function testChoicesWithoutLabelAreSortedFirst()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'choice_label' => static fn (string $choice): string|false => 'm' === $choice ? false : $choice,
            'sort_localized' => true,
        ])->createView();

        $labels = array_map(static fn (ChoiceView $choiceView) => $choiceView->label, $view->vars['choices']);

        $this->assertSame([false, 'e', 'z'], array_values($labels));
    }

    public function testEmptyChoiceList()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => [],
            'sort_localized' => true,
        ])->createView();

        $this->assertSame([], $view->vars['choices']);
    }

    public function testSortLocalizedMustBeABoolean()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'sort_localized' => 'fr',
        ]);
    }

    /**
     * @param array<ChoiceGroupView|ChoiceView> $choiceViews
     *
     * @return list<string>
     */
    private static function labels(array $choiceViews): array
    {
        return array_values(array_map(static fn (ChoiceView $choiceView) => $choiceView->label, $choiceViews));
    }
}
