<?php
declare(strict_types=1);

namespace Shlinkio\Shlink\CLI\Command\Visit;

use Shlinkio\Shlink\Common\Exception\WrongIpException;
use Shlinkio\Shlink\Common\Service\IpLocationResolverInterface;
use Shlinkio\Shlink\Core\Entity\VisitLocation;
use Shlinkio\Shlink\Core\Service\VisitServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zend\I18n\Translator\TranslatorInterface;

class ProcessVisitsCommand extends Command
{
    const LOCALHOST = '127.0.0.1';
    const NAME = 'visit:process';

    /**
     * @var VisitServiceInterface
     */
    private $visitService;
    /**
     * @var IpLocationResolverInterface
     */
    private $ipLocationResolver;
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        VisitServiceInterface $visitService,
        IpLocationResolverInterface $ipLocationResolver,
        TranslatorInterface $translator
    ) {
        $this->visitService = $visitService;
        $this->ipLocationResolver = $ipLocationResolver;
        $this->translator = $translator;
        parent::__construct(null);
    }

    public function configure()
    {
        $this->setName(self::NAME)
             ->setDescription(
                 $this->translator->translate('Processes visits where location is not set yet')
             );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $visits = $this->visitService->getUnlocatedVisits();

        foreach ($visits as $visit) {
            $ipAddr = $visit->getRemoteAddr();
            $io->write(sprintf('%s <info>%s</info>', $this->translator->translate('Processing IP'), $ipAddr));
            if ($ipAddr === self::LOCALHOST) {
                $io->writeln(
                    sprintf(' (<comment>%s</comment>)', $this->translator->translate('Ignored localhost address'))
                );
                continue;
            }

            try {
                $result = $this->ipLocationResolver->resolveIpLocation($ipAddr);

                $location = new VisitLocation();
                $location->exchangeArray($result);
                $visit->setVisitLocation($location);
                $this->visitService->saveVisit($visit);

                $io->writeln(sprintf(
                    ' (' . $this->translator->translate('Address located at "%s"') . ')',
                    $location->getCityName()
                ));
            } catch (WrongIpException $e) {
                continue;
            }
        }

        $io->success($this->translator->translate('Finished processing all IPs'));
    }
}
