#!/usr/bin/env node
/**
 * Generate VAPID keys for Web Push Notifications
 * Usage: node generate-vapid.js
 */

const crypto = require('crypto');

// Generate key pair
const { publicKey, privateKey } = crypto.generateKeyPairSync('ec', {
    namedCurve: 'prime256v1',
    publicKeyEncoding: {
        type: 'spki',
        format: 'der'
    },
    privateKeyEncoding: {
        type: 'pkcs8',
        format: 'der'
    }
});

// Convert to base64
const publicKeyB64 = publicKey.toString('base64');
const privateKeyB64 = privateKey.toString('base64');

console.log('\n=== VAPID Key Pair Generated ===\n');
console.log('PUBLIC KEY (add to head-meta.php):');
console.log(publicKeyB64);
console.log('\nPRIVATE KEY (store securely in environment):');
console.log(privateKeyB64);
console.log('\n=== Configuration Instructions ===\n');
console.log('1. Add to includes/head-meta.php in the <script> tag:');
console.log(`   window.PWA_PUBLIC_VAPID_KEY = '${publicKeyB64}';`);
console.log('\n2. Store private key as environment variable:');
console.log('   PWA_PRIVATE_VAPID_KEY=' + privateKeyB64);
console.log('\n3. Or add to .env file (not tracked in git)\n');
