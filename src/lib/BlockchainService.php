<?php
declare(strict_types=1);

namespace App\Lib;

require_once __DIR__ . '/../../bootstrap.php';

/**
 * BlockchainService - Hash-Chained Audit Trail (Blockchain-Style)
 * 
 * This service implements an immutable audit log using cryptographic hash chaining,
 * similar to blockchain technology. It does NOT use a public cryptocurrency blockchain.
 * 
 * How it works:
 * - Each record is hashed using SHA256
 * - Each record stores the hash of the previous record (creating a chain)
 * - Any tampering with historical records breaks the chain and is detectable
 * - This provides tamper-proof audit trails for compliance and security
 * 
 * Use cases:
 * - Document integrity verification
 * - Membership change tracking
 * - Transaction audit trails
 * - Compliance attendance records
 */
class BlockchainService
{
    private \App\Lib\SupabaseClient $db;
    private string $table = 'blockchain_records';
    private array $allowedRecordTypes = [
        'transaction',
        'membership_change',
        'membership',
        'compliance_attendance',
        'document_hash',
        'affiliation_action',
        'affiliation',
        'payment',
        'digital_id',
        'event_attendance',
        'certificate',
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
     * @param string $entityType
     * @param string $entityId
     * @param array $dataPayload
     * @param string|null $institutionId
     * @return array
     */
    public function record(string $entityType, string $entityId, array $dataPayload, ?string $institutionId = null): array
    {
        $entityType = trim($entityType);
        if (!in_array($entityType, $this->allowedRecordTypes, true)) {
            throw new \InvalidArgumentException('Invalid entity type: ' . $entityType);
        }

        // Fetch the previous hash for this entity type
        $previousHash = $this->getPreviousHash($entityType);

        // Sort payload for consistent hashing
        $payload = $dataPayload;
        $this->jsonSort($payload);

        // Compute hash: SHA256(entity_type + entity_id + json_encode(dataPayload) + previous_hash)
        $hashInput = $entityType . $entityId . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $previousHash;
        $dataHash = hash('sha256', $hashInput);

        $insertData = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data_hash' => $dataHash,
            'previous_hash' => $previousHash,
            'data_json' => $payload,
            'merkle_root' => null,
        ];

        if ($institutionId !== null) {
            $insertData['institution_id'] = $institutionId;
        }

        // Also set legacy columns for backward compatibility
        $insertData['record_type'] = $entityType;
        $insertData['reference_id'] = $entityId;
        $insertData['transaction_hash'] = $dataHash;
        $insertData['record_hash'] = $dataHash;
        $insertData['metadata'] = [
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
            'php_version' => PHP_VERSION,
            'created_by' => $_SESSION['user']['email'] ?? 'system',
        ];

        $result = $this->db->insert($this->table, $insertData);

        return [
            'hash' => $dataHash,
            'previous_hash' => $previousHash,
            'record' => $result[0] ?? $result,
        ];
    }

    /**
     * Verify the blockchain chain for an entity type.
     *
     * @param string $entityType
     * @return array
     */
    public function verifyChain(string $entityType): array
    {
        $records = $this->db->select($this->table, [
            'entity_type' => 'eq.' . $entityType,
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
            
            // Re-compute hash using the same formula as record()
            $entityId = $row['entity_id'] ?? $row['reference_id'] ?? '';
            $hashInput = $entityType . $entityId . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $previousHash;
            $computedHash = hash('sha256', $hashInput);

            $storedHash = $row['data_hash'] ?? $row['record_hash'] ?? $row['transaction_hash'] ?? '';
            $storedPrevious = $row['previous_hash'] ?? '';

            $hashMatches = hash_equals($computedHash, $storedHash);
            $chainMatches = hash_equals($storedPrevious, $previousHash ?? '') || ($previousHash === null && $storedPrevious === null);

            if (!$hashMatches || !$chainMatches) {
                $tampered[] = [
                    'id' => $row['id'] ?? null,
                    'entity_id' => $row['entity_id'] ?? $row['reference_id'] ?? null,
                    'expected_hash' => $computedHash,
                    'stored_hash' => $storedHash,
                    'expected_previous' => $previousHash,
                    'stored_previous' => $storedPrevious,
                ];
                $valid = false;
            }

            $previousHash = $storedHash;
        }

        return [
            'valid' => $valid,
            'entity_type' => $entityType,
            'total_records' => count($records),
            'tampered' => $tampered,
        ];
    }

    /**
     * Verify a single blockchain record by ID.
     *
     * @param string $recordId
     * @return array
     */
    public function verifyRecord(string $recordId): array
    {
        $record = $this->db->select($this->table, [
            'id' => 'eq.' . $recordId,
            'limit' => 1,
        ]);

        if (empty($record)) {
            return [
                'valid' => false,
                'error' => 'Record not found',
            ];
        }

        $row = $record[0];
        $entityType = $row['entity_type'] ?? $row['record_type'] ?? '';
        $entityId = $row['entity_id'] ?? $row['reference_id'] ?? '';
        $previousHash = $row['previous_hash'] ?? '';
        $storedHash = $row['data_hash'] ?? $row['record_hash'] ?? $row['transaction_hash'] ?? '';

        $payload = $row['data_json'];
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $this->jsonSort($payload);
        
        // Re-compute hash
        $hashInput = $entityType . $entityId . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $previousHash;
        $computedHash = hash('sha256', $hashInput);

        $hashMatches = hash_equals($computedHash, $storedHash);

        // Verify chain link by checking if previous hash matches actual previous record
        $chainValid = true;
        if ($previousHash !== null && $previousHash !== '') {
            $prevRecord = $this->db->select($this->table, [
                'entity_type' => 'eq.' . $entityType,
                'data_hash' => 'eq.' . $previousHash,
                'limit' => 1,
            ]);
            $chainValid = !empty($prevRecord);
        }

        return [
            'valid' => $hashMatches && $chainValid,
            'hash_matches' => $hashMatches,
            'chain_valid' => $chainValid,
            'expected_hash' => $computedHash,
            'stored_hash' => $storedHash,
            'previous_hash' => $previousHash,
        ];
    }

    /**
     * Generate Merkle root from an array of hashes.
     *
     * @param array $items
     * @return string
     */
    public function generateMerkleRoot(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        // Convert items to hashes if they're not already
        $hashes = [];
        foreach ($items as $item) {
            if (is_string($item) && preg_match('/^[a-f0-9]{64}$/', $item)) {
                $hashes[] = $item;
            } else {
                $hashes[] = hash('sha256', is_string($item) ? $item : json_encode($item));
            }
        }

        // If odd number of hashes, duplicate the last one
        if (count($hashes) % 2 !== 0) {
            $hashes[] = $hashes[count($hashes) - 1];
        }

        // Build Merkle tree
        while (count($hashes) > 1) {
            $newLevel = [];
            for ($i = 0; $i < count($hashes); $i += 2) {
                $combined = $hashes[$i] . ($hashes[$i + 1] ?? $hashes[$i]);
                $newLevel[] = hash('sha256', $combined);
            }
            $hashes = $newLevel;
        }

        return $hashes[0] ?? '';
    }

    /**
     * Get the last hash for a given entity type.
     *
     * @param string $entityType
     * @return string|null
     */
    public function getPreviousHash(string $entityType): ?string
    {
        $last = $this->db->select($this->table, [
            'entity_type' => 'eq.' . $entityType,
            'order' => 'created_at.desc',
            'limit' => 1,
        ]);

        if (empty($last)) {
            return null;
        }

        return $last[0]['data_hash'] ?? $last[0]['record_hash'] ?? $last[0]['transaction_hash'] ?? null;
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
            'entity_type' => 'eq.document_hash',
            'select' => 'data_hash,data_json',
        ]);

        foreach ($records as $row) {
            $storedHash = $row['data_hash'] ?? $row['record_hash'] ?? $row['transaction_hash'] ?? '';
            if (hash_equals($storedHash, $fileHash)) {
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
            'entity_type' => 'eq.digital_id',
            'select' => 'data_hash',
        ]);

        foreach ($records as $row) {
            $storedHash = $row['data_hash'] ?? $row['record_hash'] ?? $row['transaction_hash'] ?? '';
            if (hash_equals($storedHash, $idHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify the blockchain integrity for a specific entity type and entity ID.
     *
     * @param string $entityType
     * @param string $entityId
     * @return bool
     */
    public function verify(string $entityType, string $entityId): bool
    {
        $records = $this->db->select($this->table, [
            'entity_type' => 'eq.' . $entityType,
            'entity_id' => 'eq.' . $entityId,
            'order' => 'created_at.asc',
        ]);

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
            
            $hashInput = $entityType . $entityId . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $previousHash;
            $computedHash = hash('sha256', $hashInput);

            $storedHash = $row['data_hash'] ?? $row['record_hash'] ?? $row['transaction_hash'] ?? '';
            $storedPrevious = $row['previous_hash'] ?? '';

            $hashMatches = hash_equals($computedHash, $storedHash);
            $chainMatches = hash_equals($storedPrevious, $previousHash ?? '') || ($previousHash === null && $storedPrevious === null);

            if (!$hashMatches || !$chainMatches) {
                $valid = false;
                break;
            }

            $previousHash = $storedHash;
        }

        return $valid;
    }

    /**
     * Hash a document and record it in the blockchain.
     *
     * @param string $documentPath
     * @param string $documentName
     * @param string $entityId
     * @return array
     */
    public function hashDocument(string $documentPath, string $documentName, string $entityId): array
    {
        if (!file_exists($documentPath)) {
            throw new \InvalidArgumentException('Document file does not exist');
        }

        $fileContent = file_get_contents($documentPath);
        $fileHash = hash('sha256', $fileContent);

        $payload = [
            'document_name' => $documentName,
            'file_size' => filesize($documentPath),
            'mime_type' => mime_content_type($documentPath),
            'hash' => $fileHash,
            'uploaded_at' => date('c')
        ];

        return $this->record('document_hash', $entityId, $payload);
    }

    /**
     * Verify a document hash against blockchain records.
     *
     * @param string $documentPath
     * @param string $entityId
     * @return bool
     */
    public function verifyDocument(string $documentPath, string $entityId): bool
    {
        if (!file_exists($documentPath)) {
            return false;
        }

        $fileContent = file_get_contents($documentPath);
        $computedHash = hash('sha256', $fileContent);

        $records = $this->db->select($this->table, [
            'entity_type' => 'eq.document_hash',
            'entity_id' => 'eq.' . $entityId,
        ]);

        foreach ($records as $record) {
            $payload = $record['data_json'];
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }

            if (is_array($payload) && isset($payload['hash']) && hash_equals($payload['hash'], $computedHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a hash exists in the blockchain.
     *
     * @param string $hash
     * @return array
     */
    public function hashExists(string $hash): array
    {
        $records = $this->db->select($this->table, [
            'or' => '(data_hash.eq.' . $hash . ',record_hash.eq.' . $hash . ',transaction_hash.eq.' . $hash . ')',
            'limit' => 1,
        ]);

        if (empty($records)) {
            return [
                'exists' => false,
                'record' => null,
            ];
        }

        return [
            'exists' => true,
            'record' => $records[0],
        ];
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
