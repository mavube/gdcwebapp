<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The repeatable lists a page renders, each addressed by a stable key.
 *
 * A section is a list of {@see \App\Entity\ContentItem} rows. The enum exists
 * rather than a free-text column because a section only renders if a template
 * asks for it: adding a case here without a template that consumes it would
 * create rows nothing displays. The stored value is the key the template uses.
 *
 * The label is presentation, shown in the admin, and may be reworded without a
 * migration.
 */
enum ContentSection: string
{
    /** Hero credibility row: the organisations and standards behind the practice. */
    case HomeHeroTrust = 'home.hero.trust';

    /** Hero figure strip. `title` is the value, `subtitle` the caption. */
    case HomeHeroStats = 'home.hero.stats';

    /** Selected engagements carousel. `subtitle` is the category, `image` the shot. */
    case HomeWork = 'home.work';

    /** The numbered offer rows. `title` and `body`; the number is the row position. */
    case HomeServices = 'home.services';

    /** The method panel's tick list. `body` is the line of text. */
    case HomeMethodChecks = 'home.team.checks';

    /** The method panel's avatar stack. `image` and `title` (alt text). */
    case HomeMethodAvatars = 'home.team.avatars';

    /** Accordion entries. `title` is the question, `body` the answer. */
    case HomeFaq = 'home.faq';

    /** Client reviews. `body` is the quote, `title` the author, `subtitle` the role. */
    case HomeReviews = 'home.reviews';

    /**
     * Contact channels, site-wide: address, phone, email, opening hours.
     *
     * `title` is the label, `subtitle` the value shown, and `linkTarget` the
     * destination (a tel: or mailto: URI for the channels that are actionable).
     * Kept as rows rather than columns on the block so adding a channel is an
     * admin edit, not a migration.
     */
    case SiteContact = 'site.contact';

    /**
     * The programme page's solution cards.
     *
     * Distinct from the home page's numbered offer: the two lists read
     * differently and an editor should be able to change one without the other.
     */
    case TrainingSolutions = 'training.solutions';

    /**
     * The primary navigation, in order.
     *
     * Site-wide rather than per-page, so the header is edited in one place.
     * `title` is the link label and `linkTarget` its destination.
     */
    case SiteNav = 'site.nav';

    /**
     * The footer's own navigation column.
     *
     * Separate from {@see SiteNav} because the two lists differ: the header is
     * short and action-led, the footer carries the fuller set.
     */
    case SiteFooterLinks = 'site.footer.links';

    /**
     * The legal and attribution line at the foot of the page.
     *
     * `body` is the sentence, `linkTarget` the optional destination for a
     * trailing link (a licence page, for example).
     */
    case SiteLegal = 'site.legal';

    public function label(): string
    {
        return match ($this) {
            self::HomeHeroTrust => 'Home · Hero credibility row',
            self::HomeHeroStats => 'Home · Hero figures',
            self::HomeWork => 'Home · Selected engagements',
            self::HomeServices => 'Home · Numbered offer rows',
            self::HomeMethodChecks => 'Home · Method panel tick list',
            self::HomeMethodAvatars => 'Home · Method panel avatars',
            self::HomeFaq => 'Home · Questions',
            self::HomeReviews => 'Home · Client reviews',
            self::SiteContact => 'Site · Contact channels',
            self::TrainingSolutions => 'Programme · Solution cards',
            self::SiteNav => 'Site · Header navigation',
            self::SiteFooterLinks => 'Site · Footer navigation',
            self::SiteLegal => 'Site · Legal line',
        };
    }

    /**
     * What the row's fields mean in this section, shown as admin help text.
     *
     * The column names are generic so one entity serves every list; this is
     * where the per-section meaning is written down.
     */
    public function fieldHint(): string
    {
        return match ($this) {
            self::HomeHeroTrust => 'Title: the name shown in the row.',
            self::HomeHeroStats => 'Title: the figure. Subtitle: what it counts.',
            self::HomeWork => 'Title: the engagement. Subtitle: its category. Image: the shot.',
            self::HomeServices => 'Title: the offer. Body: one or two lines on it. Link: where it goes.',
            self::HomeMethodChecks => 'Body: the tick line.',
            self::HomeMethodAvatars => 'Image: the portrait. Title: its alt text.',
            self::HomeFaq => 'Title: the question. Body: the answer.',
            self::HomeReviews => 'Body: the quote. Title: who said it. Subtitle: their role. Image: their portrait.',
            self::SiteContact => 'Title: the label ("Visit", "Call"). Subtitle: the value shown. Link: the tel: or mailto: destination.',
            self::TrainingSolutions => 'Title: the card heading. Subtitle: the eyebrow above it. Body: the description. Link: where the card points.',
            self::SiteNav, self::SiteFooterLinks => 'Title: the link label. Link: where it goes.',
            self::SiteLegal => 'Body: the sentence. Link: the optional destination for a trailing link.',
        };
    }

    /**
     * Whether a row without an image still reads as intended.
     *
     * The media-led sections fall back to a branded panel, so an editor can add
     * a row before the photograph exists without leaving a broken tile.
     */
    public function isMediaLed(): bool
    {
        return match ($this) {
            self::HomeWork, self::HomeMethodAvatars => true,
            default => false,
        };
    }
}
