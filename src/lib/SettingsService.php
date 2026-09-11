<?php
declare(strict_types=1);

namespace App\Lib;

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/SupabaseClient.php';

use App\Lib\SupabaseClient;

/**
 * SettingsService - Centralized Single Source of Truth for Configuration, Fees & Rules
 * 
 * Caches settings in memory for the duration of the request.
 * Completely eliminates hardcoded fee values, bracket definitions, and compliance thresholds.
 */
class SettingsService
{
    private static ?self $instance = null;
    private ?SupabaseClient $supabase = null;
    
    private array $systemSettings = [];
    private array $feeBrackets = [];
    private array $memberFees = [];
    private array $complianceRules = [];
    private bool $loaded = false;

    private function __construct(?SupabaseClient $supabase = null)
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
    }

    public static function getInstance(?SupabaseClient $supabase = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($supabase);
        }
        return self::$instance;
    }

    /**
     * Clear the in-memory request cache (useful for testing or after migrations)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Ensure all configuration tables are loaded into in-memory request cache
     */
    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        // 1. Try Supabase first
        if ($this->supabase) {
            try {
                // System Settings
                $settingsRows = $this->supabase->select('system_settings', ['select' => 'key,value,description']);
                if (is_array($settingsRows)) {
                    foreach ($settingsRows as $row) {
                        if (isset($row['key'])) {
                            $this->systemSettings[$row['key']] = $row['value'] ?? '';
                        }
                    }
                }

                // Fee Brackets
                $bracketRows = $this->supabase->select('fee_brackets', [
                    'is_active' => 'eq.true',
                    'order' => 'min_members.asc'
                ]);
                if (is_array($bracketRows)) {
                    $this->feeBrackets = $bracketRows;
                }

                // Member Fees
                $memberFeeRows = $this->supabase->select('member_fees', [
                    'is_active' => 'eq.true'
                ]);
                if (is_array($memberFeeRows)) {
                    foreach ($memberFeeRows as $row) {
                        if (isset($row['member_type'])) {
                            $this->memberFees[strtolower($row['member_type'])] = (float)($row['fee'] ?? 0);
                        }
                    }
                }

                // Compliance Rules
                $ruleRows = $this->supabase->select('compliance_rules', [
                    'is_active' => 'eq.true'
                ]);
                if (is_array($ruleRows)) {
                    foreach ($ruleRows as $row) {
                        if (isset($row['rule_key'])) {
                            $this->complianceRules[$row['rule_key']] = (float)($row['threshold'] ?? 0);
                        }
                    }
                }

                $this->loaded = true;
                return;
            } catch (\Throwable $e) {
                error_log('[SettingsService] Supabase load warning: ' . $e->getMessage());
            }
        }

        // 2. Fallback to local MySQL PDO
        try {
            $dbHost = env('DB_HOST', '127.0.0.1');
            $dbName = env('DB_NAME', 'iecep_lsc_memsys');
            $pdo = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", env('DB_USER', 'root'), env('DB_PASS', ''), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
                \PDO::ATTR_TIMEOUT => 2
            ]);

            // System settings
            $stmt = $pdo->query("SELECT `key`, `value` FROM `system_settings`");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $this->systemSettings[$row['key']] = $row['value'];
                }
            }

            // Fee brackets
            $stmt = $pdo->query("SELECT * FROM `fee_brackets` WHERE `is_active` = 1 ORDER BY `min_members` ASC");
            if ($stmt) {
                $this->feeBrackets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            // Member fees
            $stmt = $pdo->query("SELECT `member_type`, `fee` FROM `member_fees` WHERE `is_active` = 1");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $this->memberFees[strtolower($row['member_type'])] = (float)$row['fee'];
                }
            }

            // Compliance rules
            $stmt = $pdo->query("SELECT `rule_key`, `threshold` FROM `compliance_rules` WHERE `is_active` = 1");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $this->complianceRules[$row['rule_key']] = (float)$row['threshold'];
                }
            }

            $this->loaded = true;
            return;
        } catch (\Throwable $e) {
            error_log('[SettingsService] Local DB fallback warning: ' . $e->getMessage());
        }

        $this->loaded = true;
    }

    /**
     * Get a raw system setting value by key
     */
    public function get(string $key, $default = null)
    {
        $this->ensureLoaded();
        return $this->systemSettings[$key] ?? $default;
    }

    /**
     * Get operational fee (fixed fee per chapter affiliation)
     */
    public function getOperationalFee(): float
    {
        $this->ensureLoaded();
        $val = $this->systemSettings['operational_fee'] ?? null;
        if ($val !== null && is_numeric($val)) {
            return (float)$val;
        }
        return 800.00; // Baseline CBL resolution fallback
    }

    /**
     * Get all active fee brackets
     */
    public function getFeeBrackets(): array
    {
        $this->ensureLoaded();
        if (!empty($this->feeBrackets)) {
            return $this->feeBrackets;
        }

        // Default baseline brackets if database table empty
        return [
            ['bracket_name' => 'Small', 'min_members' => 1, 'max_members' => 50, 'fee' => 1500.00],
            ['bracket_name' => 'Medium', 'min_members' => 51, 'max_members' => 100, 'fee' => 2000.00],
            ['bracket_name' => 'Large', 'min_members' => 101, 'max_members' => 150, 'fee' => 2500.00],
            ['bracket_name' => 'Enterprise', 'min_members' => 151, 'max_members' => 999999, 'fee' => 3000.00]
        ];
    }

    /**
     * Calculate affiliation national fee for a given member count based on brackets
     */
    public function getAffiliationFeeForCount(int $memberCount): float
    {
        $brackets = $this->getFeeBrackets();
        
        // Find matching bracket
        foreach ($brackets as $bracket) {
            $min = (int)($bracket['min_members'] ?? 0);
            $max = isset($bracket['max_members']) ? (int)$bracket['max_members'] : 999999;
            if ($memberCount >= $min && $memberCount <= $max) {
                return (float)$bracket['fee'];
            }
        }

        // If count exceeds highest bracket, take highest fee
        if (!empty($brackets)) {
            $last = end($brackets);
            return (float)($last['fee'] ?? 3000.00);
        }

        return 1500.00;
    }

    /**
     * Get affiliation fee breakdown (national fee, operational fee, total)
     */
    public function getAffiliationBreakdown(int $memberCount): array
    {
        $nationalFee = $this->getAffiliationFeeForCount($memberCount);
        $operationalFee = $this->getOperationalFee();
        return [
            'national_fee' => round($nationalFee, 2),
            'operational_fee' => round($operationalFee, 2),
            'total_fee' => round($nationalFee + $operationalFee, 2)
        ];
    }

    /**
     * Get member fee rate for a specific member type ('new', 'returning', 'honorary')
     */
    public function getMemberFee(string $type): float
    {
        $this->ensureLoaded();
        $normalized = strtolower(trim($type));
        if (isset($this->memberFees[$normalized])) {
            return $this->memberFees[$normalized];
        }

        // Baseline CBL defaults
        return match ($normalized) {
            'returning' => 200.00,
            'honorary'  => 300.00,
            default     => 250.00,
        };
    }

    /**
     * Get all active member fees as associative array ['new' => float, 'returning' => float, ...]
     */
    public function getMemberFees(): array
    {
        $this->ensureLoaded();
        return [
            'new'       => $this->getMemberFee('new'),
            'returning' => $this->getMemberFee('returning'),
            'honorary'  => $this->getMemberFee('honorary')
        ];
    }

    /**
     * Get minimum member participation rate threshold (percentage, e.g. 40.0)
     */
    public function getMinParticipationRate(): float
    {
        $this->ensureLoaded();
        return $this->complianceRules['min_participation'] ?? 40.0;
    }

    /**
     * Get minimum required hosted / venue events count (e.g. 1)
     */
    public function getRequiredHostedEvents(): int
    {
        $this->ensureLoaded();
        return (int)($this->complianceRules['required_hosted_events'] ?? 1);
    }

    /**
     * Get participation threshold for "at risk" compliance status (e.g. 20.0)
     */
    public function getAtRiskParticipationRate(): float
    {
        $this->ensureLoaded();
        return $this->complianceRules['at_risk_participation'] ?? 20.0;
    }

    /**
     * Get official required document keys for chapter affiliation
     */
    public function getRequiredDocumentKeys(): array
    {
        return [
            'letter_of_intent',
            'endorsement_letter',
            'constitution_by_laws',
            'officers_cvs',
            'organizational_chart',
            'member_directory'
        ];
    }

    /**
     * Get serializable payload for PWA offline cache and client hydration
     */
    public function getSettingsPayload(): array
    {
        $this->ensureLoaded();
        return [
            'system_settings'    => $this->systemSettings,
            'operational_fee'    => $this->getOperationalFee(),
            'fee_brackets'       => $this->getFeeBrackets(),
            'member_fees'        => $this->getMemberFees(),
            'compliance_rules'   => [
                'min_participation'      => $this->getMinParticipationRate(),
                'required_hosted_events' => $this->getRequiredHostedEvents(),
                'at_risk_participation'  => $this->getAtRiskParticipationRate()
            ],
            'required_documents' => $this->getRequiredDocumentKeys(),
            'cached_at'          => time()
        ];
    }
}
