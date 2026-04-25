const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("PaymentLedger", function () {
  let paymentLedger;
  let owner;
  let executor;
  let user1;
  let user2;

  beforeEach(async function () {
    [owner, executor, user1, user2] = await ethers.getSigners();
    
    const PaymentLedger = await ethers.getContractFactory("PaymentLedger");
    paymentLedger = await PaymentLedger.deploy();
    await paymentLedger.deployed();
  });

  describe("Deployment", function () {
    it("Should set the right owner", async function () {
      expect(await paymentLedger.owner()).to.equal(owner.address);
    });

    it("Should initialize with zero payments", async function () {
      expect(await paymentLedger.getPaymentCount()).to.equal(0);
    });
  });

  describe("Executor Management", function () {
    it("Should allow owner to add executor", async function () {
      await paymentLedger.addExecutor(executor.address);
      expect(await paymentLedger.isAuthorized(executor.address)).to.be.true;
    });

    it("Should allow owner to remove executor", async function () {
      await paymentLedger.addExecutor(executor.address);
      await paymentLedger.removeExecutor(executor.address);
      expect(await paymentLedger.isAuthorized(executor.address)).to.be.false;
    });

    it("Should not allow non-owner to add executor", async function () {
      await expect(
        paymentLedger.connect(user1).addExecutor(user2.address)
      ).to.be.revertedWith("Ownable: caller is not the owner");
    });
  });

  describe("Payment Logging", function () {
    beforeEach(async function () {
      await paymentLedger.addExecutor(executor.address);
    });

    it("Should allow authorized executor to log payment", async function () {
      const receiptId = "REC-001";
      const amount = ethers.utils.parseUnits("250.00", 2); // 25000 cents
      const paymentType = "MEMBERSHIP";
      const membershipType = "REGULAR";

      await expect(
        paymentLedger.connect(executor).logPayment(receiptId, amount, paymentType, membershipType)
      )
        .to.emit(paymentLedger, "PaymentLogged")
        .withArgs(
          1, // paymentId
          executor.address,
          amount,
          receiptId,
          paymentType,
          membershipType,
          any // timestamp
        );

      const payment = await paymentLedger.getPayment(receiptId);
      expect(payment.receiptId).to.equal(receiptId);
      expect(payment.amount).to.equal(amount);
      expect(payment.paymentType).to.equal(paymentType);
      expect(payment.membershipType).to.equal(membershipType);
      expect(payment.verified).to.be.false;
    });

    it("Should not allow unauthorized user to log payment", async function () {
      await expect(
        paymentLedger.connect(user1).logPayment("REC-002", 1000, "TEST", "BASIC")
      ).to.be.revertedWith("PaymentLedger: caller is not authorized");
    });

    it("Should not allow duplicate receipt IDs", async function () {
      const receiptId = "REC-003";
      await paymentLedger.connect(executor).logPayment(receiptId, 1000, "TEST", "BASIC");
      
      await expect(
        paymentLedger.connect(executor).logPayment(receiptId, 2000, "TEST", "PREMIUM")
      ).to.be.revertedWith("PaymentLedger: receipt already exists");
    });

    it("Should validate amount is greater than zero", async function () {
      await expect(
        paymentLedger.connect(executor).logPayment("REC-004", 0, "TEST", "BASIC")
      ).to.be.revertedWith("PaymentLedger: amount must be greater than 0");
    });
  });

  describe("Payment Verification", function () {
    beforeEach(async function () {
      await paymentLedger.addExecutor(executor.address);
      await paymentLedger.connect(executor).logPayment("REC-005", 1000, "TEST", "BASIC");
    });

    it("Should allow authorized user to verify payment", async function () {
      await expect(
        paymentLedger.connect(executor).verifyPayment("REC-005")
      )
        .to.emit(paymentLedger, "PaymentVerified")
        .withArgs("REC-005", executor.address, any);

      const payment = await paymentLedger.getPayment("REC-005");
      expect(payment.verified).to.be.true;
    });

    it("Should not allow verification of non-existent payment", async function () {
      await expect(
        paymentLedger.connect(executor).verifyPayment("REC-999")
      ).to.be.revertedWith("PaymentLedger: receipt does not exist");
    });

    it("Should not allow double verification", async function () {
      await paymentLedger.connect(executor).verifyPayment("REC-005");
      await expect(
        paymentLedger.connect(executor).verifyPayment("REC-005")
      ).to.be.revertedWith("PaymentLedger: payment already verified");
    });
  });

  describe("Payment Updates", function () {
    beforeEach(async function () {
      await paymentLedger.addExecutor(executor.address);
      await paymentLedger.connect(executor).logPayment("REC-006", 1000, "OLD_TYPE", "OLD_MEMBERSHIP");
    });

    it("Should allow authorized user to update payment", async function () {
      await expect(
        paymentLedger.connect(executor).updatePayment("REC-006", "NEW_TYPE", "NEW_MEMBERSHIP")
      )
        .to.emit(paymentLedger, "PaymentUpdated")
        .withArgs("REC-006", "NEW_TYPE", "NEW_MEMBERSHIP", any);

      const payment = await paymentLedger.getPayment("REC-006");
      expect(payment.paymentType).to.equal("NEW_TYPE");
      expect(payment.membershipType).to.equal("NEW_MEMBERSHIP");
    });

    it("Should not allow update of non-existent payment", async function () {
      await expect(
        paymentLedger.connect(executor).updatePayment("REC-999", "NEW_TYPE", "NEW_MEMBERSHIP")
      ).to.be.revertedWith("PaymentLedger: receipt does not exist");
    });
  });

  describe("Query Functions", function () {
    beforeEach(async function () {
      await paymentLedger.addExecutor(executor.address);
      
      // Log multiple payments
      await paymentLedger.connect(executor).logPayment("REC-007", 1000, "TYPE1", "MEM1");
      await paymentLedger.connect(executor).logPayment("REC-008", 2000, "TYPE2", "MEM2");
      await paymentLedger.connect(executor).logPayment("REC-009", 3000, "TYPE3", "MEM3");
    });

    it("Should return all receipt IDs", async function () {
      const receiptIds = await paymentLedger.getAllReceiptIds();
      expect(receiptIds).to.include("REC-007");
      expect(receiptIds).to.include("REC-008");
      expect(receiptIds).to.include("REC-009");
      expect(receiptIds.length).to.equal(3);
    });

    it("Should return correct payment count", async function () {
      expect(await paymentLedger.getPaymentCount()).to.equal(3);
    });

    it("Should return user payments", async function () {
      const userPayments = await paymentLedger.getUserPayments(executor.address);
      expect(userPayments.length).to.equal(3);
    });
  });

  describe("Audit Trail", function () {
    it("Should maintain audit trail for all actions", async function () {
      await paymentLedger.addExecutor(executor.address);
      await paymentLedger.connect(executor).logPayment("REC-010", 1000, "TEST", "BASIC");
      await paymentLedger.connect(executor).verifyPayment("REC-010");
      
      const auditTrail = await paymentLedger.getAuditTrail();
      expect(auditTrail.length).to.be.greaterThan(0);
    });
  });
});
