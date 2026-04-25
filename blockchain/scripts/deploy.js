const { ethers } = require("hardhat");

async function main() {
  console.log("Deploying PaymentLedger contract...");

  const [deployer] = await ethers.getSigners();
  console.log("Deploying contracts with the account:", deployer.address);
  
  const balance = await deployer.getBalance();
  console.log("Account balance:", ethers.utils.formatEther(balance));

  const PaymentLedger = await ethers.getContractFactory("PaymentLedger");
  const paymentLedger = await PaymentLedger.deploy();

  await paymentLedger.deployed();

  console.log("PaymentLedger deployed to:", paymentLedger.address);
  console.log("Transaction hash:", paymentLedger.deployTransaction.hash);
  console.log("Gas used:", paymentLedger.deployTransaction.gasLimit.toString());
  
  // Save deployment info
  const fs = require('fs');
  const deploymentInfo = {
    contractAddress: paymentLedger.address,
    deployerAddress: deployer.address,
    network: network.name,
    chainId: network.config.chainId,
    transactionHash: paymentLedger.deployTransaction.hash,
    gasUsed: paymentLedger.deployTransaction.gasLimit.toString(),
    deployedAt: new Date().toISOString()
  };
  
  const deploymentPath = `./blockchain/deployments/${network.name}.json`;
  fs.mkdirSync('./blockchain/deployments', { recursive: true });
  fs.writeFileSync(deploymentPath, JSON.stringify(deploymentInfo, null, 2));
  
  console.log(`Deployment info saved to: ${deploymentPath}`);
  
  // Verify contract on Etherscan (if not localhost)
  if (network.name !== "localhost" && network.name !== "hardhat") {
    console.log("Waiting for block confirmations...");
    await paymentLedger.deployTransaction.wait(5);
    
    console.log("Verifying contract on Etherscan...");
    try {
      await hre.run("verify:verify", {
        address: paymentLedger.address,
        constructorArguments: [],
      });
      console.log("Contract verified successfully!");
    } catch (error) {
      console.log("Contract verification failed:", error.message);
    }
  }
}

main()
  .then(() => process.exit(0))
  .catch((error) => {
    console.error(error);
    process.exit(1);
  });
