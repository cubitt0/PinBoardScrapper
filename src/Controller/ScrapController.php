<?php

declare(strict_types = 1);
namespace App\Controller;

use App\Services\Scrapper;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverKeys;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Stream;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Panther\Client;
use Symfony\Component\Routing\Annotation\Route;
use ZipArchive;

class ScrapController
{
    #[Route('/scrap')]
    final public function scrap(Request $request, ?LoggerInterface $logger): Response
    {
        $scrapper = new Scrapper(trim($request->get('boardUrl'), '/'), $logger);
        $scrapper->scrape();

        return new JsonResponse([
            'status' => 'success',
            'images' => $scrapper->getImages()
        ], 200);
    }

    #[Route('/download')]
    final public function download(Request $request, ?LoggerInterface $logger): Response
    {
        $scrapper = new Scrapper(trim($request->get('boardUrl'), '/'), $logger);
        $scrapper->scrape();
        $zipName = $scrapper->download();

        $response = new BinaryFileResponse(new Stream($zipName));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $zipName);
        $response->deleteFileAfterSend();

        return $response;
    }

}