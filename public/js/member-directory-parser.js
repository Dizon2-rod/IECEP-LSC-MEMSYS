/**
 * Member Directory Parser & Fee Calculator
 * Handles CSV/Excel parsing and CBL-compliant fee calculation
 */

class MemberDirectoryParser {
    constructor() {
        this.parsedData = null;
        this.feeCalculation = null;
    }

    /**
     * Parse uploaded file (CSV, XLS, XLSX)
     * @param {File} file - The uploaded file
     * @returns {Promise<Object>} Parsed member data
     */
    async parseFile(file) {
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!['csv', 'xls', 'xlsx'].includes(extension)) {
            throw new Error('Invalid file format. Please upload CSV, XLS, or XLSX file.');
        }

        try {
            const data = await this.readFile(file);
            const workbook = XLSX.read(data, { type: 'binary' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, { defval: '' });

            if (!jsonData || jsonData.length === 0) {
                throw new Error('Invalid File: No member data detected.');
            }

            return this.categorizeMembers(jsonData);
        } catch (error) {
            throw new Error(error.message || 'Failed to parse file.');
        }
    }

    /**
     * Read file as binary string
     * @param {File} file
     * @returns {Promise<string>}
     */
    readFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = () => reject(new Error('Failed to read file.'));
            reader.readAsBinaryString(file);
        });
    }

    /**
     * Categorize members by status
     * @param {Array} data - Parsed JSON data
     * @returns {Object} Categorized member data
     */
    categorizeMembers(data) {
        // Find status column (case-insensitive)
        const firstRow = data[0];
        const statusKey = Object.keys(firstRow).find(key => 
            key.toLowerCase().trim() === 'status'
        );

        if (!statusKey) {
            throw new Error("Invalid Directory Format: Missing 'Status' column.");
        }

        let newMembers = 0;
        let oldMembers = 0;
        let unknownMembers = 0;
        const warnings = [];

        data.forEach((row, index) => {
            const status = (row[statusKey] || '').toString().toLowerCase().trim();
            
            if (status === 'new') {
                newMembers++;
            } else if (['old', 'renewing', 'renewal'].includes(status)) {
                oldMembers++;
            } else {
                unknownMembers++;
                warnings.push(`Row ${index + 2}: Unknown status "${row[statusKey]}"`);
            }
        });

        const totalMembers = newMembers + oldMembers + unknownMembers;

        this.parsedData = {
            total: totalMembers,
            new: newMembers,
            old: oldMembers,
            unknown: unknownMembers,
            warnings: warnings
        };

        return this.parsedData;
    }

    /**
     * Calculate fees based on CBL compliance
     * @param {number} totalMembers - Total member count
     * @returns {Object} Fee breakdown
     */
    calculateFees(totalMembers) {
        const memberCount = parseInt(totalMembers);
        
        if (isNaN(memberCount) || memberCount <= 0) {
            throw new Error('Invalid member count.');
        }

        // Bracketing logic
        let affiliationFee;
        if (memberCount <= 50) {
            affiliationFee = 1500;
        } else if (memberCount <= 100) {
            affiliationFee = 2000;
        } else if (memberCount <= 150) {
            affiliationFee = 2500;
        } else {
            affiliationFee = 3000;
        }

        const operationalFee = 800;
        const totalFee = affiliationFee + operationalFee;

        this.feeCalculation = {
            affiliationFee,
            operationalFee,
            totalFee,
            memberCount
        };

        return this.feeCalculation;
    }

    /**
     * Get parsed data
     * @returns {Object|null}
     */
    getParsedData() {
        return this.parsedData;
    }

    /**
     * Get fee calculation
     * @returns {Object|null}
     */
    getFeeCalculation() {
        return this.feeCalculation;
    }

    /**
     * Reset parser state
     */
    reset() {
        this.parsedData = null;
        this.feeCalculation = null;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MemberDirectoryParser;
}
