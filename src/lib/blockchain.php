<?php
namespace App\Lib;

use Web3\Web3;
use Web3\Contract;
use Web3\Utils;
use Web3\Eth;
use Web3\Personal;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use kornrunner\Keccak;
use Elliptic\EC;

class BlockchainService
{
    private array $config;
    private string $contractAbi;
    private Web3 $web3;
    private Contract $contract;
    private EC $ec;

    public function __construct()
    {
        $this->config = include __DIR__ . '/../config/config.php';
        $this->ec = new EC('secp256k1');
        
        $this->contractAbi = json_encode([
            [
                'type' => 'constructor',
                'inputs' => [],
                'stateMutability' => 'nonpayable'
            ],
            [
                'type' => 'function',
                'name' => 'logPayment',
                'inputs' => [
                    ['type' => 'string', 'name' => 'receiptId'],
                    ['type' => 'uint256', 'name' => 'amount'],
                    ['type' => 'string', 'name' => 'paymentType'],
                    ['type' => 'string', 'name' => 'membershipType'],
                ],
                'outputs' => [],
                'stateMutability' => 'nonpayable',
            ],
            [
                'type' => 'function',
                'name' => 'verifyPayment',
                'inputs' => [
                    ['type' => 'string', 'name' => 'receiptId'],
                ],
                'outputs' => [],
                'stateMutability' => 'nonpayable',
            ],
            [
                'type' => 'function',
                'name' => 'updatePayment',
                'inputs' => [
                    ['type' => 'string', 'name' => 'receiptId'],
                    ['type' => 'string', 'name' => 'newPaymentType'],
                    ['type' => 'string', 'name' => 'newMembershipType'],
                ],
                'outputs' => [],
                'stateMutability' => 'nonpayable',
            ],
            [
                'type' => 'function',
                'name' => 'addExecutor',
                'inputs' => [
                    ['type' => 'address', 'name' => 'executor'],
                ],
                'outputs' => [],
                'stateMutability' => 'nonpayable',
            ],
            [
                'type' => 'function',
                'name' => 'removeExecutor',
                'inputs' => [
                    ['type' => 'address', 'name' => 'executor'],
                ],
                'outputs' => [],
                'stateMutability' => 'nonpayable',
            ],
            [
                'type' => 'function',
                'name' => 'getPayment',
                'inputs' => [
                    ['type' => 'string', 'name' => 'receiptId'],
                ],
                'outputs' => [
                    ['type' => 'uint256', 'name' => 'id'],
                    ['type' => 'address', 'name' => 'payer'],
                    ['type' => 'uint256', 'name' => 'amount'],
                    ['type' => 'string', 'name' => 'receiptId'],
                    ['type' => 'uint256', 'name' => 'timestamp'],
                    ['type' => 'bool', 'name' => 'verified'],
                    ['type' => 'string', 'name' => 'paymentType'],
                    ['type' => 'string', 'name' => 'membershipType'],
                ],
                'stateMutability' => 'view',
            ],
            [
                'type' => 'function',
                'name' => 'getUserPayments',
                'inputs' => [
                    ['type' => 'address', 'name' => 'user'],
                ],
                'outputs' => [
                    ['type' => 'uint256[]', 'name' => ''],
                ],
                'stateMutability' => 'view',
            ],
            [
                'type' => 'function',
                'name' => 'getAllReceiptIds',
                'inputs' => [],
                'outputs' => [
                    ['type' => 'string[]', 'name' => ''],
                ],
                'stateMutability' => 'view',
            ],
            [
                'type' => 'function',
                'name' => 'getPaymentCount',
                'inputs' => [],
                'outputs' => [
                    ['type' => 'uint256', 'name' => ''],
                ],
                'stateMutability' => 'view',
            ],
            [
                'type' => 'function',
                'name' => 'isAuthorized',
                'inputs' => [
                    ['type' => 'address', 'name' => 'executor'],
                ],
                'outputs' => [
                    ['type' => 'bool', 'name' => ''],
                ],
                'stateMutability' => 'view',
            ],
            [
                'type' => 'event',
                'name' => 'PaymentLogged',
                'inputs' => [
                    ['type' => 'uint256', 'name' => 'paymentId', 'indexed' => true],
                    ['type' => 'address', 'name' => 'payer', 'indexed' => true],
                    ['type' => 'uint256', 'name' => 'amount', 'indexed' => false],
                    ['type' => 'string', 'name' => 'receiptId', 'indexed' => false],
                    ['type' => 'string', 'name' => 'paymentType', 'indexed' => false],
                    ['type' => 'string', 'name' => 'membershipType', 'indexed' => false],
                    ['type' => 'uint256', 'name' => 'timestamp', 'indexed' => false],
                ],
            ],
            [
                'type' => 'event',
                'name' => 'PaymentVerified',
                'inputs' => [
                    ['type' => 'string', 'name' => 'receiptId', 'indexed' => true],
                    ['type' => 'address', 'name' => 'verifier', 'indexed' => true],
                    ['type' => 'uint256', 'name' => 'timestamp', 'indexed' => false],
                ],
            ],
            [
                'type' => 'event',
                'name' => 'PaymentUpdated',
                'inputs' => [
                    ['type' => 'string', 'name' => 'receiptId', 'indexed' => true],
                    ['type' => 'string', 'name' => 'newPaymentType', 'indexed' => false],
                    ['type' => 'string', 'name' => 'newMembershipType', 'indexed' => false],
                    ['type' => 'uint256', 'name' => 'timestamp', 'indexed' => false],
                ],
            ],
        ]);
        
        $this->initializeWeb3();
    }
    
    private function initializeWeb3(): void
    {
        $rpcUrl = $this->config['blockchain_rpc_url'] ?? '';
        if (empty($rpcUrl)) {
            throw new \Exception('Blockchain RPC URL not configured');
        }
        
        $requestManager = new HttpRequestManager($rpcUrl, 10);
        $provider = new HttpProvider($requestManager);
        $this->web3 = new Web3($provider);
        
        $contractAddress = $this->config['blockchain_contract_address'] ?? '';
        if (!empty($contractAddress)) {
            $this->contract = new Contract($this->web3->provider, $this->contractAbi);
            $this->contract->at($contractAddress);
        }
    }

    public function logPayment(string $receiptId, int $amountInCents, string $paymentType = 'MEMBERSHIP', string $membershipType = 'REGULAR'): array
    {
        try {
            $this->validateBlockchainConfig();
            
            $privateKey = $this->normalizePrivateKey($this->config['blockchain_private_key']);
            $senderAddress = $this->getAddressFromPrivateKey($privateKey);
            
            $contract = $this->getContract();
            
            $functionData = $contract->getData('logPayment', $receiptId, $amountInCents, $paymentType, $membershipType);
            
            $transaction = $this->buildTransaction($senderAddress, $functionData);
            
            $signedTransaction = $this->signTransaction($transaction, $privateKey);
            
            $txHash = $this->sendRawTransaction($signedTransaction);
            
            if ($txHash) {
                $receipt = $this->waitForConfirmation($txHash);
                return [
                    'error' => false,
                    'tx_hash' => $txHash,
                    'block_number' => $receipt['blockNumber'] ?? null,
                    'payment_id' => $receipt['paymentId'] ?? null,
                ];
            }
            
            return ['error' => true, 'message' => 'Failed to send transaction'];
        } catch (\Exception $e) {
            error_log("Blockchain error: " . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function verifyPayment(string $receiptId): array
    {
        try {
            $this->validateBlockchainConfig();
            
            $privateKey = $this->normalizePrivateKey($this->config['blockchain_private_key']);
            $senderAddress = $this->getAddressFromPrivateKey($privateKey);
            
            $contract = $this->getContract();
            
            $functionData = $contract->getData('verifyPayment', $receiptId);
            
            $transaction = $this->buildTransaction($senderAddress, $functionData);
            
            $signedTransaction = $this->signTransaction($transaction, $privateKey);
            
            $txHash = $this->sendRawTransaction($signedTransaction);
            
            if ($txHash) {
                $receipt = $this->waitForConfirmation($txHash);
                return [
                    'error' => false,
                    'tx_hash' => $txHash,
                    'block_number' => $receipt['blockNumber'] ?? null,
                    'verified' => true
                ];
            }
            
            return ['error' => true, 'message' => 'Failed to verify payment'];
        } catch (\Exception $e) {
            error_log("Blockchain verify error: " . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
    
    public function updatePayment(string $receiptId, string $newPaymentType, string $newMembershipType): array
    {
        try {
            $this->validateBlockchainConfig();
            
            $privateKey = $this->normalizePrivateKey($this->config['blockchain_private_key']);
            $senderAddress = $this->getAddressFromPrivateKey($privateKey);
            
            $contract = $this->getContract();
            
            $functionData = $contract->getData('updatePayment', $receiptId, $newPaymentType, $newMembershipType);
            
            $transaction = $this->buildTransaction($senderAddress, $functionData);
            
            $signedTransaction = $this->signTransaction($transaction, $privateKey);
            
            $txHash = $this->sendRawTransaction($signedTransaction);
            
            if ($txHash) {
                $receipt = $this->waitForConfirmation($txHash);
                return [
                    'error' => false,
                    'tx_hash' => $txHash,
                    'block_number' => $receipt['blockNumber'] ?? null,
                    'updated' => true
                ];
            }
            
            return ['error' => true, 'message' => 'Failed to update payment'];
        } catch (\Exception $e) {
            error_log("Blockchain update error: " . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
    
    public function getPayment(string $receiptId): array
    {
        try {
            $contract = $this->getContract();
            
            $result = null;
            $contract->call('getPayment', $receiptId, function ($err, $res) use (&$result) {
                if ($err === null) {
                    $result = $res;
                }
            });
            
            if ($result) {
                return [
                    'error' => false,
                    'payment' => [
                        'id' => $result[0]->toString(),
                        'payer' => $result[1],
                        'amount' => $result[2]->toString(),
                        'receiptId' => $result[3],
                        'timestamp' => $result[4]->toString(),
                        'verified' => $result[5],
                        'paymentType' => $result[6],
                        'membershipType' => $result[7]
                    ]
                ];
            }
            
            return ['error' => true, 'message' => 'Payment not found'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
    
    public function getUserPayments(string $address): array
    {
        try {
            $contract = $this->getContract();
            
            $result = null;
            $contract->call('getUserPayments', $address, function ($err, $res) use (&$result) {
                if ($err === null) {
                    $result = $res;
                }
            });
            
            if ($result) {
                $paymentIds = [];
                foreach ($result[0] as $id) {
                    $paymentIds[] = $id->toString();
                }
                return ['error' => false, 'payment_ids' => $paymentIds];
            }
            
            return ['error' => true, 'message' => 'No payments found'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
    
    public function addExecutor(string $executorAddress): array
    {
        try {
            $this->validateBlockchainConfig();
            
            $privateKey = $this->normalizePrivateKey($this->config['blockchain_private_key']);
            $senderAddress = $this->getAddressFromPrivateKey($privateKey);
            
            $contract = $this->getContract();
            
            $functionData = $contract->getData('addExecutor', $executorAddress);
            
            $transaction = $this->buildTransaction($senderAddress, $functionData);
            
            $signedTransaction = $this->signTransaction($transaction, $privateKey);
            
            $txHash = $this->sendRawTransaction($signedTransaction);
            
            if ($txHash) {
                $receipt = $this->waitForConfirmation($txHash);
                return [
                    'error' => false,
                    'tx_hash' => $txHash,
                    'block_number' => $receipt['blockNumber'] ?? null,
                    'executor_added' => true
                ];
            }
            
            return ['error' => true, 'message' => 'Failed to add executor'];
        } catch (\Exception $e) {
            error_log("Blockchain add executor error: " . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function validateBlockchainConfig(): void
    {
        $required = ['blockchain_rpc_url', 'blockchain_private_key', 'blockchain_contract_address'];
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                throw new \Exception("Blockchain configuration missing: $key");
            }
        }
    }
    
    private function normalizePrivateKey(string $privateKey): string
    {
        return str_starts_with($privateKey, '0x') ? $privateKey : '0x' . $privateKey;
    }
    
    private function getContract(): Contract
    {
        if (!isset($this->contract)) {
            throw new \Exception('Contract not initialized');
        }
        return $this->contract;
    }
    
    private function buildTransaction(string $from, string $data): array
    {
        $nonce = $this->getNonce($from);
        $gasPrice = $this->getGasPrice();
        $gasLimit = $this->estimateGas($from, $data);
        
        return [
            'from' => $from,
            'to' => $this->config['blockchain_contract_address'],
            'gas' => '0x' . dechex($gasLimit),
            'gasPrice' => $gasPrice,
            'nonce' => $nonce,
            'data' => $data,
            'chainId' => $this->config['blockchain_chain_id'] ?? 1,
        ];
    }
    
    private function getNonce(string $address): string
    {
        $nonce = '0x0';
        $this->web3->eth->getTransactionCount($address, 'pending', function ($err, $count) use (&$nonce) {
            if ($err === null) {
                $nonce = Utils::toHex($count->toString(), true);
            }
        });
        return $nonce;
    }
    
    private function getGasPrice(): string
    {
        $gasPrice = '0x3b9aca00'; // 1 gwei default
        $this->web3->eth->gasPrice(function ($err, $price) use (&$gasPrice) {
            if ($err === null) {
                $gasPrice = Utils::toHex($price->toString(), true);
            }
        });
        return $gasPrice;
    }
    
    private function estimateGas(string $from, string $data): int
    {
        $gasLimit = 200000; // default
        $this->web3->eth->estimateGas([
            'from' => $from,
            'to' => $this->config['blockchain_contract_address'],
            'data' => $data
        ], function ($err, $gas) use (&$gasLimit) {
            if ($err === null) {
                $gasLimit = (int)$gas->toString();
            }
        });
        return $gasLimit;
    }
    
    private function signTransaction(array $transaction, string $privateKey): string
    {
        $eth = new Eth($this->web3->provider);
        
        $signedTransaction = null;
        $eth->signTransaction($transaction, $privateKey, function ($err, $signedTx) use (&$signedTransaction) {
            if ($err === null) {
                $signedTransaction = $signedTx;
            }
        });
        
        if ($signedTransaction === null) {
            throw new \Exception('Failed to sign transaction');
        }
        
        return $signedTransaction;
    }
    
    private function sendRawTransaction(string $signedTransaction): ?string
    {
        $txHash = null;
        $this->web3->eth->sendRawTransaction($signedTransaction, function ($err, $hash) use (&$txHash) {
            if ($err === null) {
                $txHash = $hash;
            } else {
                error_log("Send raw transaction error: " . $err->getMessage());
            }
        });
        return $txHash;
    }
    
    private function getAddressFromPrivateKey(string $privateKey): string
    {
        $key = str_replace('0x', '', $privateKey);
        $keyPair = $this->ec->keyFromPrivate($key);
        $publicKey = $keyPair->getPublic(false, 'hex');
        $publicKeyHash = Keccak::hash(hex2bin(substr($publicKey, 2)), 256);
        return '0x' . substr($publicKeyHash, -40);
    }
    
    private function waitForConfirmation(string $txHash, int $maxAttempts = 30): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(2);
            $receipt = null;
            $this->web3->eth->getTransactionReceipt($txHash, function ($err, $r) use (&$receipt) {
                if ($err === null && $r !== null) {
                    $receipt = [
                        'blockNumber' => $r->blockNumber ? hexdec($r->blockNumber->toString()) : null,
                        'status' => $r->status ? hexdec($r->status->toString()) : null,
                        'gasUsed' => $r->gasUsed ? hexdec($r->gasUsed->toString()) : null,
                    ];
                }
            });
            if ($receipt !== null) {
                return $receipt;
            }
        }
        return ['blockNumber' => null, 'status' => null, 'gasUsed' => null];
    }
    
    public function verifyTransaction(string $txHash): array
    {
        try {
            $receipt = null;
            $this->web3->eth->getTransactionReceipt($txHash, function ($err, $r) use (&$receipt) {
                if ($err === null) {
                    $receipt = $r;
                }
            });

            if ($receipt === null) {
                return ['error' => true, 'message' => 'Transaction not found'];
            }

            $tx = null;
            $this->web3->eth->getTransactionByHash($txHash, function ($err, $t) use (&$tx) {
                if ($err === null) {
                    $tx = $t;
                }
            });

            return [
                'error' => false,
                'verified' => true,
                'tx_hash' => $txHash,
                'block_number' => $receipt->blockNumber ? '0x' . dechex($receipt->blockNumber->toString()) : null,
                'from' => $tx ? $tx->from : null,
                'status' => $receipt->status ? (hexdec($receipt->status->toString()) === 1 ? 'success' : 'failed') : 'unknown',
                'gas_used' => $receipt->gasUsed ? hexdec($receipt->gasUsed->toString()) : null,
                'etherscan_url' => "https://sepolia.etherscan.io/tx/$txHash",
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
    
    public function getContractAddress(): string
    {
        return $this->config['blockchain_contract_address'] ?? '';
    }
    
    public function isConfigured(): bool
    {
        $required = ['blockchain_rpc_url', 'blockchain_private_key', 'blockchain_contract_address'];
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }
        return true;
    }
}
