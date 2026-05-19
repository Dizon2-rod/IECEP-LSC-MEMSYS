<?php
require_once __DIR__ . '/../bootstrap.php';
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'Collaboration Portal';
include '../../includes/dashboard-layout.php';

// Get collaboration posts
try {
    $posts = $supabaseClient->from('collaboration_posts')
        ->select('*, user_profiles(name, avatar_url)')
        ->order('created_at', ['ascending' => false])
        ->limit(20)
        ->execute();

    // Get user's collaboration stats
    $userStats = $supabaseClient->from('collaboration_posts')
        ->select('*', ['count' => 'exact'])
        ->eq('user_id', $_SESSION['user_id'])
        ->execute();

} catch (Exception $e) {
    $posts = [];
    $userStats = 0;
    error_log('Error fetching collaboration data: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Collaboration Portal</h1>
                    <p class="text-muted">Connect, share ideas, and collaborate with fellow members</p>
                </div>
                <button class="btn btn-primary" onclick="showCreatePostModal()">
                    <i class="fas fa-plus me-2"></i>Create Post
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?= count($posts) ?></h4>
                            <small class="text-muted">Active Discussions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-edit fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?= $userStats ?></h4>
                            <small class="text-muted">Your Posts</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-heart fa-2x text-danger mb-2"></i>
                            <h4 class="mb-0">0</h4>
                            <small class="text-muted">Likes Received</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-reply fa-2x text-info mb-2"></i>
                            <h4 class="mb-0">0</h4>
                            <small class="text-muted">Replies</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-primary btn-sm" onclick="filterPosts('all')">
                                    <i class="fas fa-list me-1"></i>All Posts
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="filterPosts('projects')">
                                    <i class="fas fa-project-diagram me-1"></i>Projects
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="filterPosts('ideas')">
                                    <i class="fas fa-lightbulb me-1"></i>Ideas
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="filterPosts('help')">
                                    <i class="fas fa-question-circle me-1"></i>Help Needed
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="filterPosts('events')">
                                    <i class="fas fa-calendar me-1"></i>Events
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Posts Feed -->
            <div id="postsContainer">
                <?php if (empty($posts)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No posts yet</h5>
                            <p class="text-muted">Be the first to start a discussion!</p>
                            <button class="btn btn-primary" onclick="showCreatePostModal()">
                                Create First Post
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="card mb-3 post-card" data-category="<?= htmlspecialchars($post['category']) ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="avatar-circle me-3">
                                        <?= strtoupper(substr($post['user_profiles']['name'], 0, 1)) ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($post['user_profiles']['name']) ?>
                                                    <span class="badge bg-<?= getCategoryColor($post['category']) ?> ms-2">
                                                        <?= ucfirst($post['category']) ?>
                                                    </span>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= date('M d, Y H:i', strtotime($post['created_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="editPost('<?= $post['id'] ?>')">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deletePost('<?= $post['id'] ?>')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="card-title mb-2"><?= htmlspecialchars($post['title']) ?></h5>
                                <p class="card-text mb-3"><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                                <?php if (!empty($post['attachments'])): ?>
                                    <div class="attachments mb-3">
                                        <?php foreach (json_decode($post['attachments'], true) as $attachment): ?>
                                            <div class="attachment-item">
                                                <i class="fas fa-paperclip me-2"></i>
                                                <a href="<?= htmlspecialchars($attachment['url']) ?>" target="_blank">
                                                    <?= htmlspecialchars($attachment['name']) ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="post-actions">
                                        <button class="btn btn-sm btn-outline-primary me-2" onclick="likePost('<?= $post['id'] ?>')">
                                            <i class="fas fa-heart me-1"></i>Like
                                            <span class="badge bg-primary ms-1"><?= $post['likes_count'] ?? 0 ?></span>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary me-2" onclick="showComments('<?= $post['id'] ?>')">
                                            <i class="fas fa-comment me-1"></i>Comment
                                            <span class="badge bg-secondary ms-1"><?= $post['comments_count'] ?? 0 ?></span>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info" onclick="sharePost('<?= $post['id'] ?>')">
                                            <i class="fas fa-share me-1"></i>Share
                                        </button>
                                    </div>
                                    <div class="post-status">
                                        <?php if ($post['is_pinned']): ?>
                                            <i class="fas fa-thumbtack text-warning me-2" title="Pinned"></i>
                                        <?php endif; ?>
                                        <?php if ($post['is_featured']): ?>
                                            <i class="fas fa-star text-warning" title="Featured"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createPostForm">
                    <div class="mb-3">
                        <label for="postTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="postTitle" required>
                    </div>
                    <div class="mb-3">
                        <label for="postCategory" class="form-label">Category</label>
                        <select class="form-select" id="postCategory" required>
                            <option value="">Select category...</option>
                            <option value="projects">Projects</option>
                            <option value="ideas">Ideas</option>
                            <option value="help">Help Needed</option>
                            <option value="events">Events</option>
                            <option value="general">General Discussion</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="postContent" class="form-label">Content</label>
                        <textarea class="form-control" id="postContent" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="postAttachments" class="form-label">Attachments (optional)</label>
                        <input type="file" class="form-control" id="postAttachments" multiple>
                        <small class="text-muted">Supported formats: PDF, DOC, DOCX, JPG, PNG (max 5MB each)</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="isPinned">
                        <label class="form-check-label" for="isPinned">
                            Pin this post (only admins can pin posts)
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitPost()">Create Post</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh posts every 30 seconds
    setInterval(loadPosts, 30000);
});

// Show create post modal
function showCreatePostModal() {
    const modal = new bootstrap.Modal(document.getElementById('createPostModal'));
    modal.show();
}

// Submit new post
function submitPost() {
    const form = document.getElementById('createPostForm');
    const formData = new FormData();

    formData.append('title', document.getElementById('postTitle').value);
    formData.append('category', document.getElementById('postCategory').value);
    formData.append('content', document.getElementById('postContent').value);
    formData.append('is_pinned', document.getElementById('isPinned').checked ? '1' : '0');

    // Handle file attachments
    const attachments = document.getElementById('postAttachments').files;
    for (let i = 0; i < attachments.length; i++) {
        formData.append('attachments[]', attachments[i]);
    }

    fetch('../../api/collaboration.php?action=create_post', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Post created successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createPostModal')).hide();
            form.reset();
            loadPosts();
        } else {
            showToast('Error creating post: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error creating post', 'error');
    });
}

// Filter posts by category
function filterPosts(category) {
    const posts = document.querySelectorAll('.post-card');

    posts.forEach(post => {
        if (category === 'all' || post.dataset.category === category) {
            post.style.display = 'block';
        } else {
            post.style.display = 'none';
        }
    });

    // Update active button
    document.querySelectorAll('.btn-outline-primary, .btn-outline-success, .btn-outline-info, .btn-outline-warning, .btn-outline-secondary')
        .forEach(btn => btn.classList.remove('active'));

    event.target.classList.add('active');
}

// Like post
function likePost(postId) {
    fetch(`../../api/collaboration.php?action=like_post&id=${postId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPosts(); // Refresh to show updated like count
        } else {
            showToast('Error liking post', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error liking post', 'error');
    });
}

// Show comments
function showComments(postId) {
    // This would typically open a comments modal or expand the post
    showToast('Comments feature coming soon!', 'info');
}

// Share post
function sharePost(postId) {
    const url = window.location.href + '?post=' + postId;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Post link copied to clipboard!', 'success');
    });
}

// Edit post
function editPost(postId) {
    showToast('Edit feature coming soon!', 'info');
}

// Delete post
function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post?')) {
        fetch(`../../api/collaboration.php?action=delete_post&id=${postId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Post deleted successfully!', 'success');
                loadPosts();
            } else {
                showToast('Error deleting post', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting post', 'error');
        });
    }
}

// Load posts (for refresh)
function loadPosts() {
    fetch('../../api/collaboration.php?action=get_posts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update posts container with new data
                // This is a simplified version - in production you'd want to properly update the DOM
                location.reload();
            }
        })
        .catch(error => console.error('Error loading posts:', error));
}

// Get category color helper
function getCategoryColor(category) {
    const colors = {
        'projects': 'primary',
        'ideas': 'success',
        'help': 'warning',
        'events': 'info',
        'general': 'secondary'
    };
    return colors[category] || 'secondary';
}

// Toast notification helper
function showToast(message, type) {
    // Assuming toast.js is available
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php
function getCategoryColor($category) {
    $colors = [
        'projects' => 'primary',
        'ideas' => 'success',
        'help' => 'warning',
        'events' => 'info',
        'general' => 'secondary'
    ];
    return $colors[$category] ?? 'secondary';
}
?>