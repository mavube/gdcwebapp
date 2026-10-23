<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Rejects consumer mailbox providers so the funnel only accepts corporate
 * email nodes.
 *
 * Note this is a *business* rule, not a security control. Deliverability and
 * ownership can only be proven by verification; this simply filters the
 * obvious public domains at the point of entry.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class CorporateEmail extends Constraint
{
    public string $message = "Please provide an authorized enterprise email node.";

    /** @var list<string> */
    public array $blockedDomains = [
        "gmail.com", "googlemail.com",
        "yahoo.com", "yahoo.co.uk", "yahoo.co.in", "ymail.com",
        "hotmail.com", "hotmail.co.uk", "outlook.com", "live.com", "msn.com",
        "aol.com", "icloud.com", "me.com", "mac.com",
        "protonmail.com", "proton.me", "pm.me",
        "gmx.com", "gmx.net", "mail.com", "zoho.com", "yandex.com",
        "qq.com", "163.com", "126.com",
    ];

    public function __construct(
        ?array $blockedDomains = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // Pass null rather than an empty options array: the parent treats a
        // non-null $options as "evaluate these options", which is deprecated in
        // 7.4. Properties are initialised here instead.
        parent::__construct(null, $groups, $payload);
        $this->blockedDomains = $blockedDomains ?? $this->blockedDomains;
        $this->message = $message ?? $this->message;
    }
}
