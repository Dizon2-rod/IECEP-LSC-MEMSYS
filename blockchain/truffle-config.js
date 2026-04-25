require('dotenv').config();

const HDWalletProvider = require('@truffle/hdwallet-provider');

module.exports = {
  /**
   * Networks define how you connect to your ethereum client and let you set the
   * defaults web3 uses to send transactions.
   */
  networks: {
    // Development network - Hardhat Network
    development: {
      host: "127.0.0.1",
      port: 8545,
      network_id: "*",       // Match any network id
      gas: 6721975,
      gasPrice: 20000000000  // 20 gwei
    },

    // Local Ganache network
    ganache: {
      host: "127.0.0.1",
      port: 7545,
      network_id: "5777",
      gas: 6721975,
      gasPrice: 20000000000
    },

    // Sepolia Testnet
    sepolia: {
      provider: () => new HDWalletProvider(
        process.env.PRIVATE_KEY,
        process.env.SEPOLIA_RPC_URL || "https://sepolia.infura.io/v3/YOUR_INFURA_PROJECT_ID"
      ),
      network_id: 11155111,
      gas: 5500000,
      gasPrice: 20000000000, // 20 gwei
      confirmations: 2,
      timeoutBlocks: 200,
      skipDryRun: true
    },

    // Goerli Testnet
    goerli: {
      provider: () => new HDWalletProvider(
        process.env.PRIVATE_KEY,
        process.env.GOERLI_RPC_URL || "https://goerli.infura.io/v3/YOUR_INFURA_PROJECT_ID"
      ),
      network_id: 5,
      gas: 5500000,
      gasPrice: 20000000000,
      confirmations: 2,
      timeoutBlocks: 200,
      skipDryRun: true
    },

    // Ethereum Mainnet
    mainnet: {
      provider: () => new HDWalletProvider(
        process.env.PRIVATE_KEY,
        process.env.MAINNET_RPC_URL || "https://mainnet.infura.io/v3/YOUR_INFURA_PROJECT_ID"
      ),
      network_id: 1,
      gas: 5500000,
      gasPrice: 50000000000, // 50 gwei
      confirmations: 2,
      timeoutBlocks: 200,
      skipDryRun: true
    },

    // Polygon Mainnet
    polygon: {
      provider: () => new HDWalletProvider(
        process.env.PRIVATE_KEY,
        process.env.POLYGON_RPC_URL || "https://polygon-mainnet.infura.io/v3/YOUR_INFURA_PROJECT_ID"
      ),
      network_id: 137,
      gas: 5500000,
      gasPrice: 100000000000, // 100 gwei
      confirmations: 2,
      timeoutBlocks: 200,
      skipDryRun: true
    },

    // Polygon Mumbai Testnet
    mumbai: {
      provider: () => new HDWalletProvider(
        process.env.PRIVATE_KEY,
        "https://rpc-mumbai.maticvigil.com"
      ),
      network_id: 80001,
      gas: 5500000,
      gasPrice: 20000000000,
      confirmations: 2,
      timeoutBlocks: 200,
      skipDryRun: true
    }
  },

  /**
   * Configure your compilers
   */
  compilers: {
    solc: {
      version: "0.8.20",      // Fetch exact version from solc-bin
      settings: {
        optimizer: {
          enabled: true,
          runs: 200
        },
        evmVersion: "paris"
      }
    }
  },

  /**
   * Truffle DB configuration
   */
  db: {
    enabled: false
  },

  /**
   * Mocha testing framework configuration
   */
  mocha: {
    timeout: 100000,
    useColors: true,
    reporter: 'spec'
  },

  /**
   * Plugins
   */
  plugins: [
    'truffle-plugin-verify'
  ],

  /**
   * API keys for contract verification
   */
  api_keys: {
    etherscan: process.env.ETHERSCAN_API_KEY,
    polygonscan: process.env.POLYGONSCAN_API_KEY
  }
};
