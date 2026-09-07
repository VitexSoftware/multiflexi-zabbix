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

namespace MultiFlexi;

/**
 * Best-effort availability substitute for credential types that don't
 * implement checkableCredentialInterface: a credential missing a required
 * configuration field is unusable even though no live check exists for it.
 *
 * Relies on ConfigField::isRequired(), which is only as complete as each
 * prototype declares it (some prototypes check "required" fields ad hoc
 * inside their own checkAvailability() instead of tagging the field
 * definition) — prototypes without checkAvailability() should mark their
 * required fields via setRequired(true) so this fallback is accurate.
 */
final class CredentialAvailability
{
    public static function completenessFallback(ConfigFields $fields): CredentialCheckResult
    {
        $missing = [];

        foreach ($fields->getFields() as $code => $field) {
            if ($field->isRequired() && ($field->getValue() === null || $field->getValue() === '')) {
                $missing[] = $code;
            }
        }

        if ($missing) {
            return new CredentialCheckResult(
                CredentialState::Misconfigured,
                sprintf(_('Required fields not set: %s'), implode(', ', $missing)),
                time(),
                300,
                ['missing_fields' => $missing],
            );
        }

        return new CredentialCheckResult(CredentialState::Unknown, _('Availability check not implemented for this credential type'), time());
    }
}
