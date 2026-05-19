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

$pageTitle = 'Collaboration Feed';
include '../../includes/dashboard-layout.php';

// Get collaboration posts with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    // Get posts with user info and pagination
    $posts = $supabaseClient->from('collaboration_posts')
        ->select('*, user_profiles(name, avatar_url, role)', ['count' => 'exact'])
        ->order('is_pinned', ['ascending' => false])
        ->order('created_at', ['ascending' => false])
        ->range($offset, $offset + $perPage - 1)
        ->execute();

    $totalPosts = $posts['count'] ?? 0;
    $totalPages = ceil($totalPosts / $perPage);

    // Get featured posts
    $featuredPosts = $supabaseClient->from('collaboration_posts')
        ->select('*, user_profiles(name, avatar_url)')
        ->eq('is_featured', true)
        ->order('created_at', ['ascending' => false])
        ->limit(5)
        ->execute();

} catch (Exception $e) {
    $posts = [];
    $featuredPosts = [];
    $totalPosts = 0;
    $totalPages = 0;
    error_log('Error fetching collaboration feed: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Collaboration Feed</h1>
                    <p class="text-muted">Latest discussions and collaborations from the community</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="toggleView('feed')">
                        <i class="fas fa-list me-1"></i>Feed
                    </button>
                    <button class="btn btn-outline-success" onclick="toggleView('featured')">
                        <i class="fas fa-star me-1"></i>Featured
                    </button>
                    <button class="btn btn-primary" onclick="window.location.href='index.php'">
                        <i class="fas fa-plus me-2"></i>Create Post
                    </button>
                </div>
            </div>

            <div class="row">
                <!-- Main Feed -->
                <div class="col-lg-8">
                    <!-- Feed Controls -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm" id="categoryFilter" onchange="filterPosts()">
                                        <option value="all">All Categories</option>
                                        <option value="projects">Projects</option>
                                        <option value="ideas">Ideas</option>
                                        <option value="help">Help Needed</option>
                                        <option value="events">Events</option>
                                        <option value="general">General</option>
                                    </select>
                                    <select class="form-select form-select-sm" id="sortFilter" onchange="filterPosts()">
                                        <option value="latest">Latest First</option>
                                        <option value="popular">Most Popular</option>
                                        <option value="trending">Trending</option>
                                    </select>
                                </div>
                                <div class="text-muted small">
                                    Showing <?= min($offset + 1, $totalPosts) ?>-<?= min($offset + $perPage, $totalPosts) ?> of <?= $totalPosts ?> posts
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Posts Feed -->
                    <div id="postsFeed">
                        <?php if (empty($posts)): ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No posts found</h5>
                                    <p class="text-muted">Be the first to start a discussion!</p>
                                    <button class="btn btn-primary" onclick="window.location.href='index.php'">
                                        Create First Post
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <div class="card mb-3 post-card <?= $post['is_pinned'] ? 'border-warning' : '' ?>"
                                     data-category="<?= htmlspecialchars($post['category']) ?>"
                                     data-pinned="<?= $post['is_pinned'] ? '1' : '0' ?>"
                                     data-featured="<?= $post['is_featured'] ? '1' : '0' ?>">
                                    <?php if ($post['is_pinned']): ?>
                                        <div class="card-header bg-warning text-dark">
                                            <i class="fas fa-thumbtack me-2"></i>
                                            <strong>Pinned Post</strong>
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="avatar-circle me-3">
                                                <?= strtoupper(substr($post['user_profiles']['name'], 0, 1)) ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <a href="#" class="text-decoration-none">
                                                                <?= htmlspecialchars($post['user_profiles']['name']) ?>
                                                            </a>
                                                            <?php if ($post['user_profiles']['role']): ?>
                                                                <span class="badge bg-primary ms-2">
                                                                    <?= ucfirst(str_replace('eb_', '', $post['user_profiles']['role'])) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <span class="badge bg-<?= getCategoryColor($post['category']) ?> ms-2">
                                                                <?= ucfirst($post['category']) ?>
                                                            </span>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?= date('M d, Y H:i', strtotime($post['created_at'])) ?>
                                                            <?php if ($post['is_featured']): ?>
                                                                <i class="fas fa-star text-warning ms-2" title="Featured Post"></i>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-h"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="sharePost('<?= $post['id'] ?>')">
                                                                <i class="fas fa-share me-2"></i>Share
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="reportPost('<?= $post['id'] ?>')">
                                                                <i class="fas fa-flag me-2"></i>Report
                                                            </a></li>
                                                            <?php if ($post['user_id'] === $_SESSION['user_id'] || in_array($_SESSION['role'], ['eb_admin', 'eb_president'])): ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li><a class="dropdown-item" href="#" onclick="editPost('<?= $post['id'] ?>')">
                                                                    <i class="fas fa-edit me-2"></i>Edit
                                                                </a></li>
                                                                <li><a class="dropdown-item text-danger" href="#" onclick="deletePost('<?= $post['id'] ?>')">
                                                                    <i class="fas fa-trash me-2"></i>Delete
                                                                </a></li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <h5 class="card-title mb-2">
                                            <a href="#" class="text-decoration-none text-dark" onclick="viewPost('<?= $post['id'] ?>')">
                                                <?= htmlspecialchars($post['title']) ?>
                                            </a>
                                        </h5>
                                        <p class="card-text mb-3">
                                            <?= nl2br(htmlspecialchars(substr($post['content'], 0, 300))) ?>
                                            <?php if (strlen($post['content']) > 300): ?>
                                                <a href="#" onclick="viewPost('<?= $post['id'] ?>')" class="text-primary">...read more</a>
                                            <?php endif; ?>
                                        </p>

                                        <?php if (!empty($post['attachments'])): ?>
                                            <div class="attachments mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-paperclip me-1"></i>
                                                    <?= count(json_decode($post['attachments'], true)) ?> attachment(s)
                                                </small>
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
                                            <div class="post-stats">
                                                <small class="text-muted">
                                                    <i class="fas fa-eye me-1"></i><?= $post['views_count'] ?? 0 ?> views
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Posts pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
                                </li>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Featured Posts -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-star text-warning me-2"></i>Featured Posts
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($featuredPosts)): ?>
                                <p class="text-muted small">No featured posts yet</p>
                            <?php else: ?>
                                <?php foreach ($featuredPosts as $featured): ?>
                                    <div class="featured-post-item mb-3 pb-3 border-bottom">
                                        <h6 class="mb-1">
                                            <a href="#" onclick="viewPost('<?= $featured['id'] ?>')" class="text-decoration-none">
                                                <?= htmlspecialchars(substr($featured['title'], 0, 50)) ?>...
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            by <?= htmlspecialchars($featured['user_profiles']['name']) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Trending Topics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-fire text-danger me-2"></i>Trending Topics
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="trending-topic mb-2">
                                <a href="#" class="text-decoration-none">#IECEP2024</a>
                                <small class="text-muted d-block">245 posts</small>
                            </div>
                            <div class="trending-topic mb-2">
                                <a href="#" class="text-decoration-none">#ResearchCollab</a>
                                <small class="text-muted d-block">189 posts</small>
                            </div>
                            <div class="trending-topic mb-2">
                                <a href="#" class="text-decoration-none">#StudentExchange</a>
                                <small class="text-muted d-block">156 posts</small>
                            </div>
                            <div class="trending-topic">
                                <a href="#" class="text-decoration-none">#Innovation</a>
                                <small class="text-muted d-block">98 posts</small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Community Stats
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Total Posts:</span>
                                <strong><?= $totalPosts ?></strong>
                            </div>
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Active Members:</span>
                                <strong>1,247</strong>
                            </div>
                            <div class="stat-item d-flex justify-content-between mb-2">
                                <span>Discussions Today:</span>
                                <strong>23</strong>
                            </div>
                            <div class="stat-item d-flex justify-content-between">
                                <span>Most Active:</span>
                                <strong>This Week</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Mark posts as viewed when scrolled into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const postId = entry.target.dataset.postId;
                if (postId) {
                    markAsViewed(postId);
                }
            }
        });
    });

    document.querySelectorAll('.post-card').forEach(post => {
        observer.observe(post);
    });
});

// Toggle between feed and featured views
function toggleView(view) {
    if (view === 'featured') {
        window.location.href = '?view=featured';
    } else {
        window.location.href = '?view=feed';
    }
}

// Filter posts
function filterPosts() {
    const category = document.getElementById('categoryFilter').value;
    const sort = document.getElementById('sortFilter').value;

    // Reload page with filters
    const params = new URLSearchParams(window.location.search);
    params.set('category', category);
    params.set('sort', sort);
    window.location.search = params.toString();
}

// View full post
function viewPost(postId) {
    window.open(`post-details.php?id=${postId}`, '_blank');
}

// Like post
function likePost(postId) {
    fetch(`../../api/collaboration.php?action=like_post&id=${postId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update like count
            const likeBtn = event.target.closest('.btn');
            const badge = likeBtn.querySelector('.badge');
            const currentCount = parseInt(badge.textContent);
            badge.textContent = currentCount + 1;
            likeBtn.classList.add('active');
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
    // Open comments modal or expand post
    showToast('Comments feature coming soon!', 'info');
}

// Share post
function sharePost(postId) {
    const url = window.location.href.split('?')[0] + '?post=' + postId;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Post link copied to clipboard!', 'success');
    });
}

// Report post
function reportPost(postId) {
    if (confirm('Are you sure you want to report this post?')) {
        fetch(`../../api/collaboration.php?action=report_post&id=${postId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Post reported successfully', 'success');
            } else {
                showToast('Error reporting post', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error reporting post', 'error');
        });
    }
}

// Edit post
function editPost(postId) {
    window.location.href = `edit-post.php?id=${postId}`;
}

// Delete post
function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        fetch(`../../api/collaboration.php?action=delete_post&id=${postId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Post deleted successfully!', 'success');
                location.reload();
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

// Mark post as viewed
function markAsViewed(postId) {
    fetch(`../../api/collaboration.php?action=view_post&id=${postId}`, {
        method: 'POST'
    }).catch(error => console.error('Error marking post as viewed:', error));
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