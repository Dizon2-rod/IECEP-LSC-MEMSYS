<?php
namespace App\Lib;

require_once __DIR__ . '/../../bootstrap.php';
class DigitalIdService
{
    private array $config;

    public function __construct()
    {
        $this->config = include __DIR__ . '/../config/config.php';
    }

    public function generate(string $memberName, string $institutionName, string $memberType, string $memberId, string $qrCodePath): ?string
    {
        $width = 600;
        $height = 400;

        $image = imagecreatetruecolor($width, $height);
        if (!$image) return null;

        // Colors
        $navy = $this->hexToRgb('#0A2F6C');
        $gold = $this->hexToRgb('#F5A623');
        $white = $this->hexToRgb('#FFFFFF');
        $lightGray = $this->hexToRgb('#F8F9FA');
        $darkGray = $this->hexToRgb('#343A40');

        $navyColor = imagecolorallocate($image, $navy[0], $navy[1], $navy[2]);
        $goldColor = imagecolorallocate($image, $gold[0], $gold[1], $gold[2]);
        $whiteColor = imagecolorallocate($image, $white[0], $white[1], $white[2]);
        $lightGrayColor = imagecolorallocate($image, $lightGray[0], $lightGray[1], $lightGray[2]);
        $darkGrayColor = imagecolorallocate($image, $darkGray[0], $darkGray[1], $darkGray[2]);

        // Background: gradient navy to dark navy
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int)($navy[0] * (1 - $ratio * 0.5));
            $g = (int)($navy[1] * (1 - $ratio * 0.5));
            $b = (int)($navy[2] * (1 - $ratio * 0.3));
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }

        // Gold accent bar at top
        imagefilledrectangle($image, 0, 0, $width, 6, $goldColor);

        // Gold accent bar at bottom
        imagefilledrectangle($image, 0, $height - 6, $width, $height, $goldColor);

        // Header text
        $fontBold = 5; // Built-in font size
        $headerText = 'IECEP-LSC';
        $headerX = 20;
        $headerY = 20;
        imagestring($image, $fontBold, $headerX, $headerY, $headerText, $goldColor);

        // Sub-header
        $subText = 'MEMBERSHIP ID';
        imagestring($image, 3, $headerX, $headerY + 24, $subText, $whiteColor);

        // Divider line
        imageline($image, 20, 60, $width - 20, 60, $goldColor);

        // Member name
        $nameY = 75;
        $displayName = $this->truncateText($memberName, 30);
        imagestring($image, $fontBold, 20, $nameY, $displayName, $whiteColor);

        // Institution
        $instY = $nameY + 28;
        $displayInst = $this->truncateText($institutionName, 35);
        imagestring($image, 3, 20, $instY, $displayInst, $lightGrayColor);

        // Member type badge
        $badgeY = $instY + 28;
        $typeLabel = strtoupper($memberType) . ' MEMBER';
        $badgeWidth = strlen($typeLabel) * 9 + 16;
        imagefilledrectangle($image, 20, $badgeY, 20 + $badgeWidth, $badgeY + 22, $goldColor);
        imagestring($image, 3, 28, $badgeY + 4, $typeLabel, $navyColor);

        // Member ID
        $idY = $badgeY + 36;
        $shortId = substr($memberId, 0, 8);
        imagestring($image, 2, 20, $idY, "ID: $shortId", $lightGrayColor);

        // QR Code (right side)
        if (file_exists($qrCodePath)) {
            $qrImage = imagecreatefrompng($qrCodePath);
            if ($qrImage) {
                $qrSize = 140;
                $qrX = $width - $qrSize - 30;
                $qrY = 70;

                // White background for QR
                imagefilledrectangle($image, $qrX - 6, $qrY - 6, $qrX + $qrSize + 6, $qrY + $qrSize + 6, $whiteColor);
                imagecopyresampled($image, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize, imagesx($qrImage), imagesy($qrImage));
                imagedestroy($qrImage);

                // Scan label
                imagestring($image, 1, $qrX, $qrY + $qrSize + 10, 'SCAN TO VERIFY', $goldColor);
            }
        }

        // Footer text
        $footerY = $height - 30;
        imagestring($image, 2, 20, $footerY, 'Institute of Electronics Engineers of the Philippines', $lightGrayColor);
        imagestring($image, 2, 20, $footerY + 14, 'Laguna Student Chapter', $lightGrayColor);

        // Save to temp file
        $tempPath = sys_get_temp_dir() . '/digital_id_' . $memberId . '.png';
        imagepng($image, $tempPath);
        imagedestroy($image);

        return $tempPath;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function truncateText(string $text, int $maxLen): string
    {
        if (strlen($text) > $maxLen) {
            return substr($text, 0, $maxLen - 3) . '...';
        }
        return $text;
    }
}
