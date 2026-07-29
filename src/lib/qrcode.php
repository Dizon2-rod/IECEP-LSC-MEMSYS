<?php
namespace App\Lib;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;

require_once __DIR__ . '/../../bootstrap.php';
class QrCodeService
{
    public function generate(string $data, int $size = 200): string
    {
        $qrCode = QrCode::create($data)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->setSize($size)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setMargin(10);

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return $result->getString();
    }

    public function generateAndSave(string $data, string $filePath, int $size = 200): bool
    {
        try {
            $qrCode = QrCode::create($data)
                ->setEncoding(new Encoding('UTF-8'))
                ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->setSize($size)
                ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->setMargin(10);

            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $result->saveToFile($filePath);
            return true;
        } catch (\Exception $e) {
            error_log("QR Code error: " . $e->getMessage());
            return false;
        }
    }
}
