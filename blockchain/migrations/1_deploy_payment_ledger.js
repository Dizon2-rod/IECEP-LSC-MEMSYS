const PaymentLedger = artifacts.require("PaymentLedger");

module.exports = function(deployer, network, accounts) {
  // Deployment options
  const deployOptions = {
    from: accounts[0],  // Deploy from first account
    gas: 5500000,       // Gas limit
    gasPrice: 20000000000 // 20 gwei
  };

  // Log deployment info
  console.log(`Deploying PaymentLedger to network: ${network}`);
  console.log(`Deployer account: ${accounts[0]}`);

  // Deploy the contract
  deployer.deploy(PaymentLedger, deployOptions);

  // Additional deployment logic if needed
  if (network !== 'development' && network !== 'ganache') {
    console.log('Deploying to production/testnet network');
  }
};
