<?php

declare(strict_types=1);

namespace App\Lib;

class MerkleTree
{
    /**
     * Build the Merkle root for a list of items.
     *
     * @param array $items Array of strings or arrays.
     * @return string SHA-256 Merkle root.
     */
    public static function buildRoot(array $items): string
    {
        if (empty($items)) {
            return hash('sha256', '');
        }

        $leaves = array_map(function ($item) {
            $data = is_array($item) ? json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $item;
            return hash('sha256', $data);
        }, $items);

        return self::buildMerkleRoot($leaves);
    }

    /**
     * Recursively build the Merkle root from leaf hashes.
     *
     * @param array $hashes
     * @return string
     */
    private static function buildMerkleRoot(array $hashes): string
    {
        $level = $hashes;

        while (count($level) > 1) {
            $nextLevel = [];
            for ($i = 0; $i < count($level); $i += 2) {
                $left = $level[$i];
                $right = $level[$i + 1] ?? $left;
                $nextLevel[] = hash('sha256', $left . $right);
            }
            $level = $nextLevel;
        }

        return $level[0];
    }
}
