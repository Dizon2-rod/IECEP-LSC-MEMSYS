<?php
namespace App\Lib;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../bootstrap.php';
class CsvService
{
    public function parseMemberDirectory(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['error' => true, 'message' => 'Member directory file not found'];
        }

        try {
            $sheet = IOFactory::load($filePath)->getActiveSheet();
            $rows = $sheet->toArray('', true, true, false);
            if (empty($rows)) {
                return ['error' => true, 'message' => 'Member directory is empty'];
            }

            $headerAliases = [
                'full_name' => ['full_name', 'fullname', 'name', 'student_name', 'member_name'],
                'email' => ['email', 'email_address', 'e_mail'],
                'year_level' => ['year_level', 'year', 'level', 'yearlevel'],
                'member_type' => ['member_type', 'membership_type', 'type'],
                'student_number' => ['student_number', 'student_id', 'id_number', 'student_no'],
                'course' => ['course', 'program', 'degree']
            ];

            $headers = array_map(static function ($header): string {
                $header = strtolower(trim((string)$header));
                return preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';
            }, $rows[0]);
            $indexes = [];
            foreach ($headerAliases as $field => $aliases) {
                foreach ($headers as $index => $header) {
                    if (in_array($header, $aliases, true)) {
                        $indexes[$field] = $index;
                        break;
                    }
                }
            }

            if (!isset($indexes['full_name'], $indexes['email'])) {
                return ['error' => true, 'message' => 'Member directory must contain full name and email columns'];
            }

            $members = [];
            $seenEmails = [];
            foreach (array_slice($rows, 1) as $rowNumber => $row) {
                $fullName = trim((string)($row[$indexes['full_name']] ?? ''));
                $email = strtolower(trim((string)($row[$indexes['email']] ?? '')));
                if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                    continue;
                }
                $seenEmails[$email] = true;
                $members[] = [
                    'full_name' => $fullName,
                    'email' => $email,
                    'year_level' => trim((string)($row[$indexes['year_level']] ?? '')),
                    'member_type' => strtolower(trim((string)($row[$indexes['member_type']] ?? 'new'))) ?: 'new',
                    'student_number' => trim((string)($row[$indexes['student_number']] ?? '')),
                    'course' => trim((string)($row[$indexes['course']] ?? ''))
                ];
            }

            return ['error' => false, 'data' => $members, 'headers' => $headers];
        } catch (\Throwable $e) {
            error_log('Member directory parse error: ' . $e->getMessage());
            return ['error' => true, 'message' => 'Unable to parse member directory: ' . $e->getMessage()];
        }
    }

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
                // Validate required columns
                $required = ['full_name', 'email', 'member_type', 'year_level'];
                $missing = array_diff($required, $headers);
                if (!empty($missing)) {
                    fclose($handle);
                    return ['error' => true, 'message' => 'Missing columns: ' . implode(', ', $missing)];
                }
                continue;
            }

            if (count($row) < count($headers)) {
                continue; // skip malformed rows
            }

            $rowData = [];
            foreach ($headers as $idx => $header) {
                $rowData[$header] = trim($row[$idx] ?? '');
            }

            // Validate row data
            if (empty($rowData['full_name']) || empty($rowData['email'])) {
                continue; // skip rows without name or email
            }

            // Validate member_type
            $validTypes = ['new', 'returning', 'honorary'];
            if (!in_array(strtolower($rowData['member_type']), $validTypes)) {
                $rowData['member_type'] = 'new'; // default
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
            $email = strtolower($row['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row " . ($idx + 1) . ": Invalid email '{$row['email']}'";
                continue;
            }
            if (in_array($email, $emails)) {
                $errors[] = "Row " . ($idx + 1) . ": Duplicate email '{$row['email']}' in file";
                continue;
            }
            $emails[] = $email;
        }

        return $errors;
    }

    public function exportToExcel(array $data, array $headers, string $filePath): bool
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Set data
            $row = 2;
            foreach ($data as $rowData) {
                $col = 1;
                foreach ($headers as $header) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $rowData[$header] ?? '');
                    $col++;
                }
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            return true;
        } catch (\Exception $e) {
            error_log("Excel export error: " . $e->getMessage());
            return false;
        }
    }
}
