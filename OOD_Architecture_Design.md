# IECEP-LSC MEMSYS - Object-Oriented Design (OOD) Architecture

## Overview
This document outlines the complete Object-Oriented Design for the IECEP-LSC MEMSYS affiliation workflow system, following SOLID principles and design patterns.

---

## 🏗️ System Architecture

### **Layered Architecture**
```
┌─────────────────────────────────────────┐
│             Presentation Layer           │
│  (Controllers, Views, Forms, UI)         │
├─────────────────────────────────────────┤
│            Application Layer            │
│   (Use Cases, Services, Business Logic) │
├─────────────────────────────────────────┤
│              Domain Layer                │
│     (Entities, Value Objects, Repos)     │
├─────────────────────────────────────────┤
│           Infrastructure Layer           │
│  (Database, Email, File Storage, Cache)  │
└─────────────────────────────────────────┘
```

---

## 📦 Core Domain Models

### **1. User Entity**
```php
<?php
namespace App\Domain\Entities;

class User
{
    private UUID $id;
    private string $email;
    private string $password;
    private string $fullName;
    private UserRole $role;
    private bool $mustChangePassword;
    private bool $isActive;
    private ?UUID $schoolId;
    private DateTime $createdAt;
    private DateTime $updatedAt;
    
    public function __construct(
        string $email,
        string $password,
        string $fullName,
        UserRole $role,
        bool $mustChangePassword = false
    ) {
        $this->id = UUID::generate();
        $this->email = $email;
        $this->password = $this->hashPassword($password);
        $this->fullName = $fullName;
        $this->role = $role;
        $this->mustChangePassword = $mustChangePassword;
        $this->isActive = true;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }
    
    public function requiresPasswordChange(): bool
    {
        return $this->mustChangePassword;
    }
    
    public function changePassword(string $newPassword): void
    {
        $this->password = $this->hashPassword($newPassword);
        $this->mustChangePassword = false;
        $this->updatedAt = new DateTime();
    }
    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
    
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
```

### **2. UserRole Value Object**
```php
<?php
namespace App\Domain\ValueObjects;

enum UserRole: string
{
    case MEMBER = 'member';
    case SCHOOL_OFFICER = 'school_officer';
    case REGISTRATION_COMMITTEE = 'registration';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';
    
    public function canApproveAffiliations(): bool
    {
        return in_array($this, [self::REGISTRATION_COMMITTEE, self::ADMIN, self::SUPER_ADMIN]);
    }
    
    public function getDashboardPath(): string
    {
        return match($this) {
            self::SUPER_ADMIN => '/portal/super-admin/dashboard.php',
            self::ADMIN => '/portal/admin/dashboard.php',
            self::SCHOOL_OFFICER => '/portal/school-officer/dashboard.php',
            self::MEMBER => '/portal/member/dashboard.php',
            default => '/portal/member/dashboard.php'
        };
    }
}
```

### **3. AffiliationApplication Entity**
```php
<?php
namespace App\Domain\Entities;

class AffiliationApplication
{
    private UUID $id;
    private string $institutionName;
    private string $address;
    private string $contactPerson;
    private string $contactPosition;
    private string $contactPhone;
    private string $email;
    private ApplicationStatus $status;
    private ?string $committeeNotes;
    private ?DateTime $requestedAt;
    private ?DateTime $resubmittedAt;
    private ?DateTime $rejectedAt;
    private ?DateTime $approvedAt;
    private ?string $rejectionReason;
    private ?UUID $portalUserId;
    private ?string $editToken;
    private bool $loginCredentialsSent;
    private DocumentCollection $documents;
    private DateTime $submittedAt;
    private DateTime $createdAt;
    private DateTime $updatedAt;
    
    public function __construct(
        string $institutionName,
        string $address,
        string $contactPerson,
        string $contactPosition,
        string $contactPhone,
        string $email
    ) {
        $this->id = UUID::generate();
        $this->institutionName = $institutionName;
        $this->address = $address;
        $this->contactPerson = $contactPerson;
        $this->contactPosition = $contactPosition;
        $this->contactPhone = $contactPhone;
        $this->email = $email;
        $this->status = ApplicationStatus::PENDING;
        $this->documents = new DocumentCollection();
        $this->loginCredentialsSent = false;
        $this->submittedAt = new DateTime();
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }
    
    public function requestChanges(string $notes): string
    {
        $this->validateStatusForAction([ApplicationStatus::PENDING, ApplicationStatus::RESUBMITTED]);
        
        $this->status = ApplicationStatus::CHANGES_REQUESTED;
        $this->committeeNotes = $notes;
        $this->requestedAt = new DateTime();
        $this->editToken = $this->generateSecureToken();
        $this->updatedAt = new DateTime();
        
        return $this->editToken;
    }
    
    public function approve(UUID $portalUserId): void
    {
        $this->validateStatusForAction([ApplicationStatus::PENDING, ApplicationStatus::RESUBMITTED]);
        
        $this->status = ApplicationStatus::APPROVED;
        $this->approvedAt = new DateTime();
        $this->portalUserId = $portalUserId;
        $this->loginCredentialsSent = true;
        $this->editToken = null;
        $this->updatedAt = new DateTime();
    }
    
    public function reject(string $reason): void
    {
        $this->validateStatusForAction([ApplicationStatus::PENDING, ApplicationStatus::RESUBMITTED]);
        
        $this->status = ApplicationStatus::REJECTED;
        $this->rejectionReason = $reason;
        $this->rejectedAt = new DateTime();
        $this->editToken = null;
        $this->updatedAt = new DateTime();
    }
    
    public function resubmit(DocumentCollection $newDocuments): void
    {
        $this->validateStatusForAction([ApplicationStatus::CHANGES_REQUESTED]);
        
        $this->status = ApplicationStatus::RESUBMITTED;
        $this->documents = $newDocuments;
        $this->resubmittedAt = new DateTime();
        $this->editToken = null;
        $this->updatedAt = new DateTime();
    }
    
    private function validateStatusForAction(array $allowedStatuses): void
    {
        if (!in_array($this->status, $allowedStatuses)) {
            throw new InvalidApplicationStateException(
                "Cannot perform action on application with status: {$this->status->value}"
            );
        }
    }
    
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
```

### **4. ApplicationStatus Value Object**
```php
<?php
namespace App\Domain\ValueObjects;

enum ApplicationStatus: string
{
    case PENDING = 'pending';
    case CHANGES_REQUESTED = 'changes_requested';
    case RESUBMITTED = 'resubmitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    
    public function canBeEdited(): bool
    {
        return $this === self::CHANGES_REQUESTED;
    }
    
    public function canBeApproved(): bool
    {
        return in_array($this, [self::PENDING, self::RESUBMITTED]);
    }
    
    public function canBeRejected(): bool
    {
        return in_array($this, [self::PENDING, self::RESUBMITTED]);
    }
    
    public function getDisplayColor(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::CHANGES_REQUESTED => 'info',
            self::RESUBMITTED => 'success',
            self::APPROVED => 'success',
            self::REJECTED => 'danger'
        };
    }
}
```

### **5. Document Entity**
```php
<?php
namespace App\Domain\Entities;

class Document
{
    private string $filename;
    private string $mimeType;
    private int $size;
    private string $base64Content;
    private DateTime $uploadedAt;
    
    public function __construct(string $filename, string $mimeType, int $size, string $base64Content)
    {
        $this->validateDocument($filename, $mimeType, $size, $base64Content);
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->base64Content = $base64Content;
        $this->uploadedAt = new DateTime();
    }
    
    private function validateDocument(string $filename, string $mimeType, int $size, string $base64Content): void
    {
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new InvalidDocumentException("Invalid file type: {$mimeType}");
        }
        
        if ($size > 10 * 1024 * 1024) { // 10MB
            throw new InvalidDocumentException("File size exceeds 10MB limit");
        }
        
        if (empty($base64Content)) {
            throw new InvalidDocumentException("Document content cannot be empty");
        }
    }
}
```

---

## 🔧 Application Services

### **1. AffiliationService**
```php
<?php
namespace App\Application\Services;

class AffiliationService
{
    public function __construct(
        private AffiliationRepository $repository,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private PasswordGenerator $passwordGenerator
    ) {}
    
    public function requestChanges(UUID $applicationId, string $notes): string
    {
        $application = $this->repository->findById($applicationId);
        $editToken = $application->requestChanges($notes);
        
        $this->repository->save($application);
        $this->emailService->sendChangeRequestEmail($application, $editToken);
        
        return $editToken;
    }
    
    public function approve(UUID $applicationId): User
    {
        $application = $this->repository->findById($applicationId);
        
        // Create user account
        $tempPassword = $this->passwordGenerator->generate();
        $user = new User(
            email: $application->getEmail(),
            password: $tempPassword,
            fullName: $application->getContactPerson(),
            role: UserRole::SCHOOL_OFFICER,
            mustChangePassword: true
        );
        
        $this->userRepository->save($user);
        
        // Approve application
        $application->approve($user->getId());
        $this->repository->save($application);
        
        // Send credentials
        $this->emailService->sendCredentialsEmail($application, $user, $tempPassword);
        
        return $user;
    }
    
    public function reject(UUID $applicationId, string $reason): void
    {
        $application = $this->repository->findById($applicationId);
        $application->reject($reason);
        
        $this->repository->save($application);
        $this->emailService->sendRejectionEmail($application);
    }
    
    public function resubmit(string $editToken, DocumentCollection $documents): void
    {
        $application = $this->repository->findByEditToken($editToken);
        $application->resubmit($documents);
        
        $this->repository->save($application);
        $this->emailService->sendResubmissionNotification($application);
    }
}
```

### **2. AuthenticationService**
```php
<?php
namespace App\Application\Services;

class AuthenticationService
{
    public function __construct(
        private UserRepository $userRepository,
        private SessionManager $sessionManager
    ) {}
    
    public function login(string $email, string $password): AuthResult
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user || !$user->verifyPassword($password)) {
            return AuthResult::failure('Invalid credentials');
        }
        
        if (!$user->isActive()) {
            return AuthResult::failure('Account is inactive');
        }
        
        $this->sessionManager->startSession($user);
        
        if ($user->requiresPasswordChange()) {
            return AuthResult::passwordChangeRequired($user);
        }
        
        return AuthResult::success($user);
    }
    
    public function changePassword(UUID $userId, string $newPassword): void
    {
        $user = $this->userRepository->findById($userId);
        $user->changePassword($newPassword);
        
        $this->userRepository->save($user);
        $this->sessionManager->clearPasswordChangeFlag();
    }
}
```

---

## 🗄️ Repository Interfaces

### **1. AffiliationRepository Interface**
```php
<?php
namespace App\Domain\Repositories;

interface AffiliationRepository
{
    public function findById(UUID $id): ?AffiliationApplication;
    public function findByEditToken(string $token): ?AffiliationApplication;
    public function findByEmail(string $email): array;
    public function findByStatus(ApplicationStatus $status): array;
    public function save(AffiliationApplication $application): void;
    public function delete(UUID $id): void;
}
```

### **2. UserRepository Interface**
```php
<?php
namespace App\Domain\Repositories;

interface UserRepository
{
    public function findById(UUID $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): void;
    public function delete(UUID $id): void;
    public function findByRole(UserRole $role): array;
}
```

---

## 📧 Email Service

### **1. EmailService**
```php
<?php
namespace App\Infrastructure\Services;

class EmailService
{
    public function __construct(private PHPMailer $mailer) {}
    
    public function sendChangeRequestEmail(AffiliationApplication $application, string $editToken): bool
    {
        $template = new ChangeRequestEmailTemplate($application, $editToken);
        return $this->send($application->getEmail(), $template);
    }
    
    public function sendCredentialsEmail(AffiliationApplication $application, User $user, string $tempPassword): bool
    {
        $template = new CredentialsEmailTemplate($application, $user, $tempPassword);
        return $this->send($application->getEmail(), $template);
    }
    
    public function sendRejectionEmail(AffiliationApplication $application): bool
    {
        $template = new RejectionEmailTemplate($application);
        return $this->send($application->getEmail(), $template);
    }
    
    private function send(string $to, EmailTemplate $template): bool
    {
        $this->mailer->addAddress($to);
        $this->mailer->Subject = $template->getSubject();
        $this->mailer->Body = $template->getHtmlBody();
        
        return $this->mailer->send();
    }
}
```

---

## 🎯 Controllers

### **1. AffiliationReviewController**
```php
<?php
namespace App\Presentation\Controllers;

class AffiliationReviewController
{
    public function __construct(
        private AffiliationService $service,
        private SessionManager $session
    ) {}
    
    public function handleRequest(): JsonResponse
    {
        $this->requireAuthentication();
        $this->requireRole(['registration', 'admin', 'super_admin']);
        
        $action = $_POST['action'] ?? '';
        $id = $_POST['id'] ?? '';
        
        return match($action) {
            'request_changes' => $this->handleRequestChanges($id),
            'approve' => $this->handleApprove($id),
            'reject' => $this->handleReject($id),
            default => $this->jsonError('Invalid action', 400)
        };
    }
    
    private function handleApprove(string $id): JsonResponse
    {
        try {
            $user = $this->service->approve(new UUID($id));
            return $this->jsonSuccess([
                'message' => 'Application approved and account created',
                'user_id' => $user->getId()->toString()
            ]);
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
```

### **2. PasswordChangeController**
```php
<?php
namespace App\Presentation\Controllers;

class PasswordChangeController
{
    public function __construct(
        private AuthenticationService $authService,
        private SessionManager $session
    ) {}
    
    public function showForm(): void
    {
        if (!$this->session->requiresPasswordChange()) {
            $this->redirectToDashboard();
            return;
        }
        
        // Render change password form
        include __DIR__ . '/../../views/password-change.php';
    }
    
    public function handleChange(): JsonResponse
    {
        if (!$this->session->requiresPasswordChange()) {
            return $this->jsonError('Unauthorized', 403);
        }
        
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            return $this->jsonError('Passwords do not match');
        }
        
        try {
            $userId = $this->session->getCurrentUserId();
            $this->authService->changePassword($userId, $newPassword);
            
            return $this->jsonSuccess([
                'message' => 'Password changed successfully',
                'redirect' => $this->session->getCurrentUser()->getRole()->getDashboardPath()
            ]);
        } catch (Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }
}
```

---

## 🔐 Security Layer

### **1. Authentication Middleware**
```php
<?php
namespace App\Infrastructure\Middleware;

class AuthenticationMiddleware
{
    public function handle(): void
    {
        if (!$this->session->isAuthenticated()) {
            header('Location: /login.php');
            exit;
        }
    }
    
    public function requireRole(array $allowedRoles): void
    {
        $userRole = $this->session->getCurrentUserRole();
        
        if (!in_array($userRole->value, array_map(fn($r) => $r->value, $allowedRoles))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }
}
```

---

## 📁 File Structure

```
src/
├── Domain/
│   ├── Entities/
│   │   ├── User.php
│   │   ├── AffiliationApplication.php
│   │   └── Document.php
│   ├── ValueObjects/
│   │   ├── UserRole.php
│   │   ├── ApplicationStatus.php
│   │   └── UUID.php
│   ├── Repositories/
│   │   ├── UserRepository.php
│   │   └── AffiliationRepository.php
│   └── Exceptions/
│       ├── InvalidApplicationStateException.php
│       └── InvalidDocumentException.php
├── Application/
│   ├── Services/
│   │   ├── AffiliationService.php
│   │   └── AuthenticationService.php
│   └── UseCases/
│       ├── ApproveAffiliation.php
│       ├── RequestChanges.php
│       └── ChangePassword.php
├── Infrastructure/
│   ├── Repositories/
│   │   ├── SupabaseUserRepository.php
│   │   └── SupabaseAffiliationRepository.php
│   ├── Services/
│   │   ├── EmailService.php
│   │   ├── PasswordGenerator.php
│   │   └── SessionManager.php
│   └── Middleware/
│       └── AuthenticationMiddleware.php
└── Presentation/
    ├── Controllers/
    │   ├── AffiliationReviewController.php
    │   └── PasswordChangeController.php
    └── Views/
        ├── affiliation-dashboard.php
        └── password-change.php
```

---

## 🔄 Design Patterns Used

1. **Repository Pattern** - Abstract data access
2. **Service Layer Pattern** - Business logic encapsulation
3. **Factory Pattern** - Entity creation
4. **Strategy Pattern** - Email templates
5. **Observer Pattern** - Event notifications
6. **Dependency Injection** - Loose coupling
7. **Value Objects** - Immutable data types
8. **Domain Events** - Application state changes

---

## 🎯 SOLID Principles

- **S**ingle Responsibility - Each class has one purpose
- **O**pen/Closed - Open for extension, closed for modification
- **L**iskov Substitution - Interfaces can be swapped
- **I**nterface Segregation - Small, focused interfaces
- **D**ependency Inversion - Depend on abstractions

---

This OOD architecture provides a scalable, maintainable, and testable foundation for the IECEP-LSC MEMSYS affiliation system.
