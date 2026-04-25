<?php
namespace App\Lib;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeService
{
    public function generate(string $data, int $size = 200): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($size)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->margin(10)
            ->build();

        return $result->getString();
    }

    public function generateAndSave(string $data, string $filePath, int $size = 200): bool
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->writerOptions([])
                ->data($data)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size($size)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->margin(10)
                ->build();

            $result->saveToFile($filePath);
            return true;
        } catch (\Exception $e) {
            error_log("QR Code error: " . $e->getMessage());
            return false;
        }
    }
}
