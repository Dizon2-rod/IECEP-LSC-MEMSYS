<?php
declare(strict_types=1);

namespace App\Lib;

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/SettingsService.php';

use App\Lib\SupabaseClient;
use App\Lib\SettingsService;

/**
 * DocumentRepository - Canonical Data Access for Institutional Documents
 */
class DocumentRepository
{
    private static ?self $instance = null;
    private ?SupabaseClient $supabase = null;
    private SettingsService $settings;

    public function __construct(?SupabaseClient $supabase = null)
    {
        if ($supabase) {
            $this->supabase = $supabase;
        } else {
            $config = require dirname(__DIR__, 2) . '/includes/supabase.php';
            if (!empty($config['url']) && (!empty($config['service_role_key']) || !empty($config['anon_key']))) {
                $key = !empty($config['service_role_key']) ? $config['service_role_key'] : $config['anon_key'];
                $this->supabase = new SupabaseClient($config['url'], $key);
            }
        }
        $this->settings = SettingsService::getInstance($this->supabase);
    }

    public static function getInstance(?SupabaseClient $supabase = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($supabase);
        }
        return self::$instance;
    }

    /**
     * Get all documents for an institution
     */
    public function getByInstitution(string $institutionId): array
    {
        if (empty($institutionId)) return [];

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('institution_documents', [
                    'institution_id' => 'eq.' . $institutionId,
                    'order' => 'uploaded_at.desc'
                ]);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[DocumentRepository] getByInstitution error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM institution_documents WHERE institution_id = :iid ORDER BY uploaded_at DESC");
                $stmt->execute([':iid' => $institutionId]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Get all documents associated with an affiliation application
     */
    public function getByApplication(string $applicationId): array
    {
        if (empty($applicationId)) return [];

        if ($this->supabase) {
            try {
                $rows = $this->supabase->select('institution_documents', [
                    'application_id' => 'eq.' . $applicationId,
                    'order' => 'uploaded_at.desc'
                ]);
                if (is_array($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                error_log('[DocumentRepository] getByApplication error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM institution_documents WHERE application_id = :aid ORDER BY uploaded_at DESC");
                $stmt->execute([':aid' => $applicationId]);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Save an uploaded document record
     */
    public function saveDocument(string $institutionId, string $type, string $fileName, string $filePath, ?string $appId = null): array
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $docData = [
            'id'             => $uuid,
            'institution_id' => $institutionId,
            'application_id' => $appId,
            'document_type'  => $type,
            'file_name'      => $fileName,
            'file_path'      => $filePath,
            'uploaded_at'    => date('Y-m-d H:i:s')
        ];

        if ($this->supabase) {
            try {
                $this->supabase->insert('institution_documents', $docData);
            } catch (\Throwable $e) {
                error_log('[DocumentRepository] saveDocument Supabase error: ' . $e->getMessage());
            }
        }

        try {
            $pdo = $this->getMySQLConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO institution_documents (id, institution_id, application_id, document_type, file_name, file_path, uploaded_at)
                    VALUES (:id, :iid, :aid, :dt, :fn, :fp, NOW())
                ");
                $stmt->execute([
                    ':id'  => $uuid,
                    ':iid' => $institutionId,
                    ':aid' => $appId,
                    ':dt'  => $type,
                    ':fn'  => $fileName,
                    ':fp'  => $filePath
                ]);
            }
        } catch (\Throwable $e) {}

        return $docData;
    }

    /**
     * Get official required document keys from SettingsService
     */
    public function getRequiredTypes(): array
    {
        return [
            'letter_of_intent'     => 'Letter of Intent',
            'endorsement_letter'   => 'Endorsement Letter',
            'constitution_by_laws' => 'Constitution and By-Laws',
            'officers_cvs'         => 'List of Officers with CVs',
            'organizational_chart' => 'Organizational Chart',
            'member_directory'     => 'Certified Student Member Directory'
        ];
    }

    /**
     * Check which required documents are missing for an institution
     */
    public function getMissingDocuments(string $institutionId): array
    {
        $existing = $this->getByInstitution($institutionId);
        $uploadedTypes = [];
        foreach ($existing as $doc) {
            if (!empty($doc['document_type'])) {
                $uploadedTypes[strtolower($doc['document_type'])] = true;
            }
        }

        $missing = [];
        foreach ($this->getRequiredTypes() as $reqType) {
            if (!isset($uploadedTypes[strtolower($reqType)])) {
                $missing[] = $reqType;
            }
        }

        return $missing;
    }

    private function getMySQLConnection(): ?\PDO
    {
        try {
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbName = env('DB_NAME', 'iecep_lsc_memsys');
            return new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", env('DB_USER', 'root'), env('DB_PASS', ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
                \PDO::ATTR_TIMEOUT => 2
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
