<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Rejects consumer mailbox providers so corporate funnels only accept
 * corporate email nodes.
 *
 * This is a *business* rule, not a security control. Deliverability and
 * ownership can only be proven by verification; this simply filters the
 * obvious public domains at the point of entry.
 *
 * Because it is a business rule, it is group-scoped: the constraint applies
 * only inside the group named by `$groups` (default "corporate"). An
 * individual-enrolment submission is validated without that group and so
 * accepts a personal mailbox, which is what a professional buying their own
 * PMP seat actually uses.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class CorporateEmail extends Constraint
{
    public string $message = 'Please provide an authorized enterprise email node.';

    /** @var list<string> */
    public array $blockedDomains = [
        'gmail.com', 'googlemail.com',
        'yahoo.com', 'yahoo.co.uk', 'yahoo.co.in', 'ymail.com',
        'hotmail.com', 'hotmail.co.uk', 'outlook.com', 'live.com', 'msn.com',
        'aol.com', 'icloud.com', 'me.com', 'mac.com',
        'protonmail.com', 'proton.me', 'pm.me',
        'gmx.com', 'gmx.net', 'mail.com', 'zoho.com', 'yandex.com',
        'qq.com', '163.com', '126.com',
    ];

    public function __construct(
        ?array $blockedDomains = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // Default to the corporate group rather than null. A constraint with no
        // group runs in "Default", where it would reject personal mailboxes for
        // every caller including individual enrolment.
        parent::__construct(null, $groups ?? ['corporate'], $payload);
        $this->blockedDomains = $blockedDomains ?? $this->blockedDomains;
        $this->message = $message ?? $this->message;
    }
}
