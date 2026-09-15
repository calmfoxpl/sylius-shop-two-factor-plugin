<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Command;

use Calmfox\SyliusShopTwoFactorPlugin\Model\TwoFactorShopUserInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'calmfox:shop:2fa:reset',
    description: 'Resets a customer\'s two-factor authentication: all methods are removed and they turn one on again.',
)]
final class ResetTwoFactorCommand extends Command
{
    /** @param UserRepositoryInterface<TwoFactorShopUserInterface> $shopUserRepository */
    public function __construct(
        private readonly UserRepositoryInterface $shopUserRepository,
        private readonly ObjectManager $userManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail address of the customer')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Only remove the methods, without requiring a new one (the policy still applies)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $email = is_string($email) ? $email : '';

        $user = $this->shopUserRepository->findOneByEmail($email);
        if (!$user instanceof TwoFactorShopUserInterface) {
            $io->error(sprintf('No customer account with two-factor support found for %s.', $email));

            return Command::FAILURE;
        }

        $disable = (bool) $input->getOption('disable');
        $user->clearTwoFactorAuthentication();
        $user->setTwoFactorSetupRequired(!$disable);
        $this->userManager->flush();

        $io->success($disable
            ? sprintf('Two-factor authentication disabled for %s.', $email)
            : sprintf('Two-factor authentication reset for %s. They will turn a method on again.', $email));

        return Command::SUCCESS;
    }
}
