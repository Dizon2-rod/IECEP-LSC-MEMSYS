# Truffle Setup Guide for IECEP-LSC-MEMSYS Blockchain

This guide provides complete setup instructions for using Truffle framework instead of Hardhat.

## Installation

### 1. Install Truffle Dependencies

```bash
cd blockchain
npm install
```

Or if switching from Hardhat to Truffle:

```bash
cd blockchain
rm package.json package-lock.json
mv package-truffle.json package.json
npm install
```

### 2. Install Truffle Globally (Optional)

```bash
npm install -g truffle
```

### 3. Install Ganache (Local Blockchain)

```bash
npm install -g ganache-cli
```

Or download Ganache GUI from: https://trufflesuite.com/ganache/

## Configuration Files

### 1. Environment Variables (.env)

Copy the example file and add your credentials:

```bash
cp .env.example .env
```

Edit `.env` with your actual values:
- `SEPOLIA_RPC_URL`: Your Infura Sepolia RPC URL
- `PRIVATE_KEY`: Your wallet private key (without 0x prefix)
- `ETHERSCAN_API_KEY`: Your Etherscan API key for contract verification

### 2. Truffle Config (truffle-config.js)

The configuration includes:
- Multiple network configurations (development, sepolia, mainnet, polygon)
- Solidity compiler settings (0.8.20 with optimizer)
- HDWalletProvider for secure transaction signing
- Contract verification plugins

## Directory Structure

```
blockchain/
├── contracts/          # Solidity smart contracts
│   └── PaymentLedger.sol
├── migrations/         # Deployment scripts
│   └── 1_deploy_payment_ledger.js
├── test/              # Test files
│   └── PaymentLedger.test.js
├── scripts/           # Utility scripts
├── build/             # Compiled artifacts (auto-generated)
├── truffle-config.js  # Truffle configuration
├── package.json       # Node dependencies
└── .env               # Environment variables (not in git)
```

## Common Commands

### Compilation

```bash
truffle compile
```

### Running Tests

```bash
# Run all tests
truffle test

# Run specific test file
truffle test test/PaymentLedger.test.js

# Run tests with coverage
npm run test:coverage
```

### Deployment

```bash
# Deploy to local development network
npm run migrate:local

# Deploy to Sepolia testnet
npm run migrate:sepolia

# Deploy to Ethereum mainnet
npm run migrate:mainnet

# Deploy to Polygon
npm run migrate:polygon
```

### Local Development

```bash
# Start Ganache local blockchain
npm run node

# Or with specific mnemonic
npm run node:mnemonic

# Open Truffle console
truffle console

# In console, you can interact with contracts
truffle(development)> const ledger = await PaymentLedger.deployed()
truffle(development)> await ledger.addPayment("0x...", 1000)
```

### Contract Verification

```bash
# Verify contract on Etherscan
truffle run verify <CONTRACT_ADDRESS> --network sepolia

# Verify with constructor arguments
truffle run verify <CONTRACT_ADDRESS> --network sepolia "<constructor-args>"
```

## Network Configuration

### Development Network

Uses Hardhat Network or Ganache:
- Host: 127.0.0.1
- Port: 8545
- Chain ID: 1337 (Hardhat) or 5777 (Ganache)

### Sepolia Testnet

- Chain ID: 11155111
- Gas Price: 20 gwei
- Confirmations: 2

### Polygon Mainnet

- Chain ID: 137
- Gas Price: 100 gwei
- Confirmations: 2

## Migration Scripts

Create migration files in `migrations/` directory:

```javascript
// migrations/1_deploy_payment_ledger.js
const PaymentLedger = artifacts.require("PaymentLedger");

module.exports = function(deployer) {
  deployer.deploy(PaymentLedger);
};
```

## Testing

Write tests in `test/` directory:

```javascript
// test/PaymentLedger.test.js
const PaymentLedger = artifacts.require("PaymentLedger");

contract("PaymentLedger", (accounts) => {
  it("should deploy successfully", async () => {
    const ledger = await PaymentLedger.deployed();
    assert(ledger.address !== "");
  });
});
```

## Switching from Hardhat to Truffle

If you want to switch from Hardhat to Truffle:

1. Backup existing files:
```bash
mv hardhat.config.js hardhat.config.js.backup
mv package.json package-hardhat.json
```

2. Use Truffle configuration:
```bash
cp package-truffle.json package.json
npm install
```

3. Update deployment scripts to use Truffle format
4. Update test files to use Truffle's contract artifacts

## Troubleshooting

### "Network not found" Error

Ensure the network is running:
- For local: Run `ganache-cli` or `hardhat node`
- For testnets: Check your RPC URL and private key

### "Account not found" Error

Verify your private key in `.env`:
- Should be without `0x` prefix
- Should have sufficient funds on the network

### Gas Issues

If transactions fail due to gas:
- Increase gas limit in truffle-config.js
- Adjust gas price based on network conditions

## Security Best Practices

1. **Never commit `.env` file** - It contains sensitive data
2. **Use hardware wallets** for mainnet deployments
3. **Verify contracts** on Etherscan/Polygonscan
4. **Test thoroughly** on testnets before mainnet deployment
5. **Use multi-sig wallets** for production contracts

## Additional Resources

- [Truffle Documentation](https://trufflesuite.com/docs/)
- [OpenZeppelin Contracts](https://docs.openzeppelin.com/contracts/)
- [Solidity Documentation](https://docs.soliditylang.org/)
- [Etherscan API](https://docs.etherscan.io/)
