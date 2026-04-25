// IECEP-LSC MEMSYS - Web3 (ethers.js) for payment verification
let ethersInstance = null;

async function initWeb3() {
    if (typeof ethers !== 'undefined') {
        ethersInstance = ethers;
        return ethersInstance;
    }
    return null;
}

async function verifyPaymentOnChain(txHash, rpcUrl) {
    try {
        const provider = new ethers.JsonRpcProvider(rpcUrl || 'https://sepolia.infura.io/v3/');
        const receipt = await provider.getTransactionReceipt(txHash);

        if (!receipt) {
            return { verified: false, message: 'Transaction not found on blockchain' };
        }

        return {
            verified: receipt.status === 1,
            blockNumber: receipt.blockNumber,
            from: receipt.from,
            gasUsed: receipt.gasUsed.toString(),
            etherscanUrl: `https://sepolia.etherscan.io/tx/${txHash}`,
        };
    } catch (err) {
        console.error('Web3 verification error:', err);
        return { verified: false, message: err.message };
    }
}
