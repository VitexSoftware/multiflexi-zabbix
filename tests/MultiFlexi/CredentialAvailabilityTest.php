<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\MultiFlexi;

use MultiFlexi\ConfigField;
use MultiFlexi\ConfigFields;
use MultiFlexi\CredentialAvailability;
use MultiFlexi\CredentialState;
use PHPUnit\Framework\TestCase;

final class CredentialAvailabilityTest extends TestCase
{
    public function testUnknownWhenAllRequiredFieldsFilled(): void
    {
        $fields = new ConfigFields();
        $fields->addField((new ConfigField('URL', 'url'))->setRequired(true)->setValue('https://example.test'));
        $fields->addField((new ConfigField('OPTIONAL_FLAG', 'bool'))->setRequired(false)->setValue(null));

        $result = CredentialAvailability::completenessFallback($fields);

        self::assertSame(CredentialState::Unknown, $result->state);
        self::assertSame([], $result->details);
    }

    public function testMisconfiguredWhenRequiredFieldEmpty(): void
    {
        $fields = new ConfigFields();
        $fields->addField((new ConfigField('URL', 'url'))->setRequired(true)->setValue(''));
        $fields->addField((new ConfigField('TOKEN', 'secret'))->setRequired(true)->setValue(null));
        $fields->addField((new ConfigField('OPTIONAL_FLAG', 'bool'))->setRequired(false)->setValue(null));

        $result = CredentialAvailability::completenessFallback($fields);

        self::assertSame(CredentialState::Misconfigured, $result->state);
        self::assertEqualsCanonicalizing(['URL', 'TOKEN'], $result->details['missing_fields']);
        self::assertStringContainsString('URL', $result->message);
        self::assertStringContainsString('TOKEN', $result->message);
    }

    public function testUnknownWhenNoFieldsAreRequired(): void
    {
        $fields = new ConfigFields();
        $fields->addField((new ConfigField('NOTES', 'string'))->setRequired(false)->setValue(''));

        $result = CredentialAvailability::completenessFallback($fields);

        self::assertSame(CredentialState::Unknown, $result->state);
    }
}
