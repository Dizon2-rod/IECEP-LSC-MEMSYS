# IECEP‑LSC MEMSYS cleanup script – removes old roles, backups, and duplicates. Run with caution. Backup first.

[CmdletBinding()]
param()

$repoRoot = Split-Path -Parent $PSScriptRoot

function Remove-TargetPath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $fullPath = Join-Path $repoRoot $RelativePath

    if (-not (Test-Path -LiteralPath $fullPath)) {
        return
    }

    Write-Host "Deleting: $RelativePath"

    try {
        Remove-Item -LiteralPath $fullPath -Force -Recurse -ErrorAction Stop
    }
    catch {
        Write-Warning "Unable to delete $RelativePath : $($_.Exception.Message)"
    }
}

function Remove-EmptyDirectory {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $fullPath = Join-Path $repoRoot $RelativePath

    if (-not (Test-Path -LiteralPath $fullPath -PathType Container)) {
        return
    }

    $childItems = Get-ChildItem -LiteralPath $fullPath -Force
    if ($null -ne $childItems -and $childItems.Count -gt 0) {
        return
    }

    Write-Host "Removing empty directory: $RelativePath"

    try {
        Remove-Item -LiteralPath $fullPath -Force -ErrorAction Stop
    }
    catch {
        Write-Warning "Unable to remove empty directory $RelativePath : $($_.Exception.Message)"
    }
}

Write-Host "Starting obsolete file cleanup from $repoRoot"
Write-Host "--------------------------------------------------"

# Old role-specific API endpoints
$apiTargets = @(
    'public/api/auditor.php',
    'public/api/officer.php',
    'public/api/pro.php',
    'public/api/registration.php',
    'public/api/secretary.php',
    'public/api/super-admin.php',
    'public/api/treasurer.php',
    'public/api/vp-academic.php',
    'public/api/president',
    'public/api/treasurer',
    'public/api/super-admin'
)

foreach ($target in $apiTargets) {
    Remove-TargetPath -RelativePath $target
}

# Old role-specific portal JavaScript
$portalScriptTargets = @(
    'public/js/portal/asst-secretary.js',
    'public/js/portal/auditor.js',
    'public/js/portal/committee.js',
    'public/js/portal/officer.js',
    'public/js/portal/president.js',
    'public/js/portal/pro.js',
    'public/js/portal/registration.js',
    'public/js/portal/secretary.js',
    'public/js/portal/treasurer.js',
    'public/js/portal/vp-academic.js',
    'public/js/portal/vp-external.js',
    'public/js/portal/vp-internal.js'
)

foreach ($target in $portalScriptTargets) {
    Remove-TargetPath -RelativePath $target
}

# Backup and legacy variants
$backupTargets = @(
    'change-password-old.php',
    'public/api/affiliate_backup.php',
    'public/api/affiliate_old.php',
    'public/api/simulate-payment.php.backup'
)

foreach ($target in $backupTargets) {
    Remove-TargetPath -RelativePath $target
}

# Duplicate/legacy CSS and JS assets
$assetTargets = @(
    'public/assets/css/font-awesome.css',
    'public/assets/css/professional.css',
    'public/assets/css/style.css',
    'public/assets/js/affiliate.js',
    'public/assets/js/header.js',
    'public/assets/js/offline-manager.js',
    'public/assets/js/offline.js',
    'public/assets/js/realtime.js',
    'public/assets/js/supabase-auth.js',
    'public/assets/js/supabase-realtime.js',
    'public/assets/js/toast.js',
    'public/css/components.css',
    'public/css/dashboard.css',
    'public/css/design-tokens.css',
    'public/css/landing.css',
    'public/css/portal.css',
    'public/css/responsive.css',
    'public/css/style.css'
)

foreach ($target in $assetTargets) {
    Remove-TargetPath -RelativePath $target
}

# Debug/test helpers
$debugTargets = @(
    'debug-reset-api.php',
    'debug-supabase.php',
    'diagnostic.php',
    'error-checker.php',
    'fix-affiliations.php',
    'fix-requires.php',
    'get-reset-token.php',
    'includes/test-accounts.php',
    'public/api/simulate-payment-fixed.php',
    'public/api/simulate-payment-test.php',
    'public/js/offline-sync-reference.js'
)

foreach ($target in $debugTargets) {
    Remove-TargetPath -RelativePath $target
}

# Old migration and schema files
$migrationTargets = @(
    'database/add_event_id_to_transactions.sql',
    'database/additional_tables.sql',
    'database/enhancements_sql.sql',
    'database/fix_blockchain_schema.sql',
    'database/xampp_localhost_complete_query.sql',
    'database/migrations/002_events_compliance.sql',
    'database/migrations/003_cbl_compliance_system.sql',
    'database/migrations/004_auto_generate_accounts.sql',
    'database/migrations/005_pending_affiliations.sql',
    'database/migrations/006_member_id_counter.sql',
    'migrations/create_revision_requests.sql'
)

foreach ($target in $migrationTargets) {
    Remove-TargetPath -RelativePath $target
}

# Remove empty directories left behind by cleanup
$emptyDirectories = @(
    'public/api/president',
    'public/api/treasurer',
    'public/api/super-admin',
    'public/css'
)

foreach ($dir in $emptyDirectories) {
    Remove-EmptyDirectory -RelativePath $dir
}

Write-Host "Cleanup completed. Review the output above for any files that could not be removed."
