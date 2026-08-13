<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:expire-reservations', description: 'Materialize the expired status for elapsed reservations')]
final class ExpireReservationsCommand extends Command
{
    public function __construct(private readonly ReservationService $reservations)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Keep running as a worker')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Seconds between worker runs', '5');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $watch = (bool) $input->getOption('watch');
        $interval = filter_var($input->getOption('interval'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
        if ($interval === false) {
            $output->writeln('<error>--interval must be between 1 and 3600 seconds.</error>');

            return Command::INVALID;
        }

        do {
            $expired = $this->reservations->expireDue();
            if ($expired > 0 || !$watch) {
                $output->writeln(sprintf('Expired %d reservation(s).', $expired));
            }
            if ($watch) {
                sleep($interval);
            }
        } while ($watch);

        return Command::SUCCESS;
    }
}
