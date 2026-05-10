<?php

declare(strict_types=1);

namespace App\Lib;

class BlockchainService
{
    private \App\Lib\SupabaseClient $db;
    private string $table = 'blockchain_records';
    private array $allowedRecordTypes = [
        'transaction',
        'membership_change',
        'compliance_attendance',
        'document_hash',
        'affiliation_action',
        'digital_id',
    ];

    public function __construct(\App\Lib\SupabaseClient $db)
    {
        $this->db = $db;

        if (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) {
            $this->db->setServiceRoleKey(SUPABASE_SERVICE_ROLE_KEY);
        }
    }

    /**
     * Record a blockchain integrity entry.
     *
     * @param string $recordType
     * @param string $referenceId
     * @param array $dataPayload
     * @param string|null $previousHash
     * @return array
     */
    public function record(string $recordType, string $referenceId, array $dataPayload, ?string $previousHash = null): array
    {
        $recordType = trim($recordType);
        if (!in_array($recordType, $this->allowedRecordTypes, true)) {
            throw new \InvalidArgumentException('Invalid record type: ' . $recordType);
        }

        if ($previousHash === null) {
            $previousHash = $this->getPreviousHash($recordType);
        }

        $payload = $dataPayload;
        $this->jsonSort($payload);
        $dataHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $insertData = [
            'record_type' => $recordType,
            'reference_id' => $referenceId,
            'data_hash' => $dataHash,
            'previous_hash' => $previousHash,
            'data_json' => $payload,
            'metadata' => [
                'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'created_by' => $_SESSION['user']['email'] ?? 'system',
            ],
        ];

        $result = $this->db->insert($this->table, $insertData);

        return [
            'hash' => $dataHash,
            'record' => $result[0] ?? $result,
        ];
    }

    /**
     * Verify the blockchain chain for a record type.
     *
     * @param string $recordType
     * @return array
     */
    public function verifyChain(string $recordType): array
    {
        $records = $this->db->select($this->table, [
            'record_type' => 'eq.' . $recordType,
            'order' => 'created_at.asc',
        ]);

        $tampered = [];
        $valid = true;
        $previousHash = null;

        foreach ($records as $row) {
            $payload = $row['data_json'];
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }
            if (!is_array($payload)) {
                $payload = [];
            }

            $this->jsonSort($payload);
            $computedHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $hashMatches = hash_equals($computedHash, (string) ($row['data_hash'] ?? ''));
            $chainMatches = hash_equals((string) ($row['previous_hash'] ?? ''), (string) ($previousHash ?? '')) || ($previousHash === null && $row['previous_hash'] === null);

            if (!$hashMatches || !$chainMatches) {
                $tampered[] = [
                    'id' => $row['id'] ?? null,
                    'reference_id' => $row['reference_id'] ?? null,
                    'expected_hash' => $computedHash,
                    'stored_hash' => $row['data_hash'] ?? null,
                    'expected_previous' => $previousHash,
                    'stored_previous' => $row['previous_hash'] ?? null,
                ];
                $valid = false;
            }

            $previousHash = $row['data_hash'] ?? null;
        }

        return [
            'valid' => $valid,
            'record_type' => $recordType,
            'total_records' => count($records),
            'tampered' => $tampered,
        ];
    }

    /**
     * Get the last hash for a given record type.
     *
     * @param string $recordType
     * @return string|null
     */
    public function getPreviousHash(string $recordType): ?string
    {
        $last = $this->db->select($this->table, [
            'record_type' => 'eq.' . $recordType,
            'order' => 'created_at.desc',
            'limit' => 1,
        ]);

        return !empty($last) ? ($last[0]['data_hash'] ?? null) : null;
    }

    /**
     * Verify whether a raw document hash exists in document_hash records.
     *
     * @param string $fileHash
     * @return bool
     */
    public function verifyDocumentHash(string $fileHash): bool
    {
        $records = $this->db->select($this->table, [
            'record_type' => 'eq.document_hash',
            'select' => 'data_hash,data_json',
        ]);

        foreach ($records as $row) {
            if (isset($row['data_hash']) && hash_equals((string) $row['data_hash'], $fileHash)) {
                return true;
            }

            $payload = $row['data_json'];
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }

            if (is_array($payload) && isset($payload['hash']) && hash_equals((string) $payload['hash'], $fileHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify whether a digital ID hash exists in digital_id records.
     *
     * @param string $idHash
     * @return bool
     */
    public function verifyDigitalId(string $idHash): bool
    {
        $records = $this->db->select($this->table, [
            'record_type' => 'eq.digital_id',
            'select' => 'data_hash',
        ]);

        foreach ($records as $row) {
            if (isset($row['data_hash']) && hash_equals((string) $row['data_hash'], $idHash)) {
                return true;
            }
        }

        return false;
    }

    private function jsonSort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->jsonSort($value);
            }
        }
    }
}
