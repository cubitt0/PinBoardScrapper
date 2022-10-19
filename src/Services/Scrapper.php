<?php

declare(strict_types = 1);
namespace App\Services;

use Exception;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use Psr\Log\LoggerInterface;
use Symfony\Component\Panther\Client;
use ZipArchive;

class Scrapper
{
    public Client  $client;
    private string $fullURL;
    private const BASE_URL     = 'https://pl.pinterest.com/';
    private const SCROLL_LIMIT = 100;
    private array $images = [];

    public function __construct(
        private readonly string $boardURL,
        private readonly ?LoggerInterface $logger
    ) {
        $this->fullURL = self::BASE_URL . $boardURL;
        $this->initializeClient();
    }

    public function getImages(): array
    {
        return $this->images;
    }

    final public function scrape(): void
    {
        $this->client->get($this->fullURL);
        $maxPins = $this->getMaxPins();

        $limit = 0;
        do {
            $crawler       = $this->client->getCrawler();
            $previousCount = count($this->images);

            /** @var RemoteWebElement $element */
            foreach ($crawler->filter('img')->getIterator() as $element) {
                try {
                    $src = $element->getDomProperty('src');

                    if (!$this->isPinImage($src)) {
                        continue;
                    }

                    $src = $this->getInFullResolution($src);

                    if (!in_array($src, $this->images, true)) {
                        $this->images[] = $src;
                    }
                    $currentCount = count($this->images);
                    if ($currentCount >= $maxPins) {
                        break;
                    }
                } catch (Exception $e) {
                    $this->logger?->error($e->getMessage(), $e->getTrace());
                }
            }
            $this->scrollDown();

            if ($previousCount === $currentCount) {
                $limit++;
            }
        } while ($limit < self::SCROLL_LIMIT && $currentCount <= $maxPins);
    }

    final public function download(): string
    {
        $zipName = str_replace('/', '_', trim($this->boardURL, '/')) . date('YmdHis') . '.zip';

        $zip = new ZipArchive();
        $zip->open($zipName, ZipArchive::CREATE);

        foreach ($this->getImages() as $index => $img) {
            $name = explode('/', $img);
            $zip->addFromString(array_pop($name), file_get_contents($img));
        }
        $zip->close();

        return $zipName;
    }

    private function initializeClient(): void
    {
        $this->client = Client::createChromeClient('../drivers/chromedriver.exe');
        $this->client->getWebDriver()->manage()->window()->setSize(new WebDriverDimension(3840, 2160));
    }

    public function __destruct()
    {
        $this->client->close();
    }

    private function getMaxPins(): int
    {
        $maxPinsDiv = $this->client->getCrawler()->filter('div[data-test-id=pin-count]')->first()->getText();
        preg_match('/\d+/', $maxPinsDiv, $matches);
        return (int)$matches[0] ?? 0;
    }

    private function scrollDown(): void
    {
        $this->client->getKeyboard()->pressKey(WebDriverKeys::PAGE_DOWN);
    }

    private function isPinImage(string $src): bool
    {
        return $src && str_contains($src, '236x');
    }

    private function getInFullResolution(string $src): string
    {
        return str_replace('236x', '736x', $src);
    }

}