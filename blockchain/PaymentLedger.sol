// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/Counters.sol";

contract PaymentLedger is Ownable, ReentrancyGuard {
    using Counters for Counters.Counter;
    
    Counters.Counter private _paymentIds;
    Counters.Counter private _auditIds;
    
    struct Payment {
        uint256 id;
        address payer;
        uint256 amount;
        string receiptId;
        uint256 timestamp;
        bool verified;
        string paymentType;
        string membershipType;
    }
    
    struct AuditLog {
        uint256 id;
        address executor;
        string action;
        uint256 timestamp;
        bytes32 dataHash;
    }
    
    mapping(string => Payment) public payments;
    mapping(address => bool) public authorizedExecutors;
    mapping(address => uint256[]) public userPayments;
    
    string[] public allReceiptIds;
    bytes32[] public auditTrail;
    
    event PaymentLogged(
        uint256 indexed paymentId,
        address indexed payer,
        uint256 amount,
        string receiptId,
        string paymentType,
        string membershipType,
        uint256 timestamp
    );
    
    event PaymentVerified(
        string indexed receiptId,
        address indexed verifier,
        uint256 timestamp
    );
    
    event PaymentUpdated(
        string indexed receiptId,
        string newPaymentType,
        string newMembershipType,
        uint256 timestamp
    );
    
    event ExecutorAdded(address indexed executor, address indexed addedBy);
    event ExecutorRemoved(address indexed executor, address indexed removedBy);
    
    event AuditEntry(
        uint256 indexed auditId,
        address indexed executor,
        string action,
        bytes32 dataHash,
        uint256 timestamp
    );
    
    modifier onlyAuthorized() {
        require(
            msg.sender == owner() || authorizedExecutors[msg.sender],
            "PaymentLedger: caller is not authorized"
        );
        _;
    }
    
    modifier receiptExists(string memory receiptId) {
        require(
            bytes(payments[receiptId].receiptId).length > 0,
            "PaymentLedger: receipt does not exist"
        );
        _;
    }
    
    modifier receiptNotExists(string memory receiptId) {
        require(
            bytes(payments[receiptId].receiptId).length == 0,
            "PaymentLedger: receipt already exists"
        );
        _;
    }
    
    constructor() Ownable(msg.sender) {
        _addAuditEntry("CONTRACT_DEPLOYED", keccak256(abi.encodePacked(block.timestamp, msg.sender)));
    }
    
    function logPayment(
        string memory receiptId,
        uint256 amount,
        string memory paymentType,
        string memory membershipType
    ) external onlyAuthorized nonReentrant receiptNotExists(receiptId) {
        require(amount > 0, "PaymentLedger: amount must be greater than 0");
        require(bytes(receiptId).length > 0, "PaymentLedger: receiptId cannot be empty");
        
        _paymentIds.increment();
        uint256 paymentId = _paymentIds.current();
        
        Payment memory newPayment = Payment({
            id: paymentId,
            payer: msg.sender,
            amount: amount,
            receiptId: receiptId,
            timestamp: block.timestamp,
            verified: false,
            paymentType: paymentType,
            membershipType: membershipType
        });
        
        payments[receiptId] = newPayment;
        allReceiptIds.push(receiptId);
        userPayments[msg.sender].push(paymentId);
        
        bytes32 dataHash = keccak256(abi.encodePacked(receiptId, amount, paymentType, membershipType));
        _addAuditEntry("PAYMENT_LOGGED", dataHash);
        
        emit PaymentLogged(
            paymentId,
            msg.sender,
            amount,
            receiptId,
            paymentType,
            membershipType,
            block.timestamp
        );
    }
    
    function verifyPayment(string memory receiptId) 
        external 
        onlyAuthorized 
        receiptExists(receiptId) 
    {
        require(!payments[receiptId].verified, "PaymentLedger: payment already verified");
        
        payments[receiptId].verified = true;
        
        bytes32 dataHash = keccak256(abi.encodePacked(receiptId, "VERIFIED"));
        _addAuditEntry("PAYMENT_VERIFIED", dataHash);
        
        emit PaymentVerified(receiptId, msg.sender, block.timestamp);
    }
    
    function updatePayment(
        string memory receiptId,
        string memory newPaymentType,
        string memory newMembershipType
    ) external onlyAuthorized receiptExists(receiptId) {
        payments[receiptId].paymentType = newPaymentType;
        payments[receiptId].membershipType = newMembershipType;
        
        bytes32 dataHash = keccak256(abi.encodePacked(receiptId, newPaymentType, newMembershipType));
        _addAuditEntry("PAYMENT_UPDATED", dataHash);
        
        emit PaymentUpdated(receiptId, newPaymentType, newMembershipType, block.timestamp);
    }
    
    function addExecutor(address executor) external onlyOwner {
        require(executor != address(0), "PaymentLedger: executor cannot be zero address");
        require(!authorizedExecutors[executor], "PaymentLedger: executor already authorized");
        
        authorizedExecutors[executor] = true;
        bytes32 dataHash = keccak256(abi.encodePacked(executor, "AUTHORIZED"));
        _addAuditEntry("EXECUTOR_ADDED", dataHash);
        
        emit ExecutorAdded(executor, msg.sender);
    }
    
    function removeExecutor(address executor) external onlyOwner {
        require(authorizedExecutors[executor], "PaymentLedger: executor not authorized");
        
        authorizedExecutors[executor] = false;
        bytes32 dataHash = keccak256(abi.encodePacked(executor, "UNAUTHORIZED"));
        _addAuditEntry("EXECUTOR_REMOVED", dataHash);
        
        emit ExecutorRemoved(executor, msg.sender);
    }
    
    function getPayment(string memory receiptId) 
        external 
        view 
        returns (Payment memory) 
    {
        return payments[receiptId];
    }
    
    function getUserPayments(address user) external view returns (uint256[] memory) {
        return userPayments[user];
    }
    
    function getAllReceiptIds() external view returns (string[] memory) {
        return allReceiptIds;
    }
    
    function getPaymentCount() external view returns (uint256) {
        return _paymentIds.current();
    }
    
    function getAuditTrail() external view returns (bytes32[] memory) {
        return auditTrail;
    }
    
    function isAuthorized(address executor) external view returns (bool) {
        return authorizedExecutors[executor];
    }
    
    function _addAuditEntry(string memory action, bytes32 dataHash) internal {
        _auditIds.increment();
        uint256 auditId = _auditIds.current();
        
        auditTrail.push(dataHash);
        
        emit AuditEntry(auditId, msg.sender, action, dataHash, block.timestamp);
    }
    
    function emergencyPause() external onlyOwner {
        // Implementation for emergency pause if needed
        _addAuditEntry("EMERGENCY_PAUSE", keccak256(abi.encodePacked("PAUSED", block.timestamp)));
    }
    
    function emergencyUnpause() external onlyOwner {
        // Implementation for emergency unpause if needed
        _addAuditEntry("EMERGENCY_UNPAUSE", keccak256(abi.encodePacked("UNPAUSED", block.timestamp)));
    }
}
