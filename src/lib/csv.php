<?php
namespace App\Lib;

require_once __DIR__ . '/../../bootstrap.php';

class CsvService
{
    /**
     * Parse member directory from CSV or Excel file
     */
    public function parseMemberDirectory(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['error' => true, 'message' => 'Member directory file not found'];
        }

        try {
            $rows = $this->extractRawRows($filePath);
            if (empty($rows)) {
                return ['error' => true, 'message' => 'Member directory is empty or could not be read'];
            }

            // Detect header row and column mapping
            $headerAliases = [
                'full_name' => ['full_name', 'fullname', 'name', 'student_name', 'member_name', 'student_full_name', 'complete_name'],
                'email' => ['email', 'email_address', 'e_mail', 'student_email', 'mail', 'email_add'],
                'year_level' => ['year_level', 'year', 'level', 'yearlevel', 'academic_year', 'yr_level', 'year_standing'],
                'member_type' => ['member_type', 'membership_type', 'type', 'status'],
                'student_number' => ['student_number', 'student_id', 'id_number', 'student_no', 'stud_no', 'id_no'],
                'course' => ['course', 'program', 'degree', 'department']
            ];

            $headerRowIndex = null;
            $indexes = [];

            // Scan first 10 rows for header
            $searchLimit = min(10, count($rows));
            for ($r = 0; $r < $searchLimit; $r++) {
                $candidateHeaders = array_map(function ($col): string {
                    $cleaned = strtolower(trim((string)$col));
                    $cleaned = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $cleaned);
                    return trim(preg_replace('/[^a-z0-9]+/', '_', $cleaned), '_') ?: '';
                }, $rows[$r]);

                $candidateIndexes = [];
                foreach ($headerAliases as $field => $aliases) {
                    foreach ($candidateHeaders as $idx => $headerText) {
                        if ($headerText === '') continue;
                        if (in_array($headerText, $aliases, true)) {
                            $candidateIndexes[$field] = $idx;
                            break;
                        }
                    }
                }

                // Check partial containment if exact alias not found
                if (!isset($candidateIndexes['full_name']) || !isset($candidateIndexes['email'])) {
                    foreach ($candidateHeaders as $idx => $headerText) {
                        if (!isset($candidateIndexes['full_name']) && (strpos($headerText, 'name') !== false)) {
                            $candidateIndexes['full_name'] = $idx;
                        }
                        if (!isset($candidateIndexes['email']) && (strpos($headerText, 'email') !== false || strpos($headerText, 'mail') !== false)) {
                            $candidateIndexes['email'] = $idx;
                        }
                        if (!isset($candidateIndexes['year_level']) && (strpos($headerText, 'year') !== false || strpos($headerText, 'level') !== false)) {
                            $candidateIndexes['year_level'] = $idx;
                        }
                        if (!isset($candidateIndexes['student_number']) && (strpos($headerText, 'student') !== false || strpos($headerText, 'id') !== false)) {
                            $candidateIndexes['student_number'] = $idx;
                        }
                        if (!isset($candidateIndexes['course']) && (strpos($headerText, 'course') !== false || strpos($headerText, 'program') !== false)) {
                            $candidateIndexes['course'] = $idx;
                        }
                    }
                }

                if (isset($candidateIndexes['full_name']) || isset($candidateIndexes['email'])) {
                    $headerRowIndex = $r;
                    $indexes = $candidateIndexes;
                    break;
                }
            }

            // Fallback: If no header row detected, check if row 0 has data directly
            if ($headerRowIndex === null) {
                $headerRowIndex = -1; // all rows are data
                $indexes = [
                    'full_name' => 0,
                    'email' => 1,
                    'year_level' => 2,
                    'member_type' => 3,
                ];
            }

            $dataRows = ($headerRowIndex >= 0) ? array_slice($rows, $headerRowIndex + 1) : $rows;
            $members = [];
            $seenEmails = [];

            foreach ($dataRows as $row) {
                if (empty($row) || !is_array($row)) continue;

                $fullName = trim((string)($row[$indexes['full_name'] ?? 0] ?? ''));
                $email = strtolower(trim((string)($row[$indexes['email'] ?? 1] ?? '')));
                $yearLevel = trim((string)($row[$indexes['year_level'] ?? 2] ?? ''));
                $memberTypeRaw = strtolower(trim((string)($row[$indexes['member_type'] ?? 3] ?? 'new')));
                $studentNumber = trim((string)($row[$indexes['student_number'] ?? 4] ?? ''));
                $course = trim((string)($row[$indexes['course'] ?? 5] ?? ''));

                // Strip quotes and zero-width characters
                $fullName = trim($fullName, "\"' \t\n\r\0\x0B");
                $email = trim($email, "\"' \t\n\r\0\x0B");

                // Check if name and email are inverted
                if (filter_var($fullName, FILTER_VALIDATE_EMAIL) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $tmp = $fullName;
                    $fullName = $email;
                    $email = $tmp;
                }

                // If email is missing or invalid, skip
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                // If full name is empty, extract from email username
                if ($fullName === '') {
                    $parts = explode('@', $email);
                    $fullName = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
                }

                // Deduplicate emails within the same file
                if (isset($seenEmails[$email])) {
                    continue;
                }
                $seenEmails[$email] = true;

                // Normalize member_type
                $memberType = (strpos($memberTypeRaw, 'return') !== false || strpos($memberTypeRaw, 'old') !== false)
                    ? 'returning'
                    : 'new';

                // Normalize year level
                if ($yearLevel === '') {
                    $yearLevel = '1st Year';
                }

                $members[] = [
                    'full_name' => $fullName,
                    'email' => $email,
                    'year_level' => $yearLevel,
                    'member_type' => $memberType,
                    'student_number' => $studentNumber,
                    'course' => $course ?: 'Bachelor of Science in Electronics Engineering'
                ];
            }

            if (empty($members)) {
                return [
                    'error' => true,
                    'message' => 'No valid member rows found in the directory. Ensure file contains full name and valid email addresses.'
                ];
            }

            return [
                'error' => false,
                'data' => $members,
                'total_rows' => count($dataRows),
                'valid_count' => count($members)
            ];
        } catch (\Throwable $e) {
            error_log('Member directory parse error: ' . $e->getMessage());
            return ['error' => true, 'message' => 'Unable to parse member directory: ' . $e->getMessage()];
        }
    }

    /**
     * Extract raw tabular rows from file using appropriate strategy
     */
    protected function extractRawRows(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // 1. Try PhpOffice\PhpSpreadsheet if installed
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath)->getActiveSheet();
                $rows = $sheet->toArray('', true, true, false);
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $ex) {
                error_log("PhpSpreadsheet parse fallback: " . $ex->getMessage());
            }
        }

        // 2. If XLSX / XLS and PhpSpreadsheet is missing, extract via XML
        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            $xlsxRows = $this->parseXlsxViaXml($filePath);
            if (!empty($xlsxRows)) {
                return $xlsxRows;
            }
        }

        // 3. Parse as CSV / plain delimited text
        return $this->parseCsvRows($filePath);
    }

    /**
     * Parse raw CSV file handling delimiters, BOM, and enclosures
     */
    public function parseCsvRows(string $filePath): array
    {
        if (!file_exists($filePath)) return [];

        $content = file_get_contents($filePath);
        if ($content === false || trim($content) === '') return [];

        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Detect delimiter by inspecting first line
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ',';
        $commaCount = substr_count($firstLine, ',');
        $tabCount = substr_count($firstLine, "\t");
        $semiCount = substr_count($firstLine, ';');

        if ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        } elseif ($semiCount > $commaCount) {
            $delimiter = ';';
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $row = str_getcsv($line, $delimiter, '"', '\\');
            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Parse XLSX file using native ZIP and SimpleXML
     */
    public function parseXlsxViaXml(string $filePath): array
    {
        $tempDir = sys_get_temp_dir() . '/memsys_xlsx_' . uniqid();
        @mkdir($tempDir, 0777, true);

        $extracted = false;

        // Try ZipArchive
        if (class_exists('\\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
                $extracted = true;
            }
        }

        // Fallback: Use system tar.exe (available on Windows 10/11)
        if (!$extracted) {
            $escapedFile = escapeshellarg($filePath);
            $escapedDir = escapeshellarg($tempDir);
            @exec("tar -xf {$escapedFile} -C {$escapedDir} 2>&1", $out, $code);
            if ($code === 0 && file_exists($tempDir . '/xl/worksheets/sheet1.xml')) {
                $extracted = true;
            }
        }

        if (!$extracted) {
            $this->cleanTempDir($tempDir);
            return [];
        }

        try {
            $sharedStrings = [];
            $sharedStringsFile = $tempDir . '/xl/sharedStrings.xml';
            if (file_exists($sharedStringsFile)) {
                $xml = simplexml_load_file($sharedStringsFile);
                if ($xml && isset($xml->si)) {
                    foreach ($xml->si as $si) {
                        $sharedStrings[] = (string)($si->t ?? ($si->r->t ?? ''));
                    }
                }
            }

            $sheetFile = $tempDir . '/xl/worksheets/sheet1.xml';
            if (!file_exists($sheetFile)) {
                $this->cleanTempDir($tempDir);
                return [];
            }

            $sheetXml = simplexml_load_file($sheetFile);
            if (!$sheetXml || !isset($sheetXml->sheetData->row)) {
                $this->cleanTempDir($tempDir);
                return [];
            }

            $rows = [];
            foreach ($sheetXml->sheetData->row as $r) {
                $rowCells = [];
                $maxCol = 0;

                foreach ($r->c as $c) {
                    $cellRef = (string)$c['r'];
                    preg_match('/^([A-Z]+)(\d+)$/', $cellRef, $matches);
                    $colLetters = $matches[1] ?? 'A';
                    $colIndex = $this->columnLetterToIndex($colLetters);
                    $maxCol = max($maxCol, $colIndex);

                    $type = (string)$c['t'];
                    $val = (string)$c->v;

                    if ($type === 's' && isset($sharedStrings[(int)$val])) {
                        $cellVal = $sharedStrings[(int)$val];
                    } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                        $cellVal = (string)$c->is->t;
                    } else {
                        $cellVal = $val;
                    }

                    $rowCells[$colIndex] = $cellVal;
                }

                $formattedRow = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $formattedRow[$i] = $rowCells[$i] ?? '';
                }
                $rows[] = $formattedRow;
            }

            $this->cleanTempDir($tempDir);
            return $rows;
        } catch (\Throwable $e) {
            error_log("XLSX XML Parse Error: " . $e->getMessage());
            $this->cleanTempDir($tempDir);
            return [];
        }
    }

    /**
     * Helper to clean temporary directory
     */
    protected function cleanTempDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }

    /**
     * Convert Excel column letters (A, B, ..., Z, AA, AB) to 0-based index
     */
    protected function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    /**
     * Standard CSV parse function for other modules
     */
    public function parse(string $filePath, bool $hasHeader = true): array
    {
        if (!file_exists($filePath)) {
            return ['error' => true, 'message' => 'File not found'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['error' => true, 'message' => 'Cannot open file'];
        }

        $rows = [];
        $headers = [];
        $lineNum = 0;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $lineNum++;
            if ($hasHeader && $lineNum === 1) {
                $headers = array_map('trim', $row);
                $required = ['full_name', 'email', 'member_type', 'year_level'];
                $missing = array_diff($required, $headers);
                if (!empty($missing)) {
                    fclose($handle);
                    return ['error' => true, 'message' => 'Missing columns: ' . implode(', ', $missing)];
                }
                continue;
            }

            if (count($row) < count($headers)) {
                continue;
            }

            $rowData = [];
            foreach ($headers as $idx => $header) {
                $rowData[$header] = trim($row[$idx] ?? '');
            }

            if (empty($rowData['full_name']) || empty($rowData['email'])) {
                continue;
            }

            $validTypes = ['new', 'returning', 'honorary'];
            if (!in_array(strtolower($rowData['member_type']), $validTypes)) {
                $rowData['member_type'] = 'new';
            } else {
                $rowData['member_type'] = strtolower($rowData['member_type']);
            }

            $rows[] = $rowData;
        }

        fclose($handle);
        return ['error' => false, 'data' => $rows, 'headers' => $headers];
    }

    public function generateTemplate(): string
    {
        return "full_name,email,member_type,year_level\n";
    }

    public function validateEmails(array $rows, string $excludeInstitutionEmail = ''): array
    {
        $errors = [];
        $emails = [];

        foreach ($rows as $idx => $row) {
            $email = strtolower($row['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row " . ($idx + 1) . ": Invalid email '{$row['email']}'";
                continue;
            }
            if (in_array($email, $emails, true)) {
                $errors[] = "Row " . ($idx + 1) . ": Duplicate email '{$row['email']}' in file";
                continue;
            }
            $emails[] = $email;
        }

        return $errors;
    }

    public function exportToExcel(array $data, array $headers, string $filePath): bool
    {
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            try {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                $col = 1;
                foreach ($headers as $header) {
                    $sheet->setCellValueByColumnAndRow($col, 1, $header);
                    $col++;
                }

                $row = 2;
                foreach ($data as $rowData) {
                    $col = 1;
                    foreach ($headers as $header) {
                        $sheet->setCellValueByColumnAndRow($col, $row, $rowData[$header] ?? '');
                        $col++;
                    }
                    $row++;
                }

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save($filePath);
                return true;
            } catch (\Throwable $e) {
                error_log("Excel export error: " . $e->getMessage());
            }
        }

        // CSV fallback if PhpSpreadsheet is unavailable
        $fp = @fopen($filePath, 'w');
        if (!$fp) return false;
        fputcsv($fp, $headers);
        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($fp, $line);
        }
        fclose($fp);
        return true;
    }
}
