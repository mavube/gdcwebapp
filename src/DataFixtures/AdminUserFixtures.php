<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AdminUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seeds the command-centre operator.
 *
 * The password comes from GDC_ADMIN_PASSWORD when set, so a deployment can
 * choose its own without editing code. The fallback is development-only and
 * is logged loudly at load time.
 */
final class AdminUserFixtures extends Fixture
{
    public const DEFAULT_EMAIL = "admin@gdcconsulting.com";
    public const DEFAULT_PASSWORD = "ChangeMe!2026";

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $email = $_ENV["GDC_ADMIN_EMAIL"] ?? self::DEFAULT_EMAIL;
        $plain = $_ENV["GDC_ADMIN_PASSWORD"] ?? null;

        $existing = $manager->getRepository(AdminUser::class)->findOneBy(["email" => $email]);
        if ($existing !== null) {
            return;
        }

        $user = new AdminUser();
        $user->setEmail((string) $email);
        $user->setDisplayName("GDC Command Centre");
        $user->setRoles([AdminUser::ROLE_GDC_ADMIN]);
        $user->setPassword($this->hasher->hashPassword($user, $plain ?? self::DEFAULT_PASSWORD));

        $manager->persist($user);
        $manager->flush();

        if ($plain === null) {
            fwrite(STDERR, sprintf(
                "[fixtures] Seeded %s with the development password \"%s\". Set GDC_ADMIN_PASSWORD before deploying.\n",
                $email,
                self::DEFAULT_PASSWORD,
            ));
        }
    }
}
