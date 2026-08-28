# IECEP-LSC MEMSYS: Blockchain Explorer & Verification Demo Script
## Colloquium Presentation & Live System Demonstration Guide

---

### ⏱️ Presentation Snapshot
- **Target Duration:** 3 to 4 Minutes
- **Key Feature Highlight:** Enterprise Cryptographic Ledger, Asymmetric RSA-2048 Digital Signatures, Merkle Tree Batching, & Live Blockchain Explorer
- **Core Value Proposition:** Guaranteed non-repudiation and tamper-evidence for student digital credentials, institutional affiliation requirements, and financial audits across Laguna HEIs without gas fees.

---

### 🎬 Live Demo Flow & Script

#### 0:00 - 0:45 | The Hook & Problem Statement
> **Presenter:**
> *"Magandang araw po sa ating panel members. Isa sa mga pinakamalaking hamon sa mga student chapters at regional organizations ay ang **data integrity** — partikular ang pamemeke ng membership IDs, pagpapalit ng in-upload na affiliation documents nang walang audit trail, at invoice tampering sa financial collections.*
> 
> *Sa **IECEP-LSC MEMSYS**, hindi lang tayo basta nagtatago ng datos sa ordinaryong database. Bawat transaksyon, membership record, uploaded document, at official receipt ay **naka-anchor sa isang Cryptographic Blockchain Audit Trail** na protektado ng **SHA-256 hash chaining** at **RSA-2048 Asymmetric Digital Signatures**."*

---

#### 0:45 - 1:45 | Live Demo Act 1: The Interactive Blockchain Explorer
> *(Action: I-navigate ang browser sa **Resources ➡️ Blockchain Explorer** sa `http://localhost/IECEP-LSC-MEMSYS/public/blockchain-explorer.php`)*
>
> **Presenter:**
> *"Narito po ang ating **IECEP-LSC Blockchain Explorer Dashboard**. Katulad ng mga decentralized block explorers tulad ng Etherscan, binibigyan nito ang chapter officers, school deans, at auditors ng **100% real-time transparency**.*
> 
> *Makikita ninyo rito sa itaas ang ating **Live Block Height**, ang **100% Chain Integrity Status**, at ang ating **RSA-2048 Digital Signature Engine**.*
> 
> *Gamit ang filter tabs, maaari nating i-isolate ang iba't ibang multi-chain records: mga **Affiliation Kits**, **6 Required Document Hashes**, **Student Member Roster Batches**, at **Financial Payment Receipts**."*

---

#### 1:45 - 2:45 | Live Demo Act 2: Inspecting a Block & Cryptographic Proof
> *(Action: Mag-click sa **"Inspect"** button sa pinakahuling block)*
>
> **Presenter:**
> *"Kapag binuksan natin ang isang partikular na block, makikita natin ang cryptographic anatomy nito:*
> 1. *Ang **Cryptographic Hash (SHA-256)** — binuo mula sa deterministic payload.*
> 2. *Ang **Previous Block Hash** — ang cryptographic link na nag-uugnay nito sa nakaraang block.*
> 3. *Ang **RSA Digital Signature** na pinirmahan ng Chapter Private Key.*
> 
> *(Action: I-click ang **"Download Verifiable Cryptographic Proof (.json)"**)*
> 
> *Pwedeng mag-download ang sinumang evaluator o employer ng isang standalone **W3C-compliant Verifiable Proof Certificate (.json)**. Kahit offline ang user o mawalan ng internet, mapapatunayan gamit ang public key na lehitimo at hindi minanipula ang dokumento."*

---

#### 2:45 - 3:30 | Live Demo Act 3: Anti-Tampering & Security Guarantee
> *(Action: Ipakita ang `/public/verify-hash.php` o `/public/verify-member.php`)*
>
> **Presenter:**
> *"Kung sakaling may magtangka na pumasok sa database at baguhin kahit isang titik sa student name o receipt amount, **masisira ang buong hash sequence** at mare-reject ang digital signature dahil wala sa attacker ang chapter private key.*
> 
> *Sa pamamagitan nito, nakamit ng IECEP-LSC ang **bank-grade security at indisputable auditability** para sa lahat ng engineering schools sa Laguna nang walang binabayarang gas fees.*
> 
> *Maraming salamat po, at bukas po kami sa inyong mga katanungan."*

---

### 💡 Anticipated Panel Questions & Sample Answers

#### Q1: "Bakit hindi kayo gumamit ng public blockchain tulad ng Ethereum o Polygon?"
> **Answer:**
> *"Magandang tanong po. Ang mga public cryptocurrency blockchains tulad ng Ethereum ay may **gas fees (transaction costs)** sa bawat block write at mas mabagal ang confirmation time. Para sa isang student academic chapter, ang private hash-chained ledger na may RSA-2048 asymmetric signatures ay nagbibigay ng **parehong cryptographic immutability at non-repudiation guarantees** nang **zero transaction cost, sub-second latency, at 100% data privacy compliance (Data Privacy Act of 2012)**."*

#### Q2: "Paano kung ma-corrupt ang server? Nasaan ang Private Key?"
> **Answer:**
> *"Ang Chapter Private Key ay naka-store nang hiwalay sa secured directory na may restricted server permissions (`storage/keys/`). Bawat block proof na na-export ay may public key certificate na maaaring i-verify ng kahit anong external standard OpenSSL validator."*

#### Q3: "Paano pinagsasama ang maramihang dokumento ng affiliation?"
> **Answer:**
> *"Gumagamit tayo ng **Binary Merkle Tree (`MerkleTree.php`)**. Ang hashes ng 6 required files (LOI, Endorsement, CBL, Org Chart, CVs, Directory) ay pinagpapares-pares hanggang makabuo ng nag-iisang **Merkle Root Hash**. Ito ang nagbibigay-daan sa $O(\log N)$ batch verification."*
