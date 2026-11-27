<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Site;

use Masilia\IbexaBlocks\Tests\Support\TestSettings;
use PHPUnit\Framework\TestCase;

final class SettingsProviderTest extends TestCase
{
    public function testASettingNobodySaysKeepsTheDefaultOfTheValueObject(): void
    {
        $provider = TestSettings::fromParameters([]);

        self::assertSame('Confirm your subscription', $provider->newsletter()->subject);
        self::assertNull($provider->newsletter()->fromAddress);
        self::assertSame(60, $provider->forms()->maxFields);
        self::assertFalse($provider->newsletterEnabled());
    }

    public function testTheSettingsAreThoseOfTheSiteTheResolverAnswersFor(): void
    {
        $provider = TestSettings::fromParameters([
            'masilia_ibexa_blocks:newsletter.subject' => 'Confirm — Fossil Exit',
            'masilia_ibexa_blocks:newsletter.list_name' => 'the Fossil Exit newsletter',
            'masilia_ibexa_blocks:forms.contact.captcha' => false,
        ]);

        self::assertSame('Confirm — Fossil Exit', $provider->newsletter()->subject);
        self::assertSame('the Fossil Exit newsletter', $provider->newsletter()->listName);
        self::assertFalse($provider->contact()->captcha);
    }

    public function testTheAddressOfTheRegistryComesBeforeTheConfiguredOne(): void
    {
        $provider = TestSettings::fromParameters([
            'masilia_ibexa_sites:site_email' => 'hello@fossil.example',
            'masilia_ibexa_blocks:forms.from_address' => 'contact@imal.example',
            'masilia_ibexa_blocks:newsletter.from_address' => 'news@imal.example',
        ]);

        self::assertSame('hello@fossil.example', $provider->forms()->fromAddress);
        self::assertSame('hello@fossil.example', $provider->contact()->fromAddress);
        self::assertSame('hello@fossil.example', $provider->newsletter()->fromAddress);
    }

    public function testWithoutAnAddressInTheRegistryTheNewsletterFallsBackToTheFormsAddress(): void
    {
        $provider = TestSettings::fromParameters(['masilia_ibexa_blocks:forms.from_address' => 'contact@imal.example']);
        self::assertSame('contact@imal.example', $provider->newsletter()->fromAddress);

        $provider = TestSettings::fromParameters([
            'masilia_ibexa_blocks:forms.from_address' => 'contact@imal.example',
            'masilia_ibexa_blocks:newsletter.from_address' => 'news@imal.example',
        ]);
        self::assertSame('news@imal.example', $provider->newsletter()->fromAddress);
        self::assertSame('contact@imal.example', $provider->forms()->fromAddress);
    }

    public function testAnEmptyAddressIsNoAddress(): void
    {
        $provider = TestSettings::fromParameters(['masilia_ibexa_sites:site_email' => '', 'masilia_ibexa_blocks:forms.from_address' => '']);

        self::assertNull($provider->forms()->fromAddress);
    }

    public function testTheNameOfTheSiteInTheRegistryComesBeforeTheConfiguredTexts(): void
    {
        $named = TestSettings::fromParameters([
            'masilia_ibexa_sites:site_name' => 'Fossil Exit',
            'masilia_ibexa_blocks:newsletter.subject' => 'Confirm — IMAL',
            'masilia_ibexa_blocks:newsletter.list_name' => 'the IMAL newsletter',
        ]);
        self::assertSame('Confirm your subscription — Fossil Exit', $named->newsletter()->subject);
        self::assertSame('the Fossil Exit newsletter', $named->newsletter()->listName);

        $says = TestSettings::fromParameters([
            'masilia_ibexa_sites:site_name' => 'Fossil Exit',
            'masilia_ibexa_blocks:newsletter.subject' => '[{site_name}] Please confirm',
            'masilia_ibexa_blocks:newsletter.list_name' => 'the letter of {site_name}',
        ]);
        self::assertSame('[Fossil Exit] Please confirm', $says->newsletter()->subject, 'the configured text says where the name goes');
        self::assertSame('the letter of Fossil Exit', $says->newsletter()->listName);
    }

    public function testWithoutANameInTheRegistryTheConfiguredTextsApplyAsTheyAre(): void
    {
        $provider = TestSettings::fromParameters(['masilia_ibexa_blocks:newsletter.subject' => 'Confirm — IMAL', 'masilia_ibexa_blocks:newsletter.list_name' => 'the IMAL newsletter']);

        self::assertSame('Confirm — IMAL', $provider->newsletter()->subject);
        self::assertSame('the IMAL newsletter', $provider->newsletter()->listName);
        self::assertSame('our newsletter', TestSettings::fromParameters([])->newsletter()->listName);
    }
}
