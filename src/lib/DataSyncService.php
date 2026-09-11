<?php
declare(strict_types=1);

namespace App\Lib;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/FinancialSyncService.php';
require_once __DIR__ . '/ComplianceEngine.php';
require_once __DIR__ . '/BlockchainService.php';

/**
 * DataSyncService - Enterprise Centralized System Synchronizer
 * 
 * Synchronizes all core entities across the entire IECEP-LSC MEMSYS platform:
 * 1. Institutions & Affiliations linkage
 * 2. Member rosters, student enrollment counts, and orphan member resolution
 * 3. Official CBL requirement documents cross-synchronization (pending_affiliations <-> documents)
 * 4. Financial totals, 2025 CBL bracket fee audits, and receipts
 * 5. Compliance monitoring scores, event hosting/venue credits, and statuses
 * 6. Cryptographic blockchain ledger state anchoring
 */
class DataSyncService
{
    private SupabaseClient $supabase;
    private ?BlockchainService $blockchain = null;
    private ?FinancialSyncService $financialSync = null;
    private ?ComplianceEngine $complianceEngine = null;

    public function __construct(?SupabaseClient $supabase = null)
    {
        if ($supabase) {
            $this->supabase = $supabase;
        } else {
            $config = require __DIR__ . '/../../includes/supabase.php';
            $this->supabase = new SupabaseClient($config['url'], $config['service_role_key']);
        }
    }

    private function getBlockchain(): BlockchainService
    {
        if ($this->blockchain === null) {
            $this->blockchain = new BlockchainService($this->supabase);
        }
        return $this->blockchain;
    }

    private function getFinancialSync(): FinancialSyncService
    {
        if ($this->financialSync === null) {
            $this->financialSync = new FinancialSyncService($this->supabase);
        }
        return $this->financialSync;
    }

    private function getComplianceEngine(): ComplianceEngine
    {
        if ($this->complianceEngine === null) {
            $this->complianceEngine = new ComplianceEngine($this->supabase, $this->getBlockchain());
        }
        return $this->complianceEngine;
    }

    /**
     * Master synchronization routine that runs all 6 core synchronization modules.
     *
     * @param string|null $performedBy
     * @return array
     */
    public function syncAll(?string $performedBy = null): array
    {
        $startTime = microtime(true);
        $now = date('c');

        $log = [];
        $stats = [
            'institutions_processed' => 0,
            'affiliations_linked'    => 0,
            'members_linked'         => 0,
            'counts_updated'         => 0,
            'documents_synced'       => 0,
            'financials_updated'     => 0,
            'compliance_evaluated'   => 0,
            'blockchain_blocks'      => 0
        ];

        // 1. Synchronize Institutions & Pending Affiliations
        try {
            $instAffilRes = $this->syncInstitutionsAndAffiliations();
            $stats['institutions_processed'] = $instAffilRes['institutions_count'];
            $stats['affiliations_linked'] = $instAffilRes['linked_count'];
            $log[] = "Phase 1 (Institutions & Affiliations): {$instAffilRes['linked_count']} affiliations linked, {$instAffilRes['institutions_count']} chapters aligned.";
        } catch (\Throwable $e1) {
            $log[] = "Phase 1 Warning: " . $e1->getMessage();
            error_log("DataSyncService Phase 1: " . $e1->getMessage());
        }

        // 2. Synchronize Member Rosters & Enrollment Counts
        try {
            $memberRes = $this->syncMembersAndRosters();
            $stats['members_linked'] = $memberRes['members_linked'];
            $stats['counts_updated'] = $memberRes['counts_updated'];
            $log[] = "Phase 2 (Member Rosters): {$memberRes['members_linked']} orphan members linked, {$memberRes['counts_updated']} institution roster counts updated.";
        } catch (\Throwable $e2) {
            $log[] = "Phase 2 Warning: " . $e2->getMessage();
            error_log("DataSyncService Phase 2: " . $e2->getMessage());
        }

        // 3. Synchronize Requirement Documents & Dossiers
        try {
            $docRes = $this->syncDocumentsAndRequirements();
            $stats['documents_synced'] = $docRes['documents_synced'];
            $log[] = "Phase 3 (Requirements): {$docRes['documents_synced']} requirement files cross-synchronized between repository and applications.";
        } catch (\Throwable $e3) {
            $log[] = "Phase 3 Warning: " . $e3->getMessage();
            error_log("DataSyncService Phase 3: " . $e3->getMessage());
        }

        // 4. Synchronize Financial Totals & 2025 CBL Fee Brackets
        try {
            $finRes = $this->syncFinancials($performedBy);
            $stats['financials_updated'] = $finRes['processed'];
            $log[] = "Phase 4 (Finances): {$finRes['processed']} institution ledgers synced with 2025 CBL brackets (Affiliation + ₱800 Op Fee + ₱200/student).";
        } catch (\Throwable $e4) {
            $log[] = "Phase 4 Warning: " . $e4->getMessage();
            error_log("DataSyncService Phase 4: " . $e4->getMessage());
        }

        // 5. Synchronize Compliance Scores & Monitoring
        try {
            $compRes = $this->syncComplianceAndMonitoring();
            $stats['compliance_evaluated'] = count($compRes);
            $log[] = "Phase 5 (Compliance): " . count($compRes) . " chapters evaluated against Art. V Sec. 3 participation & event hosting benchmarks.";
        } catch (\Throwable $e5) {
            $log[] = "Phase 5 Warning: " . $e5->getMessage();
            error_log("DataSyncService Phase 5: " . $e5->getMessage());
        }

        // 6. Synchronize Blockchain Ledger State
        try {
            $bcRes = $this->syncBlockchainLedger();
            $stats['blockchain_blocks'] = $bcRes['blocks_recorded'];
            $log[] = "Phase 6 (Blockchain Ledger): {$bcRes['blocks_recorded']} verified transaction and affiliation blocks anchored to cryptographic ledger.";
        } catch (\Throwable $e6) {
            $log[] = "Phase 6 Warning: " . $e6->getMessage();
            error_log("DataSyncService Phase 6: " . $e6->getMessage());
        }

        $duration = round(microtime(true) - $startTime, 3);

        return [
            'success'      => true,
            'message'      => 'Full system data synchronization completed successfully in ' . $duration . 's',
            'timestamp'    => $now,
            'duration_sec' => $duration,
            'stats'        => $stats,
            'log'          => $log
        ];
    }

    /**
     * Phase 1: Link and align Institutions and Pending Affiliations.
     */
    public function syncInstitutionsAndAffiliations(): array
    {
        $institutions = $this->supabase->select('institutions', ['select' => '*']);
        if (!is_array($institutions)) $institutions = [];

        $rawApps = $this->supabase->select('pending_affiliations', ['select' => '*']);
        if (!is_array($rawApps)) $rawApps = [];

        // Build index of institutions by ID, normalized name, and email
        $instById = [];
        $instByName = [];
        $instByEmail = [];
        foreach ($institutions as $inst) {
            $id = $inst['id'] ?? '';
            if (!$id) continue;
            $instById[$id] = $inst;
            if (!empty($inst['name'])) {
                $instByName[$this->normalizeString($inst['name'])] = $inst;
            }
            if (!empty($inst['acronym'])) {
                $instByName[$this->normalizeString($inst['acronym'])] = $inst;
            }
            if (!empty($inst['email'])) {
                $instByEmail[strtolower(trim($inst['email']))] = $inst;
            }
        }

        $linkedCount = 0;
        foreach ($rawApps as $app) {
            $appId = $app['id'] ?? '';
            if (!$appId) continue;

            $currentInstId = $app['institution_id'] ?? '';
            $appName = $app['institution_name'] ?? ($app['school_name'] ?? '');
            $appEmail = $app['contact_email'] ?? ($app['email'] ?? '');

            $matchedInst = null;
            if ($currentInstId && isset($instById[$currentInstId])) {
                $matchedInst = $instById[$currentInstId];
            } else {
                if ($appName && isset($instByName[$this->normalizeString($appName)])) {
                    $matchedInst = $instByName[$this->normalizeString($appName)];
                } elseif ($appEmail && isset($instByEmail[strtolower(trim($appEmail))])) {
                    $matchedInst = $instByEmail[strtolower(trim($appEmail))];
                }
            }

            if ($matchedInst) {
                $updates = [];
                if ($currentInstId !== $matchedInst['id']) {
                    $updates['institution_id'] = $matchedInst['id'];
                }
                if (empty($app['acronym']) && !empty($matchedInst['acronym'])) {
                    $updates['acronym'] = $matchedInst['acronym'];
                }
                if (empty($app['contact_person']) && !empty($matchedInst['contact_person'])) {
                    $updates['contact_person'] = $matchedInst['contact_person'];
                }
                if (empty($app['contact_phone']) && !empty($matchedInst['contact_phone'])) {
                    $updates['contact_phone'] = $matchedInst['contact_phone'];
                }

                if (!empty($updates)) {
                    $updates['updated_at'] = date('c');
                    try {
                        $this->supabase->update('pending_affiliations', $updates, $appId);
                        $linkedCount++;
                    } catch (\Throwable $e) {}
                }

                // If affiliation is approved, ensure institution status is active
                $st = strtolower($app['status'] ?? '');
                if ($st === 'approved' && ($matchedInst['status'] ?? '') !== 'active') {
                    try {
                        $this->supabase->update('institutions', ['status' => 'active', 'updated_at' => date('c')], $matchedInst['id']);
                    } catch (\Throwable $e) {}
                }
            }
        }

        return [
            'institutions_count' => count($institutions),
            'linked_count'       => $linkedCount
        ];
    }

    /**
     * Phase 2: Link orphan student members and update institution membership counts.
     */
    public function syncMembersAndRosters(): array
    {
        $institutions = $this->supabase->select('institutions', ['select' => '*']);
        if (!is_array($institutions)) $institutions = [];

        $instLookup = [];
        foreach ($institutions as $inst) {
            $id = $inst['id'] ?? '';
            if (!$id) continue;
            if (!empty($inst['name'])) {
                $instLookup[$this->normalizeString($inst['name'])] = $id;
            }
            if (!empty($inst['acronym'])) {
                $instLookup[$this->normalizeString($inst['acronym'])] = $id;
            }
        }

        $members = $this->supabase->select('members', ['select' => 'id,institution_id,school_name,status']);
        if (!is_array($members)) $members = [];

        $membersLinked = 0;
        $countsPerInst = [];

        foreach ($members as $m) {
            $mId = $m['id'] ?? '';
            if (!$mId) continue;

            $instId = $m['institution_id'] ?? '';
            $schoolName = $m['school_name'] ?? '';

            // Resolve orphan member
            if (!$instId && $schoolName) {
                $normSchool = $this->normalizeString($schoolName);
                if (isset($instLookup[$normSchool])) {
                    $instId = $instLookup[$normSchool];
                    try {
                        $this->supabase->update('members', ['institution_id' => $instId], $mId);
                        $membersLinked++;
                    } catch (\Throwable $e) {}
                }
            }

            if ($instId) {
                if (!isset($countsPerInst[$instId])) {
                    $countsPerInst[$instId] = 0;
                }
                $st = strtolower($m['status'] ?? 'active');
                if ($st === 'active' || $st === 'verified' || $st === 'enrolled') {
                    $countsPerInst[$instId]++;
                }
            }
        }

        // Update membership_count in institutions
        $countsUpdated = 0;
        foreach ($institutions as $inst) {
            $id = $inst['id'] ?? '';
            if (!$id) continue;

            $realCount = $countsPerInst[$id] ?? 0;
            $currentCount = intval($inst['membership_count'] ?? 0);

            if ($realCount > 0 && $realCount !== $currentCount) {
                try {
                    $this->supabase->update('institutions', [
                        'membership_count' => $realCount,
                        'updated_at' => date('c')
                    ], $id);
                    $countsUpdated++;
                } catch (\Throwable $e) {}
            }
        }

        return [
            'members_linked' => $membersLinked,
            'counts_updated' => $countsUpdated
        ];
    }

    /**
     * Phase 3: Cross-synchronize canonical CBL requirement files between pending_affiliations & documents.
     */
    public function syncDocumentsAndRequirements(): array
    {
        $docs = $this->supabase->select('documents', ['select' => '*']);
        if (!is_array($docs)) $docs = [];

        $apps = $this->supabase->select('pending_affiliations', ['select' => '*']);
        if (!is_array($apps)) $apps = [];

        $institutions = $this->supabase->select('institutions', ['select' => 'id,name,acronym']);
        if (!is_array($institutions)) $institutions = [];
        $instNameMap = [];
        foreach ($institutions as $inst) {
            if (!empty($inst['id'])) {
                $instNameMap[$inst['id']] = $inst['name'] ?? 'Chapter';
            }
        }

        // Index existing documents by [institution_id][category]
        $docMap = [];
        foreach ($docs as $d) {
            $iid = $d['institution_id'] ?? '';
            $cat = $d['category'] ?? '';
            if ($iid && $cat) {
                $docMap[$iid][$cat] = $d;
            }
        }

        $canonicalDefinitions = [
            'letter_of_intent'    => ['field' => 'letter_of_intent', 'title' => 'Letter of Intent (Art. IV Sec. 3)'],
            'endorsement_letter'  => ['field' => 'endorsement_letter', 'title' => 'Dean / Chair Endorsement Letter'],
            'constitution_bylaws' => ['field' => 'constitution_by_laws', 'alt' => 'constitution_bylaws', 'title' => 'Student Chapter Constitution & By-Laws (CBL)'],
            'officers_cv'         => ['field' => 'officers_cvs', 'alt' => 'officers_cv', 'title' => 'Incumbent Officers Directory & CVs'],
            'org_chart'           => ['field' => 'organizational_chart', 'alt' => 'org_chart', 'title' => 'Organizational Structure Chart'],
            'member_directory'    => ['field' => 'member_directory', 'title' => 'Certified Student Member Directory'],
        ];

        $syncedDocsCount = 0;
        $now = date('c');

        foreach ($apps as $app) {
            $instId = $app['institution_id'] ?? '';
            if (!$instId) continue;

            $instName = $instNameMap[$instId] ?? ($app['institution_name'] ?? 'Chapter');

            foreach ($canonicalDefinitions as $catKey => $def) {
                $appFileUrl = $app[$def['field']] ?? ($app[$def['alt'] ?? ''] ?? '');
                $existingDoc = $docMap[$instId][$catKey] ?? null;

                // Case 1: File URL exists in pending_affiliations but not in documents
                if (!empty($appFileUrl) && !$existingDoc) {
                    $ext = strtolower(pathinfo($appFileUrl, PATHINFO_EXTENSION)) ?: 'pdf';
                    $docId = bin2hex(random_bytes(16));
                    try {
                        $this->supabase->insert('documents', [[
                            'id'             => $docId,
                            'institution_id' => $instId,
                            'title'          => $def['title'],
                            'category'       => $catKey,
                            'description'    => "Canonical CBL Affiliation Requirement for {$instName}",
                            'file_url'       => $appFileUrl,
                            'file_path'      => $appFileUrl,
                            'file_type'      => $ext,
                            'uploaded_by'    => $app['contact_email'] ?? 'Chapter Officer',
                            'created_at'     => $app['created_at'] ?? $now,
                            'updated_at'     => $now
                        ]]);
                        $syncedDocsCount++;
                        $docMap[$instId][$catKey] = ['file_url' => $appFileUrl];
                    } catch (\Throwable $e) {}
                }

                // Case 2: Document exists in documents table, but pending_affiliations canonical field is empty
                if ($existingDoc && !empty($existingDoc['file_url']) && empty($appFileUrl)) {
                    try {
                        $this->supabase->update('pending_affiliations', [
                            $def['field'] => $existingDoc['file_url'],
                            'updated_at' => $now
                        ], $app['id']);
                        $syncedDocsCount++;
                    } catch (\Throwable $e) {}
                }
            }
        }

        return [
            'documents_synced' => $syncedDocsCount
        ];
    }

    /**
     * Phase 4: Synchronize Financials using 2025 CBL brackets.
     */
    public function syncFinancials(?string $performedBy = null): array
    {
        $finService = $this->getFinancialSync();
        $results = $finService->syncAllInstitutions($performedBy);

        // Also ensure pending_affiliations fee totals match Constitution Article IV
        $apps = $this->supabase->select('pending_affiliations', ['select' => '*']);
        if (is_array($apps)) {
            foreach ($apps as $app) {
                $appId = $app['id'] ?? '';
                if (!$appId) continue;

                $members = max(1, intval($app['total_members'] ?? 1));
                
                // CBL 2025 Brackets:
                // Tier 1: 1-50 members   -> ₱1,500
                // Tier 2: 51-100 members  -> ₱2,000
                // Tier 3: 101-150 members -> ₱2,500
                // Tier 4: 151+ members    -> ₱3,000
                $affilFee = ($members <= 50) ? 1500.00 : (($members <= 100) ? 2000.00 : (($members <= 150) ? 2500.00 : 3000.00));
                $opFee = 800.00;
                $memFee = $members * 200.00;
                $totalFee = $affilFee + $opFee + $memFee;

                $storedTotal = floatval($app['total_fee'] ?? 0);
                if (abs($storedTotal - $totalFee) > 0.01) {
                    try {
                        $this->supabase->update('pending_affiliations', [
                            'affiliation_fee'  => ($affilFee + $opFee),
                            'membership_total' => $memFee,
                            'total_fee'        => $totalFee,
                            'updated_at'       => date('c')
                        ], $appId);
                    } catch (\Throwable $e) {}
                }
            }
        }

        return [
            'processed' => count($results),
            'results'   => $results
        ];
    }

    /**
     * Phase 5: Recalculate and synchronize compliance scores and status.
     */
    public function syncComplianceAndMonitoring(): array
    {
        $engine = $this->getComplianceEngine();
        $currentYear = intval(date('Y'));
        return $engine->calculateAll($currentYear);
    }

    /**
     * Phase 6: Ensure verified transactions, receipts, and approved affiliations are anchored in blockchain ledger.
     */
    public function syncBlockchainLedger(): array
    {
        $bc = $this->getBlockchain();
        $blocksRecorded = 0;

        // Anchor approved affiliations
        $apps = $this->supabase->select('pending_affiliations', ['status' => 'eq.approved']);
        if (is_array($apps)) {
            foreach ($apps as $app) {
                $appId = $app['id'] ?? '';
                if (!$appId) continue;

                $rcpNo = $app['receipt_number'] ?? ('RCP-' . substr(md5($appId), 0, 8));
                try {
                    $bc->record('receipt', $rcpNo, [
                        'affiliation_id'   => $appId,
                        'institution_name' => $app['institution_name'] ?? 'School Chapter',
                        'total_members'    => $app['total_members'] ?? 0,
                        'total_fee'        => $app['total_fee'] ?? 0,
                        'receipt_number'   => $rcpNo,
                        'verified_at'      => $app['updated_at'] ?? date('c')
                    ], $app['institution_id'] ?? null);
                    $blocksRecorded++;
                } catch (\Throwable $e) {}
            }
        }

        return [
            'blocks_recorded' => $blocksRecorded
        ];
    }

    /**
     * Helper to normalize text strings for safe, fuzzy matching.
     */
    private function normalizeString(string $str): string
    {
        $s = strtolower($str);
        $s = preg_replace('/[^\w\s]/', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
