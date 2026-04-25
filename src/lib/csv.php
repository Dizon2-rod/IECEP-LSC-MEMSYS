<?php
namespace App\Lib;

class CsvService
{
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
}
